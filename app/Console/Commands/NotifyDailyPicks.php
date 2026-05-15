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

    protected $description = 'Send push notification and Telegram post with today\'s daily picks at 08:00 Lagos.';

    public function handle(OneSignalService $oneSignal, TelegramService $telegram): int
    {
        $tz     = 'Africa/Lagos';
        $today  = CarbonImmutable::now($tz)->startOfDay();
        $cutoff = CarbonImmutable::now($tz)->endOfDay();

        $picks = Prediction::query()
            ->with('match')
            ->where('is_daily_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->orderBy('pick_rank')
            ->get();

        if ($picks->isEmpty()) {
            $this->warn('No daily picks found for today - notification skipped.');
            return self::SUCCESS;
        }

        $lines = $picks->map(function ($p) {
            $match = $p->match;
            if (! $match) return null;
            $conf = $p->confidence ? " ({$p->confidence}%)" : '';
            return "{$match->home_team} vs {$match->away_team}: {$p->predicted_outcome}{$conf}";
        })->filter()->values();

        // OneSignal push
        $oneSignal->sendMatchAlert(
            title:   '🎯 Today\'s Daily Picks Are Live!',
            message: $lines->implode(' | ') . ' - Tap for full analysis',
            path:    '/picks',
        );

        // Telegram - daily picks
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

        // Telegram - correct score predictions for all today's matches
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

        $this->info("Sent {$picks->count()} picks + correct scores via OneSignal and Telegram.");

        return self::SUCCESS;
    }
}
