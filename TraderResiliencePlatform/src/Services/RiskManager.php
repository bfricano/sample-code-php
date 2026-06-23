<?php
declare(strict_types=1);

namespace Trader\Src\Services;

use PDO;

class RiskManager
{
    public function __construct(private PDO $db) {}

    /**
     * Check all risk rules and return violations.
     */
    public function checkRules(int $traderId): array
    {
        $trader   = $this->getTrader($traderId);
        $rules    = $this->getRules($traderId);
        $todayPnl = $this->getTodayPnl($traderId);
        $weekPnl  = $this->getWeekPnl($traderId);
        $equity   = $this->getCurrentEquity($traderId);
        $violations = [];

        foreach ($rules as $rule) {
            if (!(int)$rule['is_active']) continue;

            $triggered = false;
            $current   = 0.0;

            switch ($rule['condition_type']) {
                case 'DAILY_LOSS_PCT':
                    $current   = abs(min(0, $todayPnl)) / $equity * 100;
                    $triggered = $todayPnl < 0 && $current >= (float)$rule['threshold_value'];
                    break;
                case 'WEEKLY_LOSS_PCT':
                    $current   = abs(min(0, $weekPnl)) / $equity * 100;
                    $triggered = $weekPnl < 0 && $current >= (float)$rule['threshold_value'];
                    break;
                case 'DAILY_TRADE_COUNT':
                    $current   = $this->getTodayTradeCount($traderId);
                    $triggered = $current >= (float)$rule['threshold_value'];
                    break;
                case 'RESILIENCE_SCORE':
                    $current = $this->getLatestResilienceScore($traderId);
                    $triggered = $current > 0 && $current < (float)$rule['threshold_value'];
                    break;
                case 'CONSECUTIVE_LOSS':
                    $current   = $this->getConsecutiveLossCount($traderId);
                    $triggered = $current >= (float)$rule['threshold_value'];
                    break;
            }

            if ($triggered) {
                $violations[] = [
                    'rule_id'        => $rule['id'],
                    'rule_name'      => $rule['rule_name'],
                    'rule_type'      => $rule['rule_type'],
                    'action'         => $rule['action'],
                    'current_value'  => round($current, 2),
                    'threshold'      => (float)$rule['threshold_value'],
                    'condition_type' => $rule['condition_type'],
                ];
                // Update trigger count
                $this->db->prepare("UPDATE risk_rules SET trigger_count = trigger_count + 1 WHERE id = ?")
                    ->execute([$rule['id']]);
            }
        }

        return $violations;
    }

    public function getDashboardRisk(int $traderId): array
    {
        $trader    = $this->getTrader($traderId);
        $equity    = $this->getCurrentEquity($traderId);
        $todayPnl  = $this->getTodayPnl($traderId);
        $weekPnl   = $this->getWeekPnl($traderId);
        $monthPnl  = $this->getMonthPnl($traderId);
        $peakEquity = $this->getPeakEquity($traderId);
        $drawdown   = $peakEquity > 0 ? ($peakEquity - $equity) / $peakEquity * 100 : 0;

        $dailyLossPct  = $equity > 0 ? abs(min(0, $todayPnl)) / $equity * 100 : 0;
        $weeklyLossPct = $equity > 0 ? abs(min(0, $weekPnl))  / $equity * 100 : 0;

        return [
            'equity'           => round($equity, 2),
            'peak_equity'      => round($peakEquity, 2),
            'drawdown_pct'     => round($drawdown, 2),
            'today_pnl'        => round($todayPnl, 2),
            'today_pnl_pct'    => $equity > 0 ? round($todayPnl / $equity * 100, 2) : 0,
            'week_pnl'         => round($weekPnl, 2),
            'month_pnl'        => round($monthPnl, 2),
            'daily_loss_pct'   => round($dailyLossPct, 2),
            'weekly_loss_pct'  => round($weeklyLossPct, 2),
            'daily_limit'      => (float)$trader['max_daily_loss'],
            'weekly_limit'     => (float)$trader['max_weekly_loss'],
            'today_trades'     => $this->getTodayTradeCount($traderId),
            'violations'       => $this->checkRules($traderId),
            'halt_trading'     => $this->shouldHaltTrading($traderId),
        ];
    }

