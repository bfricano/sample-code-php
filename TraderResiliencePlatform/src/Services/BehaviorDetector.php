<?php
declare(strict_types=1);

namespace Trader\Src\Services;

use PDO;

class BehaviorDetector
{
    public function __construct(private PDO $db) {}

    /**
     * Run all behavioral checks against recent trades and check-ins.
     * Returns any patterns detected.
     */
    public function runDetection(int $traderId): array
    {
        $recentTrades  = $this->getRecentTrades($traderId, 20);
        $todayTrades   = $this->getTodayTrades($traderId);
        $avgDailyCount = $this->getAvgDailyTradeCount($traderId);
        $checkin       = $this->getLatestCheckin($traderId);

        $patterns = [];

        $revenge = $this->detectRevengeTrade($recentTrades);
        if ($revenge) $patterns[] = $revenge;

        $overtrading = $this->detectOvertrading($todayTrades, $avgDailyCount);
        if ($overtrading) $patterns[] = $overtrading;

        $tilt = $this->detectTilt($recentTrades);
        if ($tilt) $patterns[] = $tilt;

        $fomo = $this->detectFOMO($recentTrades, $checkin);
        if ($fomo) $patterns[] = $fomo;

        $chasing = $this->detectLossCutting($recentTrades);
        if ($chasing) $patterns[] = $chasing;

        return $patterns;
    }

    public function detectRevengeTrade(array $recentTrades): ?array
    {
        // Revenge: large loss followed by immediately larger position
        for ($i = 1; $i < count($recentTrades); $i++) {
            $prev = $recentTrades[$i - 1];
            $curr = $recentTrades[$i];
            $prevPnl = (float)$prev['pnl'];
            $prevSize = (float)$prev['position_size'];
            $currSize = (float)$curr['position_size'];

            if ($prevPnl < 0 && $currSize > $prevSize * 1.5) {
                return [
                    'type'        => 'REVENGE_TRADE',
                    'severity'    => 3,
                    'title'       => 'Revenge Trade Detected',
                    'message'     => sprintf(
                        'After a loss of $%s on %s, position size increased %.0f%% on the next trade. This is a classic revenge pattern.',
                        number_format(abs($prevPnl), 2),
                        $prev['instrument'],
                        (($currSize / $prevSize) - 1) * 100
                    ),
                    'action'      => 'Return to standard position sizing. If you feel the urge to "make it back", step away for 15 minutes.',
                ];
            }
        }
        return null;
    }

    public function detectOvertrading(array $todayTrades, float $avgDailyCount): ?array
    {
        $count = count($todayTrades);
        if ($avgDailyCount > 0 && $count > $avgDailyCount * 2) {
            return [
                'type'     => 'OVERTRADING',
                'severity' => 2,
                'title'    => 'Overtrading Alert',
                'message'  => sprintf(
                    'You have taken %d trades today vs your average of %.1f. Overtrading destroys edge and increases transaction costs.',
                    $count, $avgDailyCount
                ),
                'action'   => 'Set a hard daily trade limit in your pre-market plan and commit to it.',
            ];
        }
        if ($count >= 8) {
            return [
                'type'     => 'OVERTRADING',
                'severity' => 3,
                'title'    => 'Excessive Trade Count',
                'message'  => "8+ trades in a single session. Quantity does not equal quality — the best setups are rare by definition.",
                'action'   => 'Stop trading for the day. Review what is driving this behavior.',
            ];
        }
        return null;
    }

    public function detectTilt(array $recentTrades): ?array
    {
        // Tilt: 3+ consecutive losses
        if (count($recentTrades) < 3) return null;
        $last3 = array_slice($recentTrades, -3);
        $allLosses = array_filter($last3, fn($t) => (float)$t['pnl'] < 0);

        if (count($allLosses) === 3) {
            $totalLoss = array_sum(array_column(array_values($allLosses), 'pnl'));
            return [
                'type'     => 'TILT',
                'severity' => 4,
                'title'    => 'Tilt Warning — 3 Consecutive Losses',
                'message'  => sprintf(
                    '3 consecutive losses totaling $%s. Your decision quality degrades significantly on tilt.',
                    number_format(abs($totalLoss), 2)
                ),
                'action'   => 'Mandatory 30-minute break. Journal what went wrong. Do not trade again until resilience score is rechecked.',
            ];
        }
        return null;
    }

