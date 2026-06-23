<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Services/ResilienceEngine.php';
require_once __DIR__ . '/../src/Services/CapitalAllocator.php';
require_once __DIR__ . '/../src/Services/Analytics.php';
require_once __DIR__ . '/../src/Services/BehaviorDetector.php';
require_once __DIR__ . '/../src/Services/RiskManager.php';

use Trader\Src\Database;
use Trader\Src\Services\ResilienceEngine;
use Trader\Src\Services\CapitalAllocator;
use Trader\Src\Services\Analytics;
use Trader\Src\Services\BehaviorDetector;
use Trader\Src\Services\RiskManager;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$db = Database::getInstance();

$resilience  = new ResilienceEngine($db);
$allocator   = new CapitalAllocator();
$analytics   = new Analytics($db);
$behavior    = new BehaviorDetector($db);
$risk        = new RiskManager($db);

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = preg_replace('#^/api#', '', $uri);
$uri    = rtrim($uri, '/');
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

function ok(mixed $data): void {
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}
function err(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

$traderId = 1; // Single-trader mode; extend with auth for multi-tenant

// ─── ROUTES ────────────────────────────────────────────────────────────────

// Dashboard command center
if ($uri === '/dashboard' && $method === 'GET') {
    $riskData    = $risk->getDashboardRisk($traderId);
    $latestScore = $resilience->getLatestScore($traderId);
    $trader      = $db->query("SELECT * FROM traders WHERE id=1")->fetch();
    $gate        = $resilience->getReadinessGate($latestScore, (int)$trader['min_resilience_score']);
    $behaviors   = $behavior->runDetection($traderId);
    $recentTrades = $db->query("SELECT * FROM trades WHERE trader_id=1 AND status='CLOSED' ORDER BY trade_date DESC, created_at DESC LIMIT 8")->fetchAll();
    $equityCurve  = $analytics->getEquityCurve($traderId, 'ALL');
    $resTrend     = $resilience->getTrend($traderId, 30);

    // YTD metrics
    $ytdStmt = $db->query("SELECT COUNT(*) as c, COALESCE(SUM(pnl),0) as pnl, COUNT(CASE WHEN pnl>0 THEN 1 END) as wins FROM trades WHERE trader_id=1 AND status='CLOSED' AND trade_date>=DATE('now','start of year')");
    $ytd = $ytdStmt->fetch();

    ok([
        'trader'        => $trader,
        'risk'          => $riskData,
        'resilience'    => ['score' => $latestScore, 'gate' => $gate, 'trend' => $resTrend],
        'behaviors'     => $behaviors,
        'recent_trades' => $recentTrades,
        'equity_curve'  => array_slice($equityCurve, -90),
        'ytd'           => ['trades' => (int)$ytd['c'], 'pnl' => round((float)$ytd['pnl'], 2), 'win_rate' => $ytd['c'] > 0 ? round((float)$ytd['wins'] / (float)$ytd['c'] * 100, 1) : 0],
    ]);
}

// ─── TRADES ───────────────────────────────────────────────────────────────
if (preg_match('#^/trades(/(\d+))?$#', $uri, $m)) {
    $tradeId = isset($m[2]) ? (int)$m[2] : null;

    if ($method === 'GET' && !$tradeId) {
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(10, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $setup = $_GET['setup'] ?? '';
        $period = $_GET['period'] ?? 'ALL';

        $where = "WHERE t.trader_id = 1 AND t.status = 'CLOSED'";
        if ($setup) $where .= " AND t.setup_type = " . $db->quote($setup);
        $dateFilter = match($period) {
            'TODAY'   => "AND t.trade_date = DATE('now')",
            'WEEK'    => "AND t.trade_date >= DATE('now','weekday 1','-7 days')",
            'MONTH'   => "AND t.trade_date >= DATE('now','start of month')",
            'QUARTER' => "AND t.trade_date >= DATE('now','-90 days')",
            'YTD'     => "AND t.trade_date >= DATE('now','start of year')",
            default   => '',
        };
        $total = $db->query("SELECT COUNT(*) FROM trades t {$where} {$dateFilter}")->fetchColumn();
        $stmt  = $db->query("SELECT t.* FROM trades t {$where} {$dateFilter} ORDER BY t.trade_date DESC, t.created_at DESC LIMIT {$limit} OFFSET {$offset}");
        ok(['trades' => $stmt->fetchAll(), 'total' => (int)$total, 'page' => $page, 'limit' => $limit]);
    }

    if ($method === 'GET' && $tradeId) {
        $stmt = $db->prepare("SELECT * FROM trades WHERE id=? AND trader_id=1");
        $stmt->execute([$tradeId]);
        $t = $stmt->fetch();
        $t ? ok($t) : err('Trade not found', 404);
    }

    if ($method === 'POST') {
        $required = ['trade_date', 'instrument', 'direction', 'entry_price', 'position_size'];
        foreach ($required as $f) {
            if (empty($body[$f])) err("Missing required field: {$f}");
        }

        // Calculate pnl and r_multiple if exit given
        $pnl = 0.0; $pnlPct = 0.0; $rMult = null;
        if (!empty($body['exit_price']) && !empty($body['entry_price'])) {
            $dir    = $body['direction'];
            $entry  = (float)$body['entry_price'];
            $exit   = (float)$body['exit_price'];
            $size   = (float)$body['position_size'];
            $pnl    = round(($exit - $entry) * $size * ($dir === 'LONG' ? 1 : -1), 2);
            $pnlPct = $db->query("SELECT account_size FROM traders WHERE id=1")->fetchColumn();
            $pnlPct = $pnlPct > 0 ? round($pnl / (float)$pnlPct * 100, 4) : 0;
            if (!empty($body['risk_amount']) && (float)$body['risk_amount'] > 0) {
                $rMult = round($pnl / (float)$body['risk_amount'], 3);
            }
        }

        $stmt = $db->prepare("INSERT INTO trades
            (trader_id, trade_date, instrument, direction, setup_type, entry_price, exit_price,
             stop_loss, take_profit, position_size, pnl, pnl_percent, risk_amount, r_multiple,
             mae, mfe, duration_minutes, execution_score, rule_violations, tags, notes, status)
            VALUES (1,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $body['trade_date'], $body['instrument'], $body['direction'],
            $body['setup_type'] ?? 'Other',
            $body['entry_price'], $body['exit_price'] ?? null, $body['stop_loss'] ?? null,
            $body['take_profit'] ?? null, $body['position_size'],
            $pnl, $pnlPct, $body['risk_amount'] ?? 0, $rMult,
            $body['mae'] ?? 0, $body['mfe'] ?? 0, $body['duration_minutes'] ?? 0,
            $body['execution_score'] ?? 7,
            json_encode($body['rule_violations'] ?? []),
            json_encode($body['tags'] ?? []),
            $body['notes'] ?? '',
            $body['status'] ?? 'CLOSED',
        ]);
        $id = (int)$db->lastInsertId();

        // Update daily performance
        updateDailyPerformance($db, $body['trade_date']);

        ok(['id' => $id]);
    }

    if ($method === 'PUT' && $tradeId) {
        $fields = ['trade_date','instrument','direction','setup_type','entry_price','exit_price','stop_loss','take_profit','position_size','pnl','pnl_percent','risk_amount','r_multiple','mae','mfe','duration_minutes','execution_score','notes','status','tags','execution_score'];
        $sets = []; $vals = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $body)) {
                $sets[] = "{$f} = ?";
                $vals[] = in_array($f, ['tags','rule_violations']) ? json_encode($body[$f]) : $body[$f];
            }
        }
        if (empty($sets)) err('No fields to update');
        $vals[] = $tradeId;
        $db->prepare("UPDATE trades SET " . implode(',', $sets) . " WHERE id=? AND trader_id=1")->execute($vals);
        $date = $body['trade_date'] ?? null;
        if ($date) updateDailyPerformance($db, $date);
        ok(['updated' => $tradeId]);
    }

    if ($method === 'DELETE' && $tradeId) {
        $stmt = $db->prepare("SELECT trade_date FROM trades WHERE id=? AND trader_id=1");
        $stmt->execute([$tradeId]);
        $t = $stmt->fetch();
        $db->prepare("DELETE FROM trades WHERE id=? AND trader_id=1")->execute([$tradeId]);
        if ($t) updateDailyPerformance($db, $t['trade_date']);
        ok(['deleted' => $tradeId]);
    }
}

