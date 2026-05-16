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
