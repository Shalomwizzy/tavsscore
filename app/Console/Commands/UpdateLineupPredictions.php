<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Services\OneSignalService;
use App\Services\PredictionService;
use App\Services\TelegramService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
            ->whereNotIn('status', ['FT', 'AET', 'PEN', 'CANC', 'PST', '1H', 'HT', '2H', 'ET', 'BT', 'P', 'LIVE'])
            ->orderBy('match_time')
            ->get();

        if ($matches->isEmpty()) {
            return self::SUCCESS;
        }

        $updated     = [];
        $telegramPicks = [];

        $this->line("Processing {$matches->count()} eligible matches…");

        foreach ($matches as $match) {
            $label = "{$match->home_team} vs {$match->away_team}";

            $wasUpdated = $predictionService->regenerateWithLineup($match);

            if (! $wasUpdated) {
                $this->line("  ⏭  {$label} — no lineup yet / already processed / AI failed.");
                Log::debug("UpdateLineupPredictions: skipped {$label} (regenerate=false)");
                continue;
            }

            $prediction = $match->prediction;
            $outcome    = $prediction?->predicted_outcome ?? '-';
            $conf       = $prediction?->confidence ?? 0;

            // Only promote as a lineup pick if AIs didn't explicitly conflict.
            // gemini_agrees === null   → Gemini not configured, still include.
            // agreement_level = 'speculative' → Groq < 60% but no one else called;
            //   lineup context makes this pick more reliable, so allow it through.
            // agreement_level = 'conflict' → all other AIs disagree → hard exclude.
            $tips           = is_array($prediction?->tips) ? $prediction->tips : [];
            $geminiAgrees   = $tips[0]['gemini_agrees']   ?? null;
            $agreementLevel = $tips[0]['agreement_level'] ?? 'unverified';

            if ($geminiAgrees === false && $agreementLevel !== 'speculative') {
                $this->line("  ⛔ {$label} — AIs conflict (level={$agreementLevel}), skipping lineup pick.");
                Log::info("UpdateLineupPredictions: {$label} excluded — conflict (level={$agreementLevel})");
                continue;
            }

            if ($conf < 50) {
                $this->line("  ⬇️  {$label} — confidence too low ({$conf}%), skipping.");
                Log::info("UpdateLineupPredictions: {$label} excluded — conf={$conf}%");
                continue;
            }

            $this->info("⚡ {$label} → {$outcome} ({$conf}%)");
            Log::info("UpdateLineupPredictions: pick added — {$label} → {$outcome} ({$conf}%)");

            $leaguePrefix    = $match->league ? "[{$match->league}] " : '';
            $updated[]       = "{$leaguePrefix}{$label}: {$outcome}";
            $telegramPicks[] = [
                'match'      => $label,
                'league'     => $match->league ?? '',
                'tip'        => $outcome,
                'confidence' => $conf,
            ];
        }

        if (! empty($updated)) {
            $first = $telegramPicks[0] ?? [];
            $topMatch = $first['match'] ?? '';
            $topTip   = $first['tip']   ?? '';
            $oneSignal->notifyLineupUpdated($topMatch, $topTip, count($updated));

            $telegram->sendLineupPicks($telegramPicks, config('app.url'));
        }

        return self::SUCCESS;
    }
}