// ─── CHECKINS ─────────────────────────────────────────────────────────────
if (preg_match('#^/checkins(/(\d+))?$#', $uri, $m)) {
    $cId = isset($m[2]) ? (int)$m[2] : null;

    if ($method === 'GET' && !$cId) {
        $limit = (int)($_GET['limit'] ?? 30);
        $stmt  = $db->prepare("SELECT * FROM checkins WHERE trader_id=? ORDER BY checkin_date DESC, created_at DESC LIMIT ?");
        $stmt->execute([1, $limit]);
        ok($stmt->fetchAll());
    }

    if ($method === 'POST') {
        $c = array_merge([
            'sleep_quality' => 7, 'emotional_state' => 7, 'focus_level' => 7,
            'stress_level' => 4, 'physical_energy' => 7, 'confidence_level' => 7,
        ], $body);
        $score = $resilience->calculateScore($c);
        $stmt  = $db->prepare("INSERT INTO checkins
            (trader_id, checkin_date, checkin_type, sleep_quality, emotional_state, focus_level,
             stress_level, physical_energy, confidence_level, market_bias, planned_max_trades,
             game_plan, notes, resilience_score)
            VALUES (1,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $body['checkin_date'] ?? date('Y-m-d'),
            $body['checkin_type'] ?? 'PRE_MARKET',
            $c['sleep_quality'], $c['emotional_state'], $c['focus_level'],
            $c['stress_level'], $c['physical_energy'], $c['confidence_level'],
            $body['market_bias'] ?? 'NEUTRAL', $body['planned_max_trades'] ?? 3,
            $body['game_plan'] ?? '', $body['notes'] ?? '', $score,
        ]);
        $gate = $resilience->getReadinessGate($score, 65);
        ok(['id' => (int)$db->lastInsertId(), 'resilience_score' => $score, 'gate' => $gate]);
    }
}

