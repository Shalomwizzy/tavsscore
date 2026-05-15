<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Services\OneSignalService;
use App\Services\PredictionService;
use App\Services\TelegramService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class UpdateLineupPredictions extends Command
{
    protected $signature = 'picks:update-lineups';

    protected $description = 'Re-run AI prediction for ALL today\'s matches the moment their confirmed lineup is available.';

    public function handle(PredictionService $predictionService, OneSignalService $oneSignal, TelegramService $telegram): int
    {
        $tz     = 'Africa/Lagos';
        $today  = CarbonImmutable::now($tz)->startOfDay();
        $cutoff = CarbonImmutable::now($tz)->endOfDay();

        $matches = FootballMatch::query()
            ->whereBetween('match_time', [$today, $cutoff])
            ->whereNotIn('status', ['FT', 'AET', 'PEN', 'CANC', 'PST'])
            ->orderBy('match_time')
            ->get();

        if ($matches->isEmpty()) {
            return self::SUCCESS;
        }

        $updated     = [];
        $telegramPicks = [];

        foreach ($matches as $match) {
            $wasUpdated = $predictionService->regenerateWithLineup($match);

            if ($wasUpdated) {
                $prediction = $match->prediction;
                $outcome    = $prediction?->predicted_outcome ?? '-';
                $conf       = $prediction?->confidence ?? 0;

                $this->info("⚡ {$match->home_team} vs {$match->away_team} → {$outcome} ({$conf}%)");
                $updated[]       = "{$match->home_team} vs {$match->away_team}: {$outcome}";
                $telegramPicks[] = [
                    'match'      => "{$match->home_team} vs {$match->away_team}",
                    'league'     => $match->league ?? '',
                    'tip'        => $outcome,
                    'confidence' => $conf,
                ];
            }
        }

        if (! empty($updated)) {
            $oneSignal->sendMatchAlert(
                title:   '⚡ Lineups Confirmed - Picks Updated!',
                message: implode(' | ', $updated) . ' - Tap to see analysis',
                path:    '/lineup-picks',
            );

            $telegram->sendLineupPicks($telegramPicks, config('app.url'));
        }

        return self::SUCCESS;
    }
}
