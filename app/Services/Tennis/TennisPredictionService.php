<?php

namespace App\Services\Tennis;

use App\Models\TennisMatch;
use App\Models\TennisPlayerRating;
use App\Models\TennisPrediction;
use Carbon\CarbonInterface;

/**
 * Tennis-specific pre-match model. It deliberately publishes probabilities,
 * not promises: surface Elo is the anchor, then recent form, surface record,
 * rankings, rest and H2H make bounded adjustments.
 */
class TennisPredictionService
{
    public function predict(TennisMatch $match): ?TennisPrediction
    {
        $date = $match->match_date ?: now();
        $surface = strtolower($match->surface ?: 'hard');
        $oneRating = $this->rating($match, $match->player_one, $surface);
        $twoRating = $this->rating($match, $match->player_two, $surface);
        $eloProbability = 1 / (1 + 10 ** (($twoRating - $oneRating) / 400));

        $one = $this->form($match, $match->player_one, $date, $surface);
        $two = $this->form($match, $match->player_two, $date, $surface);
        $h2h = $this->headToHead($match, $date);

        // A live fixture alone is not enough for a prediction. Until the
        // historical importer has supplied at least a meaningful sample for
        // both players, do not publish an arbitrary 50/50 placeholder.
        $evidence = $one['recent_matches'] + $two['recent_matches'];
        if ($evidence < 10) {
            TennisPrediction::where('tennis_match_id', $match->id)->delete();
            return null;
        }

        // Only use a component with adequate evidence. This prevents a 1-0
        // surface record or a single old H2H meeting from moving the model.
        $weighted = [['weight' => 0.58, 'value' => $eloProbability]];
        if ($one['surface_matches'] >= 5 && $two['surface_matches'] >= 5) {
            $weighted[] = ['weight' => 0.16, 'value' => $this->share($one['surface_wins'], $one['surface_matches'], $two['surface_wins'], $two['surface_matches'])];
        }
        if ($one['recent_matches'] >= 5 && $two['recent_matches'] >= 5) {
            $weighted[] = ['weight' => 0.14, 'value' => $this->share($one['recent_wins'], $one['recent_matches'], $two['recent_wins'], $two['recent_matches'])];
        }
        if ($match->player_one_rank && $match->player_two_rank) {
            $weighted[] = ['weight' => 0.07, 'value' => $this->rankProbability($match->player_one_rank, $match->player_two_rank)];
        }
        if ($h2h['total'] >= 3) {
            $weighted[] = ['weight' => 0.05, 'value' => $h2h['one_wins'] / $h2h['total']];
        }

        $weightSum = array_sum(array_column($weighted, 'weight'));
        $probability = array_sum(array_map(fn ($part) => $part['weight'] * $part['value'], $weighted)) / $weightSum;
        // Do not allow model output to look certain where the data is sparse.
        $evidenceStrength = min(1, $evidence / 20);
        $probability = 0.5 + (($probability - 0.5) * (0.55 + 0.45 * $evidenceStrength));
        $oneProb = round(max(0.05, min(0.95, $probability)) * 100, 2);
        $twoProb = round(100 - $oneProb, 2);
        $winner = $oneProb >= $twoProb ? $match->player_one : $match->player_two;
        $confidence = (int) round(max($oneProb, $twoProb));

        // Explore the full tennis board (match winner, set handicap, total sets)
        // derived from the match-win probability, then feature the SAFEST market.
        $fav    = $oneProb >= $twoProb ? $match->player_one : $match->player_two;
        $dog    = $oneProb >= $twoProb ? $match->player_two : $match->player_one;
        $favP   = max($oneProb, $twoProb) / 100;
        $markets = $this->markets($favP, $fav, $dog, (int) ($match->best_of ?: 3));
        $best    = $markets[0]; // markets() returns them safest-first

        $features = compact('oneRating', 'twoRating', 'eloProbability', 'one', 'two', 'h2h', 'surface');
        $features['markets']     = $markets;
        $features['best_market'] = $best;

        return TennisPrediction::updateOrCreate(['tennis_match_id' => $match->id], [
            'player_one_win_prob' => $oneProb, 'player_two_win_prob' => $twoProb,
            'predicted_winner' => $winner, 'confidence' => $confidence,
            'features' => $features, 'ai_panel' => null,
            'analysis' => sprintf('Safest market: %s (%.0f%%). %s %.0f%% vs %s %.0f%% on the moneyline — surface Elo, recent form, surface record, rankings and H2H.', $best['label'], $best['prob'], $match->player_one, $oneProb, $match->player_two, $twoProb),
        ]);
    }