// ─── RESILIENCE ────────────────────────────────────────────────────────────
if ($uri === '/resilience' && $method === 'GET') {
    $days        = (int)($_GET['days'] ?? 60);
    $trend       = $resilience->getTrend($traderId, $days);
    $latestScore = $resilience->getLatestScore($traderId);
    $trader      = $db->query("SELECT * FROM traders WHERE id=1")->fetch();
    $gate        = $resilience->getReadinessGate($latestScore, (int)$trader['min_resilience_score']);
    $correlation = $resilience->getPerformanceCorrelation($traderId);
    $coaching    = $resilience->getCoachingInsights($traderId);
    ok(['score' => $latestScore, 'gate' => $gate, 'trend' => $trend, 'correlation' => $correlation, 'coaching' => $coaching]);
}

// ─── ANALYTICS ─────────────────────────────────────────────────────────────
if ($uri === '/analytics' && $method === 'GET') {
    $period  = $_GET['period'] ?? 'ALL';
    $metrics = $analytics->getFullMetrics($traderId, $period);
    ok($metrics);
}

// ─── CAPITAL ALLOCATOR ─────────────────────────────────────────────────────
if ($uri === '/allocate' && $method === 'POST') {
    $trader      = $db->query("SELECT * FROM traders WHERE id=1")->fetch();
    $resScore    = $resilience->getLatestScore($traderId);
    $riskData    = $risk->getDashboardRisk($traderId);
    $drawdown    = (float)$riskData['drawdown_pct'];

    $entry  = (float)($body['entry_price'] ?? 0);
    $stop   = (float)($body['stop_loss']   ?? 0);
    $base   = (float)($body['risk_pct']    ?? $trader['risk_per_trade']);
    $equity = (float)($body['account_equity'] ?? $riskData['equity']);
    $conf   = (float)($body['setup_confidence'] ?? 1.0);

    if ($entry <= 0 || $stop <= 0) err('entry_price and stop_loss are required');

    // Kelly from historical stats
    $ytdMetrics = $analytics->getFullMetrics($traderId, 'YTD');
    $kelly      = ['full_kelly' => 0, 'half_kelly' => 0, 'quarter_kelly' => 0];
    if (!empty($ytdMetrics['summary'])) {
        $s = $ytdMetrics['summary'];
        $kelly = $allocator->kellyCriterion($s['win_rate'] / 100, $s['avg_win'], $s['avg_loss']);
    }

    $sizing  = $allocator->calculatePositionSize($equity, $base, $entry, $stop, $resScore, $drawdown, $kelly['half_kelly'] / 100, $conf);
    $atrStop = isset($body['atr']) ? $allocator->atrStop($entry, (float)$body['atr'], $body['direction'] ?? 'LONG') : null;
    $rScen   = $allocator->rMultipleScenarios($sizing['risk_amount'] ?? 0);

    ok(['sizing' => $sizing, 'kelly' => $kelly, 'atr_stop' => $atrStop, 'r_scenarios' => $rScen, 'resilience_score' => $resScore]);
}

