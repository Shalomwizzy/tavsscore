<?php

namespace App\Services\Booking;

use App\Models\TennisPrediction;

/** Builds an isolated tennis match-winner booking ticket. */
class TennisBetslipSpecService
{
    public function today(string $date): ?array
    {
        $predictions = TennisPrediction::query()->with('match')
            ->where('confidence', '>=', 65)
            ->whereHas('match', fn ($q) => $q->where('status', 'scheduled')->whereDate('match_date', $date))
            ->orderByDesc('confidence')->get()
            ->filter(fn (TennisPrediction $p) => $p->match && filled($p->predicted_winner))->take(12)->values();
        if ($predictions->count() < 3) return null;

        return [
            'ref' => 'tennis-match-winners', 'title' => 'Tennis Match Winners',
            'market' => 'Tennis match winner accumulator', 'sport' => 'tennis',
            'min_total_odds' => 2.0, 'max_total_odds' => 500.0,
            'selections' => $predictions->map(function (TennisPrediction $p): array {
                $m = $p->match; $one = $p->predicted_winner === $m->player_one;
                $prob = $one ? (float) $p->player_one_win_prob : (float) $p->player_two_win_prob;
                return ['tennis_match_id' => $m->id, 'home' => $m->player_one, 'away' => $m->player_two,
                    'kickoff' => $m->scheduled_at?->toIso8601String(), 'market' => $one ? 'Player One Win' : 'Player Two Win',
                    'model_prob' => round($prob, 1), 'est_odds' => round(1 / max(.01, $prob / 100), 2), 'sport' => 'tennis'];
            })->all(),
        ];
    }

    /**
     * Extra independent tennis candidates for the mixed Football + Tennis
     * High Risk ticket. These are intentionally less conservative than the
     * standalone tennis board, but remain model-backed scheduled fixtures.
     */
    public function highRiskSelections(string $date): array
    {
        return TennisPrediction::query()->with('match')
            ->where('confidence', '>=', 55)
            ->whereHas('match', fn ($q) => $q->where('status', 'scheduled')->whereDate('match_date', $date))
            ->orderBy('confidence')
            ->get()->filter(fn (TennisPrediction $p) => $p->match && filled($p->predicted_winner))
            ->take(12)->map(function (TennisPrediction $p): array {
                $m = $p->match; $one = $p->predicted_winner === $m->player_one;
                $prob = $one ? (float) $p->player_one_win_prob : (float) $p->player_two_win_prob;
                return ['tennis_match_id' => $m->id, 'home' => $m->player_one, 'away' => $m->player_two,
                    'kickoff' => $m->scheduled_at?->toIso8601String(), 'market' => $one ? 'Player One Win' : 'Player Two Win',
                    'model_prob' => round($prob, 1), 'est_odds' => round(1 / max(.01, $prob / 100), 2), 'sport' => 'tennis'];
            })->values()->all();
    }
}
