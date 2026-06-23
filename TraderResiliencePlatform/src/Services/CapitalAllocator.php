<?php
declare(strict_types=1);

namespace Trader\Src\Services;

class CapitalAllocator
{
    /**
     * Full position sizing recommendation using multi-factor model.
     */
    public function calculatePositionSize(
        float $accountEquity,
        float $baseRiskPct,
        float $entryPrice,
        float $stopLoss,
        float $resilienceScore,
        float $currentDrawdownPct,
        float $kellyFraction = 0.0,
        float $setupConfidence = 1.0
    ): array {
        if ($entryPrice <= 0 || abs($entryPrice - $stopLoss) < 0.0001) {
            return ['error' => 'Invalid entry or stop loss'];
        }

        // 1. Resilience adjustment
        $resilienceMultiplier = match(true) {
            $resilienceScore >= 85 => 1.0,
            $resilienceScore >= 70 => 1.0,
            $resilienceScore >= 60 => 0.5,
            default                => 0.0
        };

        // 2. Drawdown adjustment (anti-martingale — cut size as drawdown deepens)
        $drawdownMultiplier = match(true) {
            $currentDrawdownPct >= 10.0 => 0.25,
            $currentDrawdownPct >= 7.5  => 0.375,
            $currentDrawdownPct >= 5.0  => 0.5,
            $currentDrawdownPct >= 2.5  => 0.75,
            default                     => 1.0
        };

        // 3. Kelly cap: half-Kelly is already the reduced fraction; only shrink if base risk exceeds it
        $kellyMultiplier = ($kellyFraction > 0 && $baseRiskPct > 0)
            ? min(1.0, ($kellyFraction * 100) / $baseRiskPct)
            : 1.0;

        // 4. Setup confidence multiplier
        $confMultiplier = max(0.25, min(1.5, $setupConfidence));

        // Combined risk %
        $effectiveRiskPct = $baseRiskPct * $resilienceMultiplier * $drawdownMultiplier * $kellyMultiplier * $confMultiplier;
        $riskAmount       = $accountEquity * ($effectiveRiskPct / 100);

        // Position size
        $riskPerShare = abs($entryPrice - $stopLoss);
        $shares       = $riskAmount / $riskPerShare;
        $notional     = $shares * $entryPrice;
        $leverage     = $notional / $accountEquity;

        return [
            'base_risk_pct'        => round($baseRiskPct, 3),
            'effective_risk_pct'   => round($effectiveRiskPct, 3),
            'risk_amount'          => round($riskAmount, 2),
            'risk_per_share'       => round($riskPerShare, 4),
            'shares'               => round($shares, 4),
            'notional'             => round($notional, 2),
            'leverage'             => round($leverage, 2),
            'adjustments' => [
                'resilience'  => ['multiplier' => $resilienceMultiplier, 'score' => $resilienceScore],
                'drawdown'    => ['multiplier' => $drawdownMultiplier,   'pct'   => $currentDrawdownPct],
                'kelly'       => ['multiplier' => $kellyMultiplier,      'raw'   => $kellyFraction],
                'confidence'  => ['multiplier' => $confMultiplier,       'value' => $setupConfidence],
            ],
        ];
    }

    /**
     * Full Kelly Criterion: f* = (p - q/b)
     * Uses Half-Kelly in practice.
     */
    public function kellyCriterion(float $winRate, float $avgWin, float $avgLoss): array
    {
        if ($avgLoss <= 0 || $winRate <= 0 || $winRate >= 1) {
            return ['full_kelly' => 0, 'half_kelly' => 0, 'quarter_kelly' => 0];
        }
        $b     = $avgWin / $avgLoss;
        $q     = 1 - $winRate;
        $kelly = ($winRate - ($q / $b));
        $kelly = max(0, $kelly);

        return [
            'full_kelly'    => round($kelly * 100, 2),
            'half_kelly'    => round($kelly * 50, 2),
            'quarter_kelly' => round($kelly * 25, 2),
            'b_ratio'       => round($b, 3),
        ];
    }

    /**
     * ATR-based stop loss calculation.
     */
    public function atrStop(float $entry, float $atr, string $direction, float $multiplier = 2.0): array
    {
        $stopDist = $atr * $multiplier;
        $stop     = $direction === 'LONG' ? $entry - $stopDist : $entry + $stopDist;
        $target1  = $direction === 'LONG' ? $entry + $stopDist * 1.5 : $entry - $stopDist * 1.5; // 1.5R
        $target2  = $direction === 'LONG' ? $entry + $stopDist * 3.0 : $entry - $stopDist * 3.0; // 3R

        return [
            'stop'       => round($stop, 4),
            'target_1r5' => round($target1, 4),
            'target_3r'  => round($target2, 4),
            'stop_dist'  => round($stopDist, 4),
            'atr_mult'   => $multiplier,
        ];
    }

    /**
     * Check portfolio heat across open positions.
     */
    public function checkPortfolioHeat(array $openPositions, float $accountEquity, float $maxHeatPct = 5.0): array
    {
        $totalRisk = array_sum(array_column($openPositions, 'risk_amount'));
        $heatPct   = ($totalRisk / $accountEquity) * 100;

        return [
            'total_risk'  => round($totalRisk, 2),
            'heat_pct'    => round($heatPct, 2),
            'max_heat'    => $maxHeatPct,
            'remaining'   => round(max(0, ($maxHeatPct - $heatPct) / 100 * $accountEquity), 2),
            'utilization' => round(min(100, $heatPct / $maxHeatPct * 100), 1),
            'status'      => $heatPct >= $maxHeatPct ? 'AT_LIMIT' : ($heatPct >= $maxHeatPct * 0.8 ? 'WARNING' : 'OK'),
        ];
    }

    /**
     * Drawdown-adjusted account equity for sizing.
     */
    public function drawdownAdjustment(float $drawdownPct): float
    {
        return match(true) {
            $drawdownPct >= 10.0 => 0.25,
            $drawdownPct >= 7.5  => 0.375,
            $drawdownPct >= 5.0  => 0.5,
            $drawdownPct >= 2.5  => 0.75,
            default              => 1.0,
        };
    }

    /**
     * R-multiple scenarios for a given setup.
     */
    public function rMultipleScenarios(float $riskAmount): array
    {
        $scenarios = [];
        foreach ([-1.0, -0.5, 0.5, 1.0, 1.5, 2.0, 3.0, 5.0] as $r) {
            $scenarios[] = [
                'r'      => $r,
                'pnl'    => round($r * $riskAmount, 2),
                'label'  => $r >= 0 ? "+{$r}R" : "{$r}R",
            ];
        }
        return $scenarios;
    }
}