// ─── RISK ──────────────────────────────────────────────────────────────────
if ($uri === '/risk' && $method === 'GET') {
    ok($risk->getDashboardRisk($traderId));
}

if ($uri === '/risk/rules' && $method === 'GET') {
    ok($risk->getRules($traderId));
}

if ($uri === '/risk/rules' && $method === 'POST') {
    $id = $risk->upsertRule($traderId, $body);
    ok(['id' => $id]);
}

if (preg_match('#^/risk/rules/(\d+)$#', $uri, $m) && $method === 'DELETE') {
    $db->prepare("DELETE FROM risk_rules WHERE id=? AND trader_id=?")->execute([$m[1], $traderId]);
    ok(['deleted' => (int)$m[1]]);
}

// ─── BEHAVIOR ──────────────────────────────────────────────────────────────
if ($uri === '/behavior' && $method === 'GET') {
    $summary  = $behavior->getBehavioralSummary($traderId);
    $live     = $behavior->runDetection($traderId);
    $violations = $behavior->getRuleViolations($traderId);
    ok(['patterns' => $live, 'summary' => $summary, 'violations' => $violations]);
}

// ─── PROFILE ──────────────────────────────────────────────────────────────
if ($uri === '/profile' && $method === 'GET') {
    ok($db->query("SELECT * FROM traders WHERE id=1")->fetch());
}

if ($uri === '/profile' && $method === 'PUT') {
    $fields = ['name','account_size','risk_per_trade','max_daily_loss','max_weekly_loss','max_monthly_loss','min_resilience_score','benchmark','timezone'];
    $sets = []; $vals = [];
    foreach ($fields as $f) {
        if (array_key_exists($f, $body)) { $sets[] = "{$f}=?"; $vals[] = $body[$f]; }
    }
    if (!empty($sets)) {
        $vals[] = 1;
        $db->prepare("UPDATE traders SET " . implode(',', $sets) . " WHERE id=?")->execute($vals);
    }
    ok($db->query("SELECT * FROM traders WHERE id=1")->fetch());
}

// ─── ALERTS ───────────────────────────────────────────────────────────────
if ($uri === '/alerts' && $method === 'GET') {
    $stmt = $db->prepare("SELECT * FROM alerts WHERE trader_id=? AND is_read=0 ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([1]);
    ok($stmt->fetchAll());
}

if (preg_match('#^/alerts/(\d+)/read$#', $uri, $m) && $method === 'POST') {
    $db->prepare("UPDATE alerts SET is_read=1 WHERE id=? AND trader_id=1")->execute([$m[1]]);
    ok(['marked' => (int)$m[1]]);
}

// 404
err("Route not found: {$method} {$uri}", 404);

// ─── HELPERS ───────────────────────────────────────────────────────────────
function updateDailyPerformance(\PDO $db, string $date): void
{
    $stmt = $db->prepare("SELECT SUM(pnl) as pnl, COUNT(*) as cnt, COUNT(CASE WHEN pnl>0 THEN 1 END) as wins, COUNT(CASE WHEN pnl<0 THEN 1 END) as losses, MAX(pnl) as max_win, MIN(pnl) as min_loss FROM trades WHERE trader_id=1 AND trade_date=? AND status='CLOSED'");
    $stmt->execute([$date]);
    $row = $stmt->fetch();

    // Running equity
    $prevEquity = $db->query("SELECT equity_end FROM daily_performance WHERE trader_id=1 AND perf_date<'{$date}' ORDER BY perf_date DESC LIMIT 1")->fetchColumn();
    if (!$prevEquity) {
        $prevEquity = $db->query("SELECT account_size FROM traders WHERE id=1")->fetchColumn();
    }
    $equity = (float)$prevEquity + (float)$row['pnl'];

    $resAvg = $db->prepare("SELECT AVG(resilience_score) FROM checkins WHERE trader_id=1 AND checkin_date=? AND checkin_type='PRE_MARKET'");
    $resAvg->execute([$date]);
    $res = (float)$resAvg->fetchColumn();

    $ins = $db->prepare("INSERT OR REPLACE INTO daily_performance (trader_id,perf_date,gross_pnl,net_pnl,trade_count,win_count,loss_count,largest_win,largest_loss,equity_end,resilience_avg) VALUES (1,?,?,?,?,?,?,?,?,?,?)");
    $ins->execute([$date, $row['pnl']??0, $row['pnl']??0, $row['cnt']??0, $row['wins']??0, $row['losses']??0, $row['max_win']??0, $row['min_loss']??0, $equity, $res]);
}