    public function shouldHaltTrading(int $traderId): array
    {
        $violations = $this->checkRules($traderId);
        $halts      = array_filter($violations, fn($v) => $v['action'] === 'HALT_TRADING');
        return [
            'halt'     => !empty($halts),
            'reasons'  => array_values($halts),
        ];
    }

    public function getRules(int $traderId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM risk_rules WHERE trader_id = ? ORDER BY rule_type, rule_name");
        $stmt->execute([$traderId]);
        return $stmt->fetchAll();
    }

    public function upsertRule(int $traderId, array $data): int
    {
        if (!empty($data['id'])) {
            $stmt = $this->db->prepare("UPDATE risk_rules SET rule_name=?, rule_type=?, condition_type=?, threshold_value=?, action=?, is_active=? WHERE id=? AND trader_id=?");
            $stmt->execute([$data['rule_name'], $data['rule_type'], $data['condition_type'], $data['threshold_value'], $data['action'], $data['is_active'] ?? 1, $data['id'], $traderId]);
            return (int)$data['id'];
        }
        $stmt = $this->db->prepare("INSERT INTO risk_rules (trader_id, rule_name, rule_type, condition_type, threshold_value, action) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$traderId, $data['rule_name'], $data['rule_type'], $data['condition_type'], $data['threshold_value'], $data['action']]);
        return (int)$this->db->lastInsertId();
    }

    private function getTrader(int $traderId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM traders WHERE id = ?");
        $stmt->execute([$traderId]);
        return $stmt->fetch() ?: [];
    }

    private function getCurrentEquity(int $traderId): float
    {
        $stmt = $this->db->prepare("SELECT equity_end FROM daily_performance WHERE trader_id = ? ORDER BY perf_date DESC LIMIT 1");
        $stmt->execute([$traderId]);
        $row = $stmt->fetch();
        if ($row && $row['equity_end']) return (float)$row['equity_end'];

        $stmt = $this->db->prepare("SELECT account_size FROM traders WHERE id = ?");
        $stmt->execute([$traderId]);
        $t = $stmt->fetch();
        return $t ? (float)$t['account_size'] : 100000;
    }

    private function getPeakEquity(int $traderId): float
    {
        $stmt = $this->db->prepare("SELECT MAX(equity_end) FROM daily_performance WHERE trader_id = ?");
        $stmt->execute([$traderId]);
        $peak = $stmt->fetchColumn();
        return $peak ? (float)$peak : $this->getCurrentEquity($traderId);
    }

    private function getTodayPnl(int $traderId): float
    {
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(pnl),0) FROM trades WHERE trader_id=? AND trade_date=DATE('now') AND status='CLOSED'");
        $stmt->execute([$traderId]);
        return (float)$stmt->fetchColumn();
    }

    private function getWeekPnl(int $traderId): float
    {
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(pnl),0) FROM trades WHERE trader_id=? AND trade_date>=DATE('now','weekday 1','-7 days') AND status='CLOSED'");
        $stmt->execute([$traderId]);
        return (float)$stmt->fetchColumn();
    }

    private function getMonthPnl(int $traderId): float
    {
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(pnl),0) FROM trades WHERE trader_id=? AND trade_date>=DATE('now','start of month') AND status='CLOSED'");
        $stmt->execute([$traderId]);
        return (float)$stmt->fetchColumn();
    }

    private function getTodayTradeCount(int $traderId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM trades WHERE trader_id=? AND trade_date=DATE('now') AND status!='CANCELLED'");
        $stmt->execute([$traderId]);
        return (int)$stmt->fetchColumn();
    }

    private function getConsecutiveLossCount(int $traderId): int
    {
        $stmt = $this->db->prepare("SELECT pnl FROM trades WHERE trader_id=? AND status='CLOSED' ORDER BY trade_date DESC, created_at DESC LIMIT 10");
        $stmt->execute([$traderId]);
        $rows = $stmt->fetchAll();
        $count = 0;
        foreach ($rows as $r) {
            if ((float)$r['pnl'] < 0) $count++;
            else break;
        }
        return $count;
    }

    private function getLatestResilienceScore(int $traderId): float
    {
        $stmt = $this->db->prepare("SELECT resilience_score FROM checkins WHERE trader_id=? AND checkin_type='PRE_MARKET' ORDER BY checkin_date DESC, created_at DESC LIMIT 1");
        $stmt->execute([$traderId]);
        $row = $stmt->fetch();
        return $row ? (float)$row['resilience_score'] : 0;
    }
}
