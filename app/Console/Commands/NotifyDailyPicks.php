<?php

namespace App\Console\Commands;

use App\Models\Prediction;
use App\Services\OneSignalService;
use App\Services\TelegramService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class NotifyDailyPicks extends Command
{
    protected $signature = 'picks:notify';

    protected $description = 'Send push notification and Telegram post with today\'s daily picks, draw picks, and GG picks at 08:00 Lagos.';

    public function handle(OneSignalService $oneSignal, TelegramService $telegram): int
    {
        $tz     = 'Africa/Lagos';
        $today  = CarbonImmutable::now($tz)->startOfDay();
        $cutoff = CarbonImmutable::now($tz)->endOfDay();

        // ── Daily picks ────────────────────────────────────────────
        $picks = Prediction::query()
            ->with('match')
            ->where('is_daily_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->orderBy('pick_rank')
            ->get();

        if ($picks->isNotEmpty()) {
            $lines = $picks->map(function ($p) {
                $match = $p->match;
                if (! $match) return null;
                $conf = $p->confidence ? " ({$p->confidence}%)" : '';
                return "{$match->home_team} vs {$match->away_team}: {$p->predicted_outcome}{$conf}";
            })->filter()->values();

            $oneSignal->sendMatchAlert(
                title:   '🎯 Today\'s Daily Picks Are Live!',
                message: $lines->implode(' | ') . ' - Tap for full analysis',
                path:    '/picks',
            );

            $telegramPicks = $picks->map(function ($p) {
                $match = $p->match;
                if (! $match) return null;
                return [
                    'match'      => "{$match->home_team} vs {$match->away_team}",
                    'league'     => $match->league ?? '',
                    'tip'        => $p->predicted_outcome ?? '',
                    'confidence' => $p->confidence ?? '',
                ];
            })->filter()->values()->toArray();

            $telegram->sendDailyPicks($telegramPicks, config('app.url'));
        } else {
            $this->warn('No daily picks found for today — daily notification skipped.');
        }

        // ── Draw picks ─────────────────────────────────────────────
        $drawPicks = Prediction::query()
            ->with('match')
            ->where('is_draw_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->orderBy('draw_rank')
            ->get();

        if ($drawPicks->isNotEmpty()) {
            $drawLines = $drawPicks->map(function ($p) {
                $match = $p->match;
                if (! $match) return null;
                $conf = $p->confidence ? " ({$p->confidence}%)" : '';
                return "{$match->home_team} vs {$match->away_team}: Draw{$conf}";
            })->filter()->values();

            $oneSignal->sendMatchAlert(
                title:   '🤝 Today\'s Draw Picks — Triple AI Agreed!',
                message: $drawLines->implode(' | ') . ' - Tap for analysis',
                path:    '/draw-picks',
            );

            $telegramDrawPicks = $drawPicks->map(function ($p) {
                $match = $p->match;
                if (! $match) return null;
                return [
                    'match'      => "{$match->home_team} vs {$match->away_team}",
                    'league'     => $match->league ?? '',
                    'confidence' => $p->confidence ?? '',
                ];
            })->filter()->values()->toArray();

            $telegram->sendDrawPicks($telegramDrawPicks, config('app.url'));
        } else {
            $this->warn('No draw picks found for today — draw notification skipped.');
        }

        // ── GG picks ───────────────────────────────────────────────
        $ggPicks = Prediction::query()
            ->with('match')
            ->where('is_gg_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->orderBy('gg_rank')
            ->get();

        if ($ggPicks->isNotEmpty()) {
            $ggLines = $ggPicks->map(function ($p) {
                $match = $p->match;
                if (! $match) return null;
                $conf = $p->confidence ? " ({$p->confidence}%)" : '';
                return "{$match->home_team} vs {$match->away_team}: GG{$conf}";
            })->filter()->values();

            $oneSignal->sendMatchAlert(
                title:   '⚽ Today\'s GG Picks — Both Teams to Score!',
                message: $ggLines->implode(' | ') . ' - Tap for analysis',
                path:    '/gg-picks',
            );

            $telegramGGPicks = $ggPicks->map(function ($p) {
                $match = $p->match;
                if (! $match) return null;
                return [
                    'match'      => "{$match->home_team} vs {$match->away_team}",
                    'league'     => $match->league ?? '',
                    'confidence' => $p->confidence ?? '',
                ];
            })->filter()->values()->toArray();

            $telegram->sendGGPicks($telegramGGPicks, config('app.url'));
        } else {
            $this->warn('No GG picks found for today — GG notification skipped.');
        }

        // ── Correct score predictions ──────────────────────────────
        $scorePredictions = Prediction::query()
            ->with('match')
            ->whereNotNull('likely_scores')
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->orderByDesc('confidence')
            ->limit(10)
            ->get()
            ->filter(fn ($p) => ! empty($p->likely_scores))
            ->map(function ($p) {
                $match = $p->match;
                if (! $match) return null;
                return [
                    'match'  => "{$match->home_team} vs {$match->away_team}",
                    'scores' => array_slice(is_array($p->likely_scores) ? $p->likely_scores : [], 0, 4),
                ];
            })->filter()->values()->toArray();

        if (! empty($scorePredictions)) {
            $telegram->sendCorrectScores($scorePredictions, config('app.url'));
        }

        $this->info(sprintf(
            'Sent %d picks, %d draw picks, %d GG picks + correct scores.',
            $picks->count(),
            $drawPicks->count(),
            $ggPicks->count(),
        ));

        return self::SUCCESS;
    }
}
