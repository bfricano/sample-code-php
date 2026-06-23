<?php
declare(strict_types=1);

namespace Trader\Src;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;
    private static string $dbPath = __DIR__ . '/../data/trader.db';

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            try {
                self::$instance = new PDO('sqlite:' . self::$dbPath, null, null, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
                self::$instance->exec('PRAGMA journal_mode=WAL');
                self::$instance->exec('PRAGMA foreign_keys=ON');
                self::bootstrap(self::$instance);
            } catch (PDOException $e) {
                throw new \RuntimeException('Database connection failed: ' . $e->getMessage());
            }
        }
        return self::$instance;
    }

    private static function bootstrap(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS traders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL DEFAULT 'Trader',
                account_size REAL DEFAULT 100000,
                risk_per_trade REAL DEFAULT 1.0,
                max_daily_loss REAL DEFAULT 3.0,
                max_weekly_loss REAL DEFAULT 6.0,
                max_monthly_loss REAL DEFAULT 10.0,
                min_resilience_score INTEGER DEFAULT 65,
                benchmark TEXT DEFAULT 'SPY',
                timezone TEXT DEFAULT 'America/New_York',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS trades (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                trader_id INTEGER DEFAULT 1,
                trade_date DATE NOT NULL,
                instrument TEXT NOT NULL,
                direction TEXT CHECK(direction IN ('LONG','SHORT')),
                setup_type TEXT DEFAULT 'Other',
                entry_price REAL NOT NULL,
                exit_price REAL,
                stop_loss REAL,
                take_profit REAL,
                position_size REAL NOT NULL DEFAULT 1,
                pnl REAL DEFAULT 0,
                pnl_percent REAL DEFAULT 0,
                risk_amount REAL DEFAULT 0,
                r_multiple REAL,
                mae REAL DEFAULT 0,
                mfe REAL DEFAULT 0,
                duration_minutes INTEGER DEFAULT 0,
                execution_score INTEGER DEFAULT 5 CHECK(execution_score BETWEEN 1 AND 10),
                rule_violations TEXT DEFAULT '[]',
                tags TEXT DEFAULT '[]',
                notes TEXT DEFAULT '',
                status TEXT DEFAULT 'CLOSED' CHECK(status IN ('OPEN','CLOSED','CANCELLED')),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (trader_id) REFERENCES traders(id)
            );

            CREATE TABLE IF NOT EXISTS checkins (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                trader_id INTEGER DEFAULT 1,
                checkin_date DATE NOT NULL,
                checkin_type TEXT DEFAULT 'PRE_MARKET' CHECK(checkin_type IN ('PRE_MARKET','POST_MARKET','MID_SESSION')),
                sleep_quality INTEGER DEFAULT 7 CHECK(sleep_quality BETWEEN 1 AND 10),
                emotional_state INTEGER DEFAULT 7 CHECK(emotional_state BETWEEN 1 AND 10),
                focus_level INTEGER DEFAULT 7 CHECK(focus_level BETWEEN 1 AND 10),
                stress_level INTEGER DEFAULT 3 CHECK(stress_level BETWEEN 1 AND 10),
                physical_energy INTEGER DEFAULT 7 CHECK(physical_energy BETWEEN 1 AND 10),
                confidence_level INTEGER DEFAULT 7 CHECK(confidence_level BETWEEN 1 AND 10),
                market_bias TEXT DEFAULT 'NEUTRAL',
                planned_max_trades INTEGER DEFAULT 3,
                key_levels TEXT DEFAULT '[]',
                game_plan TEXT DEFAULT '',
                resilience_score REAL DEFAULT 0,
                notes TEXT DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (trader_id) REFERENCES traders(id)
            );

            CREATE TABLE IF NOT EXISTS daily_performance (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                trader_id INTEGER DEFAULT 1,
                perf_date DATE NOT NULL,
                gross_pnl REAL DEFAULT 0,
                net_pnl REAL DEFAULT 0,
                trade_count INTEGER DEFAULT 0,
                win_count INTEGER DEFAULT 0,
                loss_count INTEGER DEFAULT 0,
                largest_win REAL DEFAULT 0,
                largest_loss REAL DEFAULT 0,
                equity_end REAL DEFAULT 0,
                drawdown_percent REAL DEFAULT 0,
                resilience_avg REAL DEFAULT 0,
                rule_violations INTEGER DEFAULT 0,
                UNIQUE(trader_id, perf_date),
                FOREIGN KEY (trader_id) REFERENCES traders(id)
            );

            CREATE TABLE IF NOT EXISTS risk_rules (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                trader_id INTEGER DEFAULT 1,
                rule_name TEXT NOT NULL,
                rule_type TEXT DEFAULT 'SOFT_ALERT' CHECK(rule_type IN ('HARD_STOP','SOFT_ALERT','SIZING_RULE')),
                condition_type TEXT DEFAULT 'DAILY_LOSS',
                threshold_value REAL DEFAULT 0,
                action TEXT DEFAULT 'ALERT',
                is_active INTEGER DEFAULT 1,
                trigger_count INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (trader_id) REFERENCES traders(id)
            );

            CREATE TABLE IF NOT EXISTS alerts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                trader_id INTEGER DEFAULT 1,
                alert_type TEXT DEFAULT 'SYSTEM',
                severity TEXT DEFAULT 'INFO' CHECK(severity IN ('INFO','WARNING','CRITICAL')),
                title TEXT NOT NULL,
                message TEXT DEFAULT '',
                is_read INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (trader_id) REFERENCES traders(id)
            );

            CREATE TABLE IF NOT EXISTS behavioral_patterns (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                trader_id INTEGER DEFAULT 1,
                pattern_date DATE NOT NULL,
                pattern_type TEXT NOT NULL,
                severity INTEGER DEFAULT 1 CHECK(severity BETWEEN 1 AND 5),
                trigger_data TEXT DEFAULT '{}',
                resolved INTEGER DEFAULT 0,
                FOREIGN KEY (trader_id) REFERENCES traders(id)
            );
        ");

        // Seed default trader if none exists
        $count = $pdo->query('SELECT COUNT(*) FROM traders')->fetchColumn();
        if ((int)$count === 0) {
            $pdo->exec("INSERT INTO traders (name, account_size) VALUES ('Elite Trader', 100000)");
            self::seedDemoData($pdo);
        }
    }

    private static function seedDemoData(PDO $pdo): void
    {
        $setups    = ['Breakout', 'Pullback', 'Reversal', 'Momentum', 'Gap Fill', 'VWAP Reclaim'];
        $tickers   = ['SPY', 'QQQ', 'AAPL', 'TSLA', 'NVDA', 'AMD', 'MSFT', 'META', 'AMZN', 'GOOGL'];
        $equity    = 100000.0;
        $startDate = new \DateTime('2026-01-02');
        $today     = new \DateTime();

        $date = clone $startDate;
        while ($date <= $today) {
            $dow = (int)$date->format('N');
            if ($dow >= 6) { $date->modify('+1 day'); continue; }

            // Pre-market check-in
            $sleep    = mt_rand(5, 10);
            $emotion  = mt_rand(5, 10);
            $focus    = mt_rand(5, 10);
            $stress   = mt_rand(1, 6);
            $energy   = mt_rand(5, 10);
            $conf     = mt_rand(5, 10);
            $resScore = self::computeResilience($sleep, $emotion, $focus, $stress, $energy, $conf);

            $stmt = $pdo->prepare("INSERT INTO checkins
                (trader_id, checkin_date, checkin_type, sleep_quality, emotional_state, focus_level,
                 stress_level, physical_energy, confidence_level, resilience_score, market_bias)
                VALUES (1, ?, 'PRE_MARKET', ?, ?, ?, ?, ?, ?, ?, ?)");
            $biases = ['BULLISH', 'BEARISH', 'NEUTRAL'];
            $stmt->execute([
                $date->format('Y-m-d'), $sleep, $emotion, $focus, $stress, $energy, $conf,
                $resScore, $biases[array_rand($biases)]
            ]);

            // Trades
            $tradeCount   = mt_rand(1, 4);
            $dailyPnl     = 0.0;
            $wins         = 0;
            $losses       = 0;
            $largestWin   = 0.0;
            $largestLoss  = 0.0;

            for ($t = 0; $t < $tradeCount; $t++) {
                $ticker    = $tickers[array_rand($tickers)];
                $setup     = $setups[array_rand($setups)];
                $dir       = (mt_rand(0, 1) ? 'LONG' : 'SHORT');
                $entry     = round(mt_rand(100, 500) + mt_rand(0, 99) / 100, 2);
                $riskPct   = ($resScore >= 70 ? 1.0 : 0.5);
                $riskAmt   = $equity * $riskPct / 100;
                $stopDist  = round($entry * 0.01, 2); // 1% stop
                $size      = round($riskAmt / $stopDist, 2);
                $stop      = $dir === 'LONG' ? round($entry - $stopDist, 2) : round($entry + $stopDist, 2);

                // Outcome skewed by resilience
                $winProb = 0.45 + ($resScore / 1000);
                $win     = (mt_rand(0, 100) / 100) < $winProb;

                if ($win) {
                    $rMult = round(mt_rand(10, 35) / 10, 2); // 1.0-3.5R
                    $exit  = $dir === 'LONG'
                        ? round($entry + $stopDist * $rMult, 2)
                        : round($entry - $stopDist * $rMult, 2);
                    $pnl   = round(($exit - $entry) * $size * ($dir === 'LONG' ? 1 : -1), 2);
                    $wins++;
                    if ($pnl > $largestWin) $largestWin = $pnl;
                } else {
                    $rMult = round(-(mt_rand(5, 12) / 10), 2); // -0.5 to -1.2R
                    $exit  = $dir === 'LONG'
                        ? round($entry + $stopDist * $rMult, 2)
                        : round($entry - $stopDist * $rMult, 2);
                    $pnl   = round(($exit - $entry) * $size * ($dir === 'LONG' ? 1 : -1), 2);
                    $losses++;
                    if ($pnl < $largestLoss) $largestLoss = $pnl;
                }

                $pnlPct = round($pnl / $equity * 100, 4);
                $dailyPnl += $pnl;

                $stmt = $pdo->prepare("INSERT INTO trades
                    (trader_id, trade_date, instrument, direction, setup_type, entry_price, exit_price,
                     stop_loss, position_size, pnl, pnl_percent, risk_amount, r_multiple,
                     mae, mfe, duration_minutes, execution_score, status)
                    VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'CLOSED')");
                $stmt->execute([
                    $date->format('Y-m-d'), $ticker, $dir, $setup, $entry, $exit, $stop, $size,
                    $pnl, $pnlPct, $riskAmt, $rMult,
                    round(abs($stopDist) * mt_rand(50, 120) / 100, 2),
                    round(abs($stopDist) * mt_rand(100, 300) / 100, 2),
                    mt_rand(5, 240),
                    mt_rand(5, 10)
                ]);
            }

            $equity += $dailyPnl;
            $stmt = $pdo->prepare("INSERT OR REPLACE INTO daily_performance
                (trader_id, perf_date, gross_pnl, net_pnl, trade_count, win_count, loss_count,
                 largest_win, largest_loss, equity_end, resilience_avg)
                VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $date->format('Y-m-d'), $dailyPnl, $dailyPnl, $tradeCount,
                $wins, $losses, $largestWin, $largestLoss, $equity, $resScore
            ]);

            $date->modify('+1 day');
        }

        // Default risk rules
        $rules = [
            ['Daily Loss Limit',      'HARD_STOP',   'DAILY_LOSS_PCT',   3.0,  'HALT_TRADING'],
            ['Weekly Loss Limit',     'HARD_STOP',   'WEEKLY_LOSS_PCT',  6.0,  'HALT_TRADING'],
            ['Max Daily Trades',      'SOFT_ALERT',  'DAILY_TRADE_COUNT', 5.0, 'ALERT'],
            ['Max Position Size',     'SIZING_RULE', 'POSITION_RISK_PCT', 2.0, 'REDUCE_SIZE'],
            ['Low Resilience Gate',   'HARD_STOP',   'RESILIENCE_SCORE', 60.0, 'HALT_TRADING'],
            ['Revenge Trade Alert',   'SOFT_ALERT',  'CONSECUTIVE_LOSS',  3.0, 'ALERT'],
            ['Portfolio Heat Limit',  'SOFT_ALERT',  'PORTFOLIO_HEAT',    5.0, 'ALERT'],
        ];
        $stmt = $pdo->prepare("INSERT INTO risk_rules (trader_id, rule_name, rule_type, condition_type, threshold_value, action) VALUES (1, ?, ?, ?, ?, ?)");
        foreach ($rules as $r) {
            $stmt->execute($r);
        }
    }

    private static function computeResilience(int $sleep, int $emotion, int $focus, int $stress, int $energy, int $conf): float
    {
        $score = ($sleep * 1.5) + ($emotion * 1.5) + ($focus * 2.0) + ((10 - $stress) * 1.5) + ($energy * 1.0) + ($conf * 1.5);
        return round(min(100, $score), 1);
    }
}
