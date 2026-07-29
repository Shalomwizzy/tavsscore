<?php

namespace App\Services;

use App\Models\FootballMatch;
use App\Models\ShalomBlogDraft;
use App\Models\ShalomPrediction;
use App\Services\DixonColes\Predictor;
use App\Support\PickHelpers;
use Illuminate\Support\Collection;

/**
 * TavsScore's first-party, versioned shadow model.
 *
 * Shalom AI uses parameters trained from verified final scores only. It is
 * intentionally isolated: its rows are never read by public pick pages,
 * notifications, booking codes, or published news until an administrator
 * explicitly promotes a future version after independent evaluation.
 */
class ShalomAIService
{
    public const VERSION = 'shalom-ai-v1';
    private const FINISHED = ['FT', 'AET', 'PEN'];

    public function __construct(private readonly Predictor $predictor) {}

    public function predictUpcoming(int $hoursAhead = 48): array
    {
        $matches = FootballMatch::query()
            ->whereBetween('match_time', [now(), now()->addHours($hoursAhead)])
            ->whereNotIn('status', array_merge(self::FINISHED, ['CANC', 'PST', 'ABD']))
            ->where('held_for_review', false)
            ->get();
        $created = 0; $skipped = 0;
        foreach ($matches as $match) {
            $forecast = $this->predictor->predict($match, self::VERSION);
            if (! $forecast) { $skipped++; continue; }
            $race = ['Home Win' => $forecast['home_win'], 'Draw' => $forecast['draw'], 'Away Win' => $forecast['away_win']];
            $outcome = array_search(max($race), $race, true);
            $confidence = (int) round(max($race) * 100 * ($forecast['confidence_flag'] === 'LOW' ? .88 : 1));
            ShalomPrediction::updateOrCreate(
                ['match_id' => $match->id, 'model_version' => self::VERSION],
                [
                    'home_win_probability' => round($forecast['home_win'] * 100, 2),
                    'draw_probability' => round($forecast['draw'] * 100, 2),
                    'away_win_probability' => round($forecast['away_win'] * 100, 2),
                    'over_25_probability' => round($forecast['over_25'] * 100, 2),
                    'btts_probability' => round($forecast['btts'] * 100, 2),
                    'predicted_outcome' => $outcome,
                    'confidence' => max(1, min(99, $confidence)),
                    'is_shadow' => true,
                    'explanation' => [
                        'engine' => 'Shalom AI independent score model',
                        'expected_goals' => ['home' => round($forecast['lambda_home'], 2), 'away' => round($forecast['lambda_away'], 2)],
                        'data_confidence' => $forecast['confidence_flag'],
                        'top_scores' => $forecast['top_scores'],
                    ],
                ],
            );
            $created++;
        }
        return compact('created', 'skipped');
    }

    public function settle(): int
    {
        $settled = 0;
        ShalomPrediction::query()->with('match')->whereNull('settled_at')->chunkById(250, function (Collection $rows) use (&$settled) {
            foreach ($rows as $prediction) {
                if (! $prediction->match || ! in_array($prediction->match->status, self::FINISHED, true)) continue;
                $result = PickHelpers::resolveForMatch($prediction->match, $prediction->predicted_outcome);
                if ($result === null) continue;
                $prediction->update(['was_correct' => $result, 'settled_at' => now()]);
                $settled++;
            }
        });
        return $settled;
    }

    /** Deterministic, data-grounded editorial draft. Never public by default. */
    public function makeBlogDraft(): ?ShalomBlogDraft
    {
        $prediction = ShalomPrediction::query()->with('match')
            ->where('model_version', self::VERSION)->whereNull('settled_at')
            ->orderByDesc('confidence')->first();
        if (! $prediction?->match) return null;
        $m = $prediction->match;
        $date = $m->match_time?->timezone('Africa/Lagos')->format('l, j F') ?? 'the upcoming match';
        $title = "{$m->home_team} vs {$m->away_team}: Shalom AI match preview";
        $prob = number_format(max($prediction->home_win_probability, $prediction->draw_probability, $prediction->away_win_probability), 1);
        $content = "<h2>What Shalom AI sees</h2><p>This private research draft is generated from TavsScore's verified fixture and final-score dataset. For {$m->home_team} versus {$m->away_team} on {$date}, Shalom AI's current 1X2 lean is <strong>{$prediction->predicted_outcome}</strong> at {$prob}% model probability.</p><h2>Score-based forecast</h2><p>The model estimates {$m->home_team} at ".data_get($prediction->explanation, 'expected_goals.home', '—')." expected goals and {$m->away_team} at ".data_get($prediction->explanation, 'expected_goals.away', '—').". Its goal-market estimates are Over 2.5 at {$prediction->over_25_probability}% and both teams to score at {$prediction->btts_probability}%.</p><h2>Research note</h2><p>This is an admin-only Shalom AI draft, not a public betting recommendation. It must be reviewed against current team news, line-ups and bookmaker prices before any editorial decision. Once the fixture ends, its prediction is automatically settled and remains in the model's independent track record.</p>";
        return ShalomBlogDraft::create(['match_id' => $m->id, 'shalom_prediction_id' => $prediction->id, 'title' => $title, 'excerpt' => "Private Shalom AI preview for {$m->home_team} vs {$m->away_team}.", 'content' => $content, 'status' => 'draft', 'generated_at' => now()]);
    }
}
