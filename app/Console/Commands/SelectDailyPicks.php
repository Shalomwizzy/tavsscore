<?php

namespace App\Console\Commands;

use App\Models\Prediction;
use App\Services\PredictionService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SelectDailyPicks extends Command
{
    protected $signature = 'picks:select {--force : Re-select even if picks exist today}';

    protected $description = 'Score today\'s AI predictions and mark the top 3 as daily picks, plus Draw and GG picks.';

    public function handle(PredictionService $service): int
    {
        $today  = CarbonImmutable::now(config('app.timezone'));
        $cutoff = $today->endOfDay();

        // ── Daily picks ────────────────────────────────────────────
        if (! $this->option('force')) {
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
        $this->info('Selecting Draw picks…');
        $drawPicks = $service->selectDrawPicks();
        if ($drawPicks->isEmpty()) {
            $this->warn('No qualifying Draw picks today.');
        } else {
            foreach ($drawPicks as $p) {
                $match = $p->match;
                $this->line(sprintf(
                    '  🤝 Draw #%d  %s vs %s  (%s%%)',
                    $p->draw_rank,
                    $match?->home_team ?? '?',
                    $match?->away_team ?? '?',
                    $p->confidence,
                ));
            }
            $this->info("✅ {$drawPicks->count()} Draw picks selected.");
        }

        // ── GG picks ───────────────────────────────────────────────
        $this->info('Selecting GG picks…');
        $ggPicks = $service->selectGGPicks();
        if ($ggPicks->isEmpty()) {
            $this->warn('No qualifying GG picks today.');
        } else {
            foreach ($ggPicks as $p) {
                $match = $p->match;
                $this->line(sprintf(
                    '  ⚽ GG #%d  %s vs %s  (%s%%)',
                    $p->gg_rank,
                    $match?->home_team ?? '?',
                    $match?->away_team ?? '?',
                    $p->confidence,
                ));
            }
            $this->info("✅ {$ggPicks->count()} GG picks selected.");
        }

        // ── Over 1.5 picks ─────────────────────────────────────────
        $this->info('Selecting Over 1.5 picks…');
        $over15Picks = $service->selectOver15Picks();
        if ($over15Picks->isEmpty()) {
            $this->warn('No qualifying Over 1.5 picks today.');
        } else {
            foreach ($over15Picks as $p) {
                $match = $p->match;
                $this->line(sprintf(
                    '  ⚽ O1.5 #%d  %s vs %s  (%s%% likely)',
                    $p->over15_rank,
                    $match?->home_team ?? '?',
                    $match?->away_team ?? '?',
                    round($p->over_15_prob ?? 0),
                ));
            }
            $this->info("✅ {$over15Picks->count()} Over 1.5 picks selected.");
        }

        // ── Over 2.5 picks ─────────────────────────────────────────
        $this->info('Selecting Over 2.5 picks…');
        $over25Picks = $service->selectOver25Picks();
        if ($over25Picks->isEmpty()) {
            $this->warn('No qualifying Over 2.5 picks today.');
        } else {
            foreach ($over25Picks as $p) {
                $match = $p->match;
                $this->line(sprintf(
                    '  🔥 O2.5 #%d  %s vs %s  (%s%% likely)',
                    $p->over25_rank,
                    $match?->home_team ?? '?',
                    $match?->away_team ?? '?',
                    round($p->over_25_prob ?? 0),
                ));
            }
            $this->info("✅ {$over25Picks->count()} Over 2.5 picks selected.");
        }

        // ── Team 3+ picks ──────────────────────────────────────────
        $this->info('Selecting Team 3+ picks…');
        $team3Picks = $service->selectTeam3PlusPicks();
        if ($team3Picks->isEmpty()) {
            $this->warn('No qualifying Team 3+ picks today.');
        } else {
            foreach ($team3Picks as $p) {
                $match = $p->match;
                $prob  = $p->team3plus_label === 'Home' ? $p->home_3plus_prob : $p->away_3plus_prob;
                $this->line(sprintf(
                    '  🎯 3+ #%d  %s vs %s  (%s: %s%% probability)',
                    $p->team3plus_rank,
                    $match?->home_team ?? '?',
                    $match?->away_team ?? '?',
                    $p->team3plus_label,
                    round($prob ?? 0),
                ));
            }
            $this->info("✅ {$team3Picks->count()} Team 3+ picks selected.");
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

        $this->info("✅ {$picks->count()} daily picks selected. Notification will fire at 08:00 Lagos.");
    }
}
