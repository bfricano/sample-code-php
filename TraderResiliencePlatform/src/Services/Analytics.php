<?php
declare(strict_types=1);

namespace Trader\Src\Services;

use PDO;

class Analytics
{
    public function __construct(private PDO $db) {}

    public function getFullMetrics(int $traderId, string $period = 'ALL'): array
    {
        $where = $this->periodWhere($period);
        $stmt  = $this->db->prepare("
            SELECT * FROM trades
            WHERE trader_id = ? AND status = 'CLOSED' {$where}
            ORDER BY trade_date ASC, created_at ASC
        ");
        $stmt->execute([$traderId]);
        $trades = $stmt->fetchAll();

        if (empty($trades)) {
            return ['error' => 'No trades found for this period'];
        }

        $pnls    = array_column($trades, 'pnl');
        $rmults  = array_filter(array_column($trades, 'r_multiple'), fn($v) => $v !== null);
        $wins    = array_filter($pnls, fn($p) => $p > 0);
        $losses  = array_filter($pnls, fn($p) => $p < 0);

        $totalPnl   = array_sum($pnls);
        $grossWins  = array_sum($wins);
        $grossLoss  = abs(array_sum($losses));
        $winRate    = count($pnls) > 0 ? count($wins) / count($pnls) : 0;
        $avgWin     = count($wins)  > 0 ? $grossWins / count($wins)   : 0;
        $avgLoss    = count($losses) > 0 ? $grossLoss / count($losses) : 0;
        $expectancy = ($winRate * $avgWin) - ((1 - $winRate) * $avgLoss);
        $profitFactor = $grossLoss > 0 ? $grossWins / $grossLoss : ($grossWins > 0 ? 999 : 0);

        // Equity curve for Sharpe/Drawdown
        $equity      = $this->getEquityCurve($traderId, $period);
        $returns     = $this->dailyReturns($equity);
        $sharpe      = $this->sharpeRatio($returns);
        $sortino     = $this->sortinoRatio($returns);
        $ddData      = $this->maxDrawdown($equity);
        $calmar      = $ddData['max_dd_pct'] > 0
            ? $this->annualizedReturn($returns) / $ddData['max_dd_pct']
            : 0;

        // Streak analysis
        $streaks = $this->streakAnalysis($pnls);

        // By setup
        $bySetup = $this->performanceBySetup($trades);

        // By instrument
        $byInstrument = $this->performanceByInstrument($trades);

        // R-multiple distribution
        $rDist = $this->rMultipleDistribution(array_values($rmults));

        // Rolling 30-day Sharpe
        $rollingMetrics = $this->rollingMetrics($equity);

        return [
            'summary' => [
                'total_trades'  => count($trades),
                'total_pnl'     => round($totalPnl, 2),
                'gross_wins'    => round($grossWins, 2),
                'gross_loss'    => round($grossLoss, 2),
                'win_count'     => count($wins),
                'loss_count'    => count($losses),
                'win_rate'      => round($winRate * 100, 2),
                'avg_win'       => round($avgWin, 2),
                'avg_loss'      => round($avgLoss, 2),
                'avg_r_mult'    => count($rmults) ? round(array_sum($rmults) / count($rmults), 3) : 0,
                'expectancy'    => round($expectancy, 2),
                'profit_factor' => round($profitFactor, 3),
                'largest_win'   => count($wins)   ? round(max($wins), 2)   : 0,
                'largest_loss'  => count($losses) ? round(min($losses), 2) : 0,
            ],
            'risk_adjusted' => [
                'sharpe'  => round($sharpe, 3),
                'sortino' => round($sortino, 3),
                'calmar'  => round($calmar, 3),
                'max_dd'  => $ddData,
                'var_95'  => $this->valueAtRisk($pnls, 0.95),
            ],
            'streaks'         => $streaks,
            'by_setup'        => $bySetup,
            'by_instrument'   => $byInstrument,
            'r_distribution'  => $rDist,
            'equity_curve'    => $equity,
            'rolling'         => $rollingMetrics,
        ];
    }

    public function getEquityCurve(int $traderId, string $period = 'ALL'): array
    {
        $stmt = $this->db->prepare("SELECT account_size FROM traders WHERE id = ?");
        $stmt->execute([$traderId]);
        $trader = $stmt->fetch();
        $initial = $trader ? (float)$trader['account_size'] : 100000;

        $where = $this->periodWhereDate('perf_date', $period);
        $stmt = $this->db->prepare("
            SELECT perf_date, net_pnl, equity_end
            FROM daily_performance
            WHERE trader_id = ? {$where}
            ORDER BY perf_date ASC
        ");
        $stmt->execute([$traderId]);
        $rows = $stmt->fetchAll();

        if (empty($rows)) return [];

        $curve  = [];
        $equity = (float)($rows[0]['equity_end'] ?: $initial);
        foreach ($rows as $row) {
            if ($row['equity_end']) $equity = (float)$row['equity_end'];
            $curve[] = ['date' => $row['perf_date'], 'equity' => round($equity, 2), 'pnl' => round((float)$row['net_pnl'], 2)];
        }
        return $curve;
    }

    private function dailyReturns(array $equity): array
    {
        $returns = [];
        for ($i = 1; $i < count($equity); $i++) {
            $prev = $equity[$i - 1]['equity'];
            $curr = $equity[$i]['equity'];
            if ($prev > 0) $returns[] = ($curr - $prev) / $prev;
        }
        return $returns;
    }

    public function sharpeRatio(array $returns, float $riskFree = 0.0475): float
    {
        $n = count($returns);
        if ($n < 2) return 0;
        $dailyRf = $riskFree / 252;
        $excess  = array_map(fn($r) => $r - $dailyRf, $returns);
        $mean    = array_sum($excess) / $n;
        $variance = array_sum(array_map(fn($r) => ($r - $mean) ** 2, $excess)) / ($n - 1);
        return $variance > 0 ? $mean / sqrt($variance) * sqrt(252) : 0;
    }

    public function sortinoRatio(array $returns, float $riskFree = 0.0475): float
    {
        $n = count($returns);
        if ($n < 2) return 0;
        $dailyRf   = $riskFree / 252;
        $excess    = array_map(fn($r) => $r - $dailyRf, $returns);
        $mean      = array_sum($excess) / $n;
        $downside  = array_filter($excess, fn($r) => $r < 0);
        if (empty($downside)) return $mean > 0 ? 999 : 0;
        $dVar      = array_sum(array_map(fn($r) => $r ** 2, $downside)) / $n;
        return $dVar > 0 ? $mean / sqrt($dVar) * sqrt(252) : 0;
    }

    public function maxDrawdown(array $equityCurve): array
    {
        if (empty($equityCurve)) return ['max_dd_pct' => 0, 'max_dd_abs' => 0, 'peak_date' => null, 'trough_date' => null];

        $peak     = 0;
        $maxDd    = 0;
        $maxDdAbs = 0;
        $peakDate = $troughDate = $peakDateFinal = $troughDateFinal = null;

        foreach ($equityCurve as $point) {
            $eq = (float)$point['equity'];
            if ($eq > $peak) {
                $peak     = $eq;
                $peakDate = $point['date'];
            }
            $dd = ($peak - $eq) / $peak * 100;
            if ($dd > $maxDd) {
                $maxDd        = $dd;
                $maxDdAbs     = $peak - $eq;
                $peakDateFinal   = $peakDate;
                $troughDateFinal = $point['date'];
            }
        }

        return [
            'max_dd_pct'   => round($maxDd, 2),
            'max_dd_abs'   => round($maxDdAbs, 2),
            'peak_date'    => $peakDateFinal,
            'trough_date'  => $troughDateFinal,
        ];
    }

    private function annualizedReturn(array $dailyReturns): float
    {
        if (empty($dailyReturns)) return 0;
        $n     = count($dailyReturns);
        $total = array_product(array_map(fn($r) => 1 + $r, $dailyReturns));
        return ($total ** (252 / $n) - 1) * 100;
    }

    public function valueAtRisk(array $pnls, float $confidence = 0.95): float
    {
        if (empty($pnls)) return 0;
        sort($pnls);
        $idx = (int)floor((1 - $confidence) * count($pnls));
        return round($pnls[$idx], 2);
    }

    private function streakAnalysis(array $pnls): array
    {
        $currentStreak = $maxWinStreak = $maxLossStreak = 0;
        $streakType    = null;

        foreach ($pnls as $pnl) {
            $isWin = $pnl > 0;
            if ($streakType === null || $streakType !== $isWin) {
                $currentStreak = 1;
                $streakType    = $isWin;
            } else {
                $currentStreak++;
            }
            if ($isWin)  $maxWinStreak  = max($maxWinStreak, $currentStreak);
            else         $maxLossStreak = max($maxLossStreak, $currentStreak);
        }

        $currentStreakDir = $streakType ? 'win' : 'loss';
        return [
            'current'         => $currentStreak,
            'current_type'    => $currentStreakDir,
            'max_win_streak'  => $maxWinStreak,
            'max_loss_streak' => $maxLossStreak,
        ];
    }

    private function performanceBySetup(array $trades): array
    {
        $groups = [];
        foreach ($trades as $t) {
            $setup = $t['setup_type'] ?: 'Other';
            if (!isset($groups[$setup])) {
                $groups[$setup] = ['trades' => 0, 'pnl' => 0, 'wins' => 0, 'r_sum' => 0];
            }
            $groups[$setup]['trades']++;
            $groups[$setup]['pnl']   += (float)$t['pnl'];
            $groups[$setup]['r_sum'] += (float)($t['r_multiple'] ?? 0);
            if ((float)$t['pnl'] > 0) $groups[$setup]['wins']++;
        }
        $result = [];
        foreach ($groups as $name => $g) {
            $result[] = [
                'setup'       => $name,
                'trades'      => $g['trades'],
                'total_pnl'   => round($g['pnl'], 2),
                'win_rate'    => round($g['wins'] / $g['trades'] * 100, 1),
                'avg_r'       => round($g['r_sum'] / $g['trades'], 3),
            ];
        }
        usort($result, fn($a, $b) => $b['total_pnl'] <=> $a['total_pnl']);
        return $result;
    }

    private function performanceByInstrument(array $trades): array
    {
        $groups = [];
        foreach ($trades as $t) {
            $sym = $t['instrument'];
            if (!isset($groups[$sym])) {
                $groups[$sym] = ['trades' => 0, 'pnl' => 0, 'wins' => 0];
            }
            $groups[$sym]['trades']++;
            $groups[$sym]['pnl'] += (float)$t['pnl'];
            if ((float)$t['pnl'] > 0) $groups[$sym]['wins']++;
        }
        $result = [];
        foreach ($groups as $sym => $g) {
            $result[] = [
                'instrument' => $sym,
                'trades'     => $g['trades'],
                'total_pnl'  => round($g['pnl'], 2),
                'win_rate'   => round($g['wins'] / $g['trades'] * 100, 1),
            ];
        }
        usort($result, fn($a, $b) => $b['total_pnl'] <=> $a['total_pnl']);
        return array_slice($result, 0, 10);
    }

    public function rMultipleDistribution(array $rmults): array
    {
        if (empty($rmults)) return [];
        $buckets = [
            'below_neg2' => 0, 'neg2_neg1' => 0, 'neg1_zero' => 0,
            'zero_1' => 0, '1_2' => 0, '2_3' => 0, 'above_3' => 0
        ];
        foreach ($rmults as $r) {
            match(true) {
                $r < -2    => $buckets['below_neg2']++,
                $r < -1    => $buckets['neg2_neg1']++,
                $r < 0     => $buckets['neg1_zero']++,
                $r < 1     => $buckets['zero_1']++,
                $r < 2     => $buckets['1_2']++,
                $r < 3     => $buckets['2_3']++,
                default    => $buckets['above_3']++,
            };
        }
        return [
            'buckets' => $buckets,
            'labels'  => ['<-2R', '-2 to -1R', '-1 to 0R', '0 to 1R', '1 to 2R', '2 to 3R', '>3R'],
            'values'  => array_values($buckets),
            'avg_r'   => round(array_sum($rmults) / count($rmults), 3),
        ];
    }

    private function rollingMetrics(array $equityCurve): array
    {
        $window  = 21; // ~1 month trading days
        $results = [];
        for ($i = $window; $i < count($equityCurve); $i++) {
            $slice   = array_slice($equityCurve, $i - $window, $window);
            $returns = $this->dailyReturns($slice);
            $results[] = [
                'date'   => $equityCurve[$i]['date'],
                'sharpe' => round($this->sharpeRatio($returns), 2),
            ];
        }
        return $results;
    }

    private function periodWhere(string $period): string
    {
        return $this->periodWhereDate('trade_date', $period);
    }

    private function periodWhereDate(string $col, string $period): string
    {
        return match($period) {
            'TODAY'   => "AND {$col} = DATE('now')",
            'WEEK'    => "AND {$col} >= DATE('now', 'weekday 1', '-7 days')",
            'MONTH'   => "AND {$col} >= DATE('now', 'start of month')",
            'QUARTER' => "AND {$col} >= DATE('now', '-90 days')",
            'YTD'     => "AND {$col} >= DATE('now', 'start of year')",
            default   => '',
        };
    }
}
