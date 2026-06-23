<?php
declare(strict_types=1);

namespace Trader\Src\Services;

use PDO;

class ResilienceEngine
{
    public function __construct(private PDO $db) {}

    public function calculateScore(array $c): float
    {
        // Weighted composite: sleep 15%, emotion 15%, focus 20%, stress 15% (inverted), energy 10%, confidence 15%, streak 10%
        $raw = ($c['sleep_quality'] * 1.5)
             + ($c['emotional_state'] * 1.5)
             + ($c['focus_level'] * 2.0)
             + ((10 - $c['stress_level']) * 1.5)
             + ($c['physical_energy'] * 1.0)
             + ($c['confidence_level'] * 1.5);
        return round(min(100, max(0, $raw)), 1);
    }

    public function getTrend(int $traderId, int $days = 60): array
    {
        $stmt = $this->db->prepare("
            SELECT checkin_date, AVG(resilience_score) as score
            FROM checkins
            WHERE trader_id = ? AND checkin_type = 'PRE_MARKET'
              AND checkin_date >= DATE('now', '-{$days} days')
            GROUP BY checkin_date
            ORDER BY checkin_date ASC
        ");
        $stmt->execute([$traderId]);
        return $stmt->fetchAll();
    }

    public function getLatestScore(int $traderId): float
    {
        $stmt = $this->db->prepare("
            SELECT resilience_score FROM checkins
            WHERE trader_id = ? AND checkin_type = 'PRE_MARKET'
            ORDER BY checkin_date DESC, created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$traderId]);
        $row = $stmt->fetch();
        return $row ? (float)$row['resilience_score'] : 0.0;
    }

    public function getReadinessGate(float $score, int $minScore): array
    {
        if ($score >= 85) {
            return ['ready' => true, 'level' => 'PEAK', 'color' => '#10b981', 'message' => 'Peak state. Execute your plan with conviction.', 'risk_multiplier' => 1.0];
        }
        if ($score >= 70) {
            return ['ready' => true, 'level' => 'GOOD', 'color' => '#22c55e', 'message' => 'Good trading state. Stay disciplined.', 'risk_multiplier' => 1.0];
        }
        if ($score >= $minScore) {
            return ['ready' => true, 'level' => 'REDUCED', 'color' => '#f59e0b', 'message' => 'Below optimal. Reduce size to 50% and focus only on A+ setups.', 'risk_multiplier' => 0.5];
        }
        return ['ready' => false, 'level' => 'SIT_OUT', 'color' => '#ef4444', 'message' => 'Below threshold. Protect your capital. Do not trade today.', 'risk_multiplier' => 0.0];
    }

    public function getPerformanceCorrelation(int $traderId): array
    {
        $stmt = $this->db->prepare("
            SELECT c.checkin_date,
                   AVG(c.resilience_score) as resilience,
                   COALESCE(SUM(t.pnl), 0) as daily_pnl,
                   COALESCE(COUNT(t.id), 0) as trade_count
            FROM checkins c
            LEFT JOIN trades t ON t.trade_date = c.checkin_date AND t.trader_id = c.trader_id
            WHERE c.trader_id = ? AND c.checkin_type = 'PRE_MARKET'
              AND c.checkin_date >= DATE('now', '-90 days')
            GROUP BY c.checkin_date
            ORDER BY c.checkin_date ASC
        ");
        $stmt->execute([$traderId]);
        $rows = $stmt->fetchAll();

        // Pearson correlation coefficient
        $n = count($rows);
        if ($n < 2) return ['coefficient' => 0, 'data' => $rows, 'interpretation' => 'Insufficient data'];

        $sumR = $sumP = $sumRR = $sumPP = $sumRP = 0;
        foreach ($rows as $row) {
            $r = (float)$row['resilience'];
            $p = (float)$row['daily_pnl'];
            $sumR  += $r;
            $sumP  += $p;
            $sumRR += $r * $r;
            $sumPP += $p * $p;
            $sumRP += $r * $p;
        }

        $num   = $n * $sumRP - $sumR * $sumP;
        $denSq = ($n * $sumRR - $sumR * $sumR) * ($n * $sumPP - $sumP * $sumP);
        $coeff = $denSq > 0 ? round($num / sqrt($denSq), 3) : 0;

        $interp = match(true) {
            $coeff >= 0.7  => 'Strong positive: high resilience strongly correlates with better P&L',
            $coeff >= 0.4  => 'Moderate positive: resilience has meaningful impact on performance',
            $coeff >= 0.1  => 'Weak positive: slight correlation',
            $coeff <= -0.4 => 'Negative correlation: review your state assessment accuracy',
            default        => 'Minimal correlation detected'
        };

        return ['coefficient' => $coeff, 'data' => $rows, 'interpretation' => $interp];
    }

    public function getCoachingInsights(int $traderId): array
    {
        $stmt = $this->db->prepare("
            SELECT sleep_quality, emotional_state, focus_level, stress_level,
                   physical_energy, confidence_level, resilience_score
            FROM checkins
            WHERE trader_id = ? AND checkin_type = 'PRE_MARKET'
              AND checkin_date >= DATE('now', '-30 days')
            ORDER BY checkin_date DESC
        ");
        $stmt->execute([$traderId]);
        $rows = $stmt->fetchAll();

        if (empty($rows)) {
            return [['type' => 'info', 'text' => 'Start logging daily check-ins to unlock personalized coaching insights.']];
        }

        $avgs = array_reduce($rows, function ($carry, $row) {
            foreach (['sleep_quality','emotional_state','focus_level','stress_level','physical_energy','confidence_level'] as $k) {
                $carry[$k] = ($carry[$k] ?? 0) + (float)$row[$k];
            }
            return $carry;
        }, []);
        $cnt = count($rows);
        foreach ($avgs as $k => $v) $avgs[$k] = round($v / $cnt, 1);

        $insights = [];

        if ($avgs['sleep_quality'] < 6) {
            $insights[] = ['type' => 'warning', 'text' => "Avg sleep quality is {$avgs['sleep_quality']}/10. Poor sleep is the #1 resilience killer. Establish a pre-bed routine: screen-off at 9:30pm, 7-9 hours target."];
        }
        if ($avgs['stress_level'] > 7) {
            $insights[] = ['type' => 'critical', 'text' => "Chronic high stress ({$avgs['stress_level']}/10). High cortisol impairs risk assessment. Consider 10-min morning meditation before market open."];
        }
        if ($avgs['focus_level'] < 6) {
            $insights[] = ['type' => 'warning', 'text' => "Focus averaging {$avgs['focus_level']}/10. Build a pre-market routine: chart review, key levels, written game plan before each session."];
        }
        if ($avgs['confidence_level'] < 5) {
            $insights[] = ['type' => 'info', 'text' => "Low confidence detected. Review your edge statistics — data-driven confidence outperforms intuition. Focus on process, not outcomes."];
        }
        if ($avgs['emotional_state'] < 6) {
            $insights[] = ['type' => 'warning', 'text' => "Emotional state is suboptimal. Consider journaling to externalize negative emotional states before trading. Emotion = noise; your edge = signal."];
        }
        if (empty($insights)) {
            $insights[] = ['type' => 'success', 'text' => "Excellent baseline mental metrics over the past 30 days. Maintain this discipline — consistency is the institutional edge."];
        }

        return $insights;
    }
}