    public function detectFOMO(array $recentTrades, ?array $checkin): ?array
    {
        if (empty($recentTrades)) return null;
        $last = $recentTrades[count($recentTrades) - 1];

        // Check: low execution score with high confidence in checkin = chasing
        if ($checkin && (int)$checkin['confidence_level'] >= 9 && (int)$last['execution_score'] <= 4) {
            return [
                'type'     => 'FOMO',
                'severity' => 2,
                'title'    => 'FOMO Signal Detected',
                'message'  => 'High confidence check-in followed by poor execution score suggests chasing price. FOMO trades have a negative expectancy.',
                'action'   => 'Wait for price to come to YOUR level. Patience is the most underrated trading skill.',
            ];
        }
        return null;
    }

    public function detectLossCutting(array $recentTrades): ?array
    {
        // Multiple R > -1.5 (stopped out bigger than plan)
        $bigLosses = array_filter($recentTrades, fn($t) => (float)($t['r_multiple'] ?? 0) < -1.2);
        if (count($bigLosses) >= 2) {
            return [
                'type'     => 'STOP_VIOLATION',
                'severity' => 3,
                'title'    => 'Stop Loss Violations Detected',
                'message'  => sprintf(
                    '%d recent trades exceeded the -1R stop. Moving stops is the fastest way to blow an account.',
                    count($bigLosses)
                ),
                'action'   => 'Use hard stops entered immediately upon position entry. No manual intervention during the trade.',
            ];
        }
        return null;
    }

    public function getBehavioralSummary(int $traderId, int $days = 30): array
    {
        $stmt = $this->db->prepare("
            SELECT pattern_type, COUNT(*) as count, AVG(severity) as avg_severity
            FROM behavioral_patterns
            WHERE trader_id = ? AND pattern_date >= DATE('now', '-{$days} days')
            GROUP BY pattern_type
            ORDER BY count DESC
        ");
        $stmt->execute([$traderId]);
        return $stmt->fetchAll();
    }

    public function getRuleViolations(int $traderId, int $days = 30): array
    {
        $stmt = $this->db->prepare("
            SELECT trade_date, instrument, rule_violations, pnl, r_multiple
            FROM trades
            WHERE trader_id = ? AND rule_violations != '[]'
              AND trade_date >= DATE('now', '-{$days} days')
            ORDER BY trade_date DESC
        ");
        $stmt->execute([$traderId]);
        return $stmt->fetchAll();
    }

    private function getRecentTrades(int $traderId, int $limit = 20): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM trades
            WHERE trader_id = ? AND status = 'CLOSED'
            ORDER BY trade_date DESC, created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$traderId, $limit]);
        return array_reverse($stmt->fetchAll());
    }

    private function getTodayTrades(int $traderId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM trades
            WHERE trader_id = ? AND trade_date = DATE('now') AND status != 'CANCELLED'
        ");
        $stmt->execute([$traderId]);
        return $stmt->fetchAll();
    }

    private function getAvgDailyTradeCount(int $traderId): float
    {
        $stmt = $this->db->prepare("
            SELECT AVG(trade_count) FROM daily_performance
            WHERE trader_id = ? AND trade_count > 0
              AND perf_date >= DATE('now', '-30 days')
        ");
        $stmt->execute([$traderId]);
        return (float)($stmt->fetchColumn() ?? 0);
    }

    private function getLatestCheckin(int $traderId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM checkins
            WHERE trader_id = ? AND checkin_date = DATE('now')
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$traderId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
