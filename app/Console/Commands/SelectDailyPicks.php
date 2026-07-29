<?php

namespace App\Console\Commands;

use App\Models\Prediction;
use App\Services\PredictionService;
use App\Support\SpecialtyPickCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SelectDailyPicks extends Command
{
    protected $signature = 'picks:select {--force : Re-select even if picks exist today}';

    protected $description = 'Score today\'s AI predictions and mark the top 3 as daily picks, plus Draw and GG picks.';

    public function handle(PredictionService $service): int
    {
        $today  = CarbonImmutable::now(config('app.timezone'));
        $cutoff = $today->endOfDay();
        $force  = $this->option('force');
        $date   = now('Africa/Lagos')->toDateString();

        // ── Daily picks ────────────────────────────────────────────
        if (! $force && Cache::has("picks_sent_daily_{$date}")) {
            $this->info('Daily picks already notified — skipping re-selection.');
        } elseif (! $force) {
            $existing = Prediction::query()
                ->where('is_daily_pick', true)
                ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [
                    $today->startOfDay(),
                    $cutoff,
                ]))
                ->count();

            if ($existing >= 3) {
                $this->info("Daily picks already selected ({$existing} picks). Use --force to re-run.");
            } else {
                $this->runDailyPicks($service);
            }
        } else {
            $this->runDailyPicks($service);
        }

        // ── Draw picks ─────────────────────────────────────────────
        if (! $force && Cache::has("picks_sent_draw_{$date}")) {
            $this->info('Draw picks already notified — skipping re-selection.');
        } else {
            $this->info('Selecting Draw picks…');
            $drawPicks = $service->selectDrawPicks();
            if ($drawPicks->isEmpty()) {
                $this->warn('No qualifying Draw picks today.');
            } else {
                foreach ($drawPicks as $p) {
                    $match = $p->match;
                    $this->line(sprintf('  🤝 Draw #%d  %s vs %s  (%s%%)', $p->draw_rank, $match?->home_team ?? '?', $match?->away_team ?? '?', $p->confidence));
                }
                $this->info("✅ {$drawPicks->count()} Draw picks selected.");
            }
        }

        // ── GG picks ───────────────────────────────────────────────
        if (! $force && Cache::has("picks_sent_gg_{$date}")) {
            $this->info('GG picks already notified — skipping re-selection.');
        } else {
            $this->info('Selecting GG picks…');
            $ggPicks = $service->selectGGPicks();
            if ($ggPicks->isEmpty()) {
                $this->warn('No qualifying GG picks today.');
            } else {
                foreach ($ggPicks as $p) {
                    $match = $p->match;
                    $this->line(sprintf('  ⚽ GG #%d  %s vs %s  (%s%%)', $p->gg_rank, $match?->home_team ?? '?', $match?->away_team ?? '?', $p->confidence));
                }
                $this->info("✅ {$ggPicks->count()} GG picks selected.");
            }
        }

        // ── Over 1.5 picks ─────────────────────────────────────────
        if (! $force && Cache::has("picks_sent_over15_{$date}")) {
            $this->info('Over 1.5 picks already notified — skipping re-selection.');
        } else {
            $this->info('Selecting Over 1.5 picks…');
            $over15Picks = $service->selectOver15Picks();
            if ($over15Picks->isEmpty()) {
                $this->warn('No qualifying Over 1.5 picks today.');
            } else {
                foreach ($over15Picks as $p) {
                    $match = $p->match;
                    $this->line(sprintf('  ⚽ O1.5 #%d  %s vs %s  (%s%% likely)', $p->over15_rank, $match?->home_team ?? '?', $match?->away_team ?? '?', round($p->over_15_prob ?? 0)));
                }
                $this->info("✅ {$over15Picks->count()} Over 1.5 picks selected.");
            }
        }

        // ── Over 2.5 picks ─────────────────────────────────────────
        if (! $force && Cache::has("picks_sent_over25_{$date}")) {
            $this->info('Over 2.5 picks already notified — skipping re-selection.');
        } else {
            $this->info('Selecting Over 2.5 picks…');
            $over25Picks = $service->selectOver25Picks();
            if ($over25Picks->isEmpty()) {
                $this->warn('No qualifying Over 2.5 picks today.');
            } else {
                foreach ($over25Picks as $p) {
                    $match = $p->match;
                    $this->line(sprintf('  🔥 O2.5 #%d  %s vs %s  (%s%% likely)', $p->over25_rank, $match?->home_team ?? '?', $match?->away_team ?? '?', round($p->over_25_prob ?? 0)));
                }
                $this->info("✅ {$over25Picks->count()} Over 2.5 picks selected.");
            }
        }

        // ── Specialist under-goals and Asian Handicap picks ─────────
        foreach (['under35', 'under45', 'handicap', 'europeanhandicap'] as $type) {
            if (! $force && Cache::has("picks_sent_{$type}_{$date}")) {
                $this->info("{$type} picks already notified — skipping re-selection.");
                continue;
            }
            $this->info("Selecting {$type} picks…");
            $picks = $service->selectSpecialtyMarketPicks($type);
            if ($picks->isEmpty()) {
                $this->warn("No qualifying {$type} picks today.");
                continue;
            }
            foreach ($picks as $pick) {
                $match = $pick->match;
                $config = SpecialtyPickCatalog::get($type);
                $label = $type === 'handicap' ? $pick->handicap_label : ($type === 'europeanhandicap' ? $pick->european_handicap_label : ($type === 'under35' ? 'Under 3.5 Goals' : 'Under 4.5 Goals'));
                $probability = (float) ((is_array($pick->market_board) ? $pick->market_board : [])[$label] ?? 0);
                $this->line(sprintf('  %s #%d  %s vs %s  (%s, %s%%)', in_array($type, ['handicap', 'europeanhandicap'], true) ? '🛡️' : '🧊', $pick->{$config['rank']}, $match?->home_team ?? '?', $match?->away_team ?? '?', $label, round($probability)));
            }
            $this->info("✅ {$picks->count()} {$type} picks selected.");
        }

        // ── Corner picks ───────────────────────────────────────────
        if (! $force && Cache::has("picks_sent_corners_{$date}")) {
            $this->info('Corner picks already notified — skipping re-selection.');
        } else {
            $this->info('Selecting Corner picks…');
            $cornerPicks = $service->selectCornersPicks();
            if ($cornerPicks->isEmpty()) {
                $this->warn('No qualifying Corner picks today.');
            } else {
                foreach ($cornerPicks as $p) {
                    $match = $p->match;
                    $this->line(sprintf('  🚩 Corners #%d  %s vs %s  (%s)', $p->corners_rank, $match?->home_team ?? '?', $match?->away_team ?? '?', $p->corners_label));
                }
                $this->info("✅ {$cornerPicks->count()} Corner picks selected.");
            }
        }

        // ── Team 3+ picks ──────────────────────────────────────────
        if (! $force && Cache::has("picks_sent_team3plus_{$date}")) {
            $this->info('Team 3+ picks already notified — skipping re-selection.');
        } else {
            $this->info('Selecting Team 3+ picks…');
            $team3Picks = $service->selectTeam3PlusPicks();
            if ($team3Picks->isEmpty()) {
                $this->warn('No qualifying Team 3+ picks today.');
            } else {
                foreach ($team3Picks as $p) {
                    $match  = $p->match;
                    $label  = $p->team3plus_label ?? 'Home 3+';
                    $isHome = str_starts_with($label, 'Home');
                    $market = str_ends_with($label, '2+') ? '2+' : '3+';
                    $prob   = $market === '2+'
                        ? ($isHome ? $p->home_2plus_prob : $p->away_2plus_prob)
                        : ($isHome ? $p->home_3plus_prob : $p->away_3plus_prob);
                    $this->line(sprintf('  🎯 3+ #%d  %s vs %s  (%s: %s%% probability)', $p->team3plus_rank, $match?->home_team ?? '?', $match?->away_team ?? '?', $label, round($prob ?? 0)));
                }
                $this->info("✅ {$team3Picks->count()} Team 3+ picks selected.");
            }
        }

        // ── Double Chance picks ────────────────────────────────────
        if (! $force && Cache::has("picks_sent_doublechance_{$date}")) {
            $this->info('Double Chance picks already notified — skipping re-selection.');
        } else {
            $this->info('Selecting Double Chance picks…');
            $dcPicks = $service->selectDoubleChancePicks();
            if ($dcPicks->isEmpty()) {
                $this->warn('No qualifying Double Chance picks today.');
            } else {
                foreach ($dcPicks as $p) {
                    $match = $p->match;
                    $this->line(sprintf('  🎯 DC #%d  %s vs %s  (%s)', $p->double_chance_rank, $match?->home_team ?? '?', $match?->away_team ?? '?', $p->double_chance_label));
                }
                $this->info("✅ {$dcPicks->count()} Double Chance picks selected.");
            }
        }

        // ── Correct Score picks ────────────────────────────────────
        if (! $force && Cache::has("picks_sent_correctscore_{$date}")) {
            $this->info('Correct Score picks already notified — skipping re-selection.');
            return self::SUCCESS;
        }
        $this->info('Selecting Correct Score picks…');
        $csPicks = $service->selectCorrectScorePicks();
        if ($csPicks->isEmpty()) {
            $this->warn('No qualifying Correct Score picks today.');
        } else {
            foreach ($csPicks as $p) {
                $match  = $p->match;
                $top    = $p->likely_scores[0]['score'] ?? '?';
                $this->line(sprintf('  🎯 CS #%d  %s vs %s  (top score: %s, conf: %s%%)', $p->correct_score_rank, $match?->home_team ?? '?', $match?->away_team ?? '?', $top, $p->confidence));
            }
            $this->info("✅ {$csPicks->count()} Correct Score picks selected.");
        }

        return self::SUCCESS;
    }

    private function runDailyPicks(PredictionService $service): void
    {
        $this->info('Selecting daily picks…');

        $picks = $service->selectDailyPicks();

        if ($picks->isEmpty()) {
            $this->warn('No qualifying predictions found. Run predictions:generate first.');
            return;
        }

        foreach ($picks as $p) {
            $match = $p->match;
            $label = $p->pick_rank === 1 ? '👑 Pick of the Day' : "Pick #{$p->pick_rank}";
            $this->line(sprintf(
                '  #%d  %s vs %s  →  %s  (%s)',
                $p->pick_rank,
                $match?->home_team ?? '?',
                $match?->away_team ?? '?',
                $p->predicted_outcome,
                $label
            ));
        }

        $this->info("✅ {$picks->count()} daily picks selected. Notification will fire at 03:30 Lagos.");
    }
}