    /**
     * Tennis board derived from the favourite's match-win probability: match
     * winner, set handicap (±1.5 sets) and total sets. Returned safest-first so
     * the caller can feature the highest-probability (safest) market.
     *
     * @return array<int, array{label: string, prob: float, key: string}>
     */
    private function markets(float $favProb, string $fav, string $dog, int $bestOf): array
    {
        $favProb = max(0.5, min(0.97, $favProb));
        $p = $this->perSetProb($favProb, $bestOf);   // favourite's per-set win prob
        $q = 1 - $p;
        $pc = fn (float $x) => round(max(0.0, min(100.0, $x * 100)), 1);

        if ($bestOf >= 5) {
            $straight = $p ** 3;                       // 3-0
            $markets = [
                ['label' => "{$fav} to win a set",       'prob' => $pc(1 - $q ** 3),      'key' => 'fav_win_set'],
                ['label' => "{$fav} +1.5 sets",          'prob' => $pc(1 - $q ** 3),      'key' => 'fav_p15'],
                ['label' => "{$fav} to win",             'prob' => $pc($favProb),          'key' => 'fav_ml'],
                ['label' => "{$dog} +1.5 sets",          'prob' => $pc(1 - $p ** 3),      'key' => 'dog_p15'],
                ['label' => "{$fav} to win 3-0",         'prob' => $pc($straight),         'key' => 'fav_30'],
            ];
        } else {
            $markets = [
                ['label' => "{$fav} +1.5 sets",          'prob' => $pc(1 - $q * $q),       'key' => 'fav_p15'],
                ['label' => "{$fav} to win",             'prob' => $pc($favProb),          'key' => 'fav_ml'],
                ['label' => 'Under 2.5 sets (straight)', 'prob' => $pc($p * $p + $q * $q), 'key' => 'under_25_sets'],
                ['label' => "{$fav} -1.5 sets (2-0)",    'prob' => $pc($p * $p),           'key' => 'fav_m15'],
                ['label' => 'Over 2.5 sets',             'prob' => $pc(2 * $p * $q),       'key' => 'over_25_sets'],
                ['label' => "{$dog} +1.5 sets",          'prob' => $pc(1 - $p * $p),       'key' => 'dog_p15'],
            ];
        }

        usort($markets, fn ($a, $b) => $b['prob'] <=> $a['prob']);
        return array_values($markets);
    }

    /** Invert the match-win probability to a per-set win probability. */
    private function perSetProb(float $matchProb, int $bestOf): float
    {
        $win = fn (float $p) => $bestOf >= 5
            ? $p ** 3 * (1 + 3 * (1 - $p) + 6 * (1 - $p) ** 2)  // first to 3 sets
            : $p * $p * (3 - 2 * $p);                             // first to 2 sets
        $lo = 0.5;
        $hi = 0.999;
        for ($i = 0; $i < 40; $i++) {
            $mid = ($lo + $hi) / 2;
            if ($win($mid) < $matchProb) $lo = $mid; else $hi = $mid;
        }
        return ($lo + $hi) / 2;
    }

    private function rating(TennisMatch $match, string $player, string $surface): float
    {
        $surfaceRating = TennisPlayerRating::where(['tour' => $match->tour, 'player_name' => $player, 'surface' => $surface])->value('rating');
        $overall = TennisPlayerRating::where(['tour' => $match->tour, 'player_name' => $player, 'surface' => 'all'])->value('rating');
        return $surfaceRating !== null ? ((float) $surfaceRating * 0.65 + (float) ($overall ?? $surfaceRating) * 0.35) : (float) ($overall ?? 1500);
    }

    private function form(TennisMatch $fixture, string $player, CarbonInterface $date, string $surface): array
    {
        $base = TennisMatch::where('tour', $fixture->tour)->where('status', 'completed')->whereDate('match_date', '<', $date)
            ->where(fn ($q) => $q->where('player_one', $player)->orWhere('player_two', $player));
        $recent = (clone $base)->orderByDesc('match_date')->limit(10)->get();
        $surfaceRows = (clone $base)->whereRaw('lower(surface) = ?', [$surface])->orderByDesc('match_date')->limit(30)->get();
        $wins = fn ($rows) => $rows->filter(fn ($m) => $m->winner === $player)->count();
        return [
            'recent_matches' => $recent->count(), 'recent_wins' => $wins($recent),
            'surface_matches' => $surfaceRows->count(), 'surface_wins' => $wins($surfaceRows),
        ];
    }

    private function headToHead(TennisMatch $fixture, CarbonInterface $date): array
    {
        $rows = TennisMatch::where('tour', $fixture->tour)->where('status', 'completed')->whereDate('match_date', '<', $date)
            ->where(fn ($q) => $q->where(fn ($x) => $x->where('player_one', $fixture->player_one)->where('player_two', $fixture->player_two))
                ->orWhere(fn ($x) => $x->where('player_one', $fixture->player_two)->where('player_two', $fixture->player_one)))
            ->orderByDesc('match_date')->limit(10)->get();
        return ['total' => $rows->count(), 'one_wins' => $rows->where('winner', $fixture->player_one)->count()];
    }

    private function share(int $oneWins, int $oneMatches, int $twoWins, int $twoMatches): float
    {
        $oneRate = $oneWins / max(1, $oneMatches);
        $twoRate = $twoWins / max(1, $twoMatches);
        return $oneRate / max(0.01, $oneRate + $twoRate);
    }

    private function rankProbability(int $oneRank, int $twoRank): float
    {
        return 1 / (1 + exp((log(max(1, $oneRank)) - log(max(1, $twoRank))) * 1.25));
    }
}
