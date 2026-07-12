<?php

namespace App\Services\DixonColes;

use App\Models\DcLeagueParams;
use App\Models\DcTeamParams;
use App\Models\FootballMatch;
use App\Services\DixonColes\TeamNameNormalizer;
use Illuminate\Support\Facades\Cache;

/**
 * Runtime scoring: given a fixture and a fitted (league, model_version),
 * return calibrated probabilities for 1X2, O/U 2.5, BTTS, and top scores.
 *
 * If either team has no parameters in the target model_version (new club,
 * newly promoted, or league never fitted), returns null — the spec's
 * NO_PREDICTION contract. Never silently substitutes an LLM guess.
 */
class Predictor
{
    /**
     * @return array{
     *   home_win: float, draw: float, away_win: float,
     *   over_15: float, over_25: float, over_35: float,
     *   btts: float, top_scores: array<int, array{score:string,probability:float}>,
     *   lambda_home: float, lambda_away: float,
     *   confidence_flag: string
     * } | null
     */
    public function predict(FootballMatch $match, string $modelVersion): ?array
    {
        if (! $match->league_id || ! $match->home_team || ! $match->away_team) {
            return null;
        }

        $league = $this->leagueParams((int) $match->league_id, $modelVersion);
        if (! $league) return null;

        $home = $this->teamParams((int) $match->league_id, $modelVersion, TeamNameNormalizer::key($match->home_team));
        $away = $this->teamParams((int) $match->league_id, $modelVersion, TeamNameNormalizer::key($match->away_team));
        if (! $home || ! $away) return null;

        $lambdaHome = exp($home->attack + $away->defense + $league->gamma);
        $lambdaAway = exp($away->attack + $home->defense);

        $matrix = Model::matrix($lambdaHome, $lambdaAway, $league->rho);
        $race   = Model::oneXTwo($matrix);

        // Confidence flag: LOW if either team was shrunk toward the league mean
        // (i.e. sparse-data team) or has < 10 matches used at fit time.
        $confidence = ($home->is_shrunk || $away->is_shrunk) ? 'LOW' : 'NORMAL';

        return [
            'home_win'        => $race['home_win'],
            'draw'            => $race['draw'],
            'away_win'        => $race['away_win'],
            'over_15'         => Model::overGoals($matrix, 1.5),
            'over_25'         => Model::overGoals($matrix, 2.5),
            'over_35'         => Model::overGoals($matrix, 3.5),
            'btts'            => Model::btts($matrix),
            'top_scores'      => Model::topScores($matrix, 3),
            'lambda_home'     => $lambdaHome,
            'lambda_away'     => $lambdaAway,
            'confidence_flag' => $confidence,
        ];
    }

    private function leagueParams(int $leagueId, string $modelVersion): ?DcLeagueParams
    {
        return Cache::remember(
            "dc_league_{$leagueId}_{$modelVersion}",
            now()->addMinutes(30),
            fn () => DcLeagueParams::where('league_id', $leagueId)
                ->where('model_version', $modelVersion)
                ->first(),
        );
    }

    private function teamParams(int $leagueId, string $modelVersion, string $team): ?DcTeamParams
    {
        return Cache::remember(
            "dc_team_{$leagueId}_{$modelVersion}_" . md5($team),
            now()->addMinutes(30),
            fn () => DcTeamParams::where('league_id', $leagueId)
                ->where('model_version', $modelVersion)
                ->where('team_name', $team)
                ->first(),
        );
    }
}
