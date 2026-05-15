<?php

namespace App\Console\Commands;

use App\Models\Prediction;
use App\Services\TelegramService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SendDailyResults extends Command
{
    protected $signature = 'results:send-telegram';

    protected $description = 'Post today\'s resolved pick results to Telegram at 23:00 Lagos.';

    public function handle(TelegramService $telegram): int
    {
        $tz     = 'Africa/Lagos';
        $today  = CarbonImmutable::now($tz)->startOfDay();
        $cutoff = CarbonImmutable::now($tz)->endOfDay();

        $picks = Prediction::query()
            ->with('match')
            ->where('is_daily_pick', true)
            ->whereNotNull('was_correct')
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->orderBy('pick_rank')
            ->get();

        if ($picks->isEmpty()) {
            $this->warn('No resolved picks found for today - results skipped.');
            return self::SUCCESS;
        }

        $results = $picks->map(function ($p) {
            $match = $p->match;
            if (! $match) return null;

            $score = in_array($match->status, ['FT', 'AET', 'PEN'])
                ? "{$match->home_score}-{$match->away_score}"
                : null;

            return [
                'match'      => "{$match->home_team} vs {$match->away_team}",
                'tip'        => $p->predicted_outcome ?? '',
                'confidence' => $p->confidence ?? '',
                'correct'    => $p->was_correct,
                'score'      => $score,
            ];
        })->filter()->values()->toArray();

        if (empty($results)) {
            $this->warn('All picks had no associated match data - results skipped.');
            return self::SUCCESS;
        }

        $telegram->sendDailyResults($results, config('app.url'));

        $correct = collect($results)->where('correct', true)->count();
        $total   = count($results);
        $this->info("Results sent: {$correct}/{$total} correct.");

        return self::SUCCESS;
    }
}
