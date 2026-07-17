<?php

namespace App\Services\DixonColes;

use Illuminate\Support\Collection;

/**
 * Maximum-likelihood fitter for Dixon-Coles.
 *
 * Fits per-team attack (α) and defense (β) log-strengths plus league-wide
 * home advantage (γ) and low-score correction (ρ) by gradient ascent on
 * the time-weighted log-likelihood:
 *
 *   L(θ) = Σ w_i log P(h_i, a_i | θ)     with w_i = 2^(-Δdays/half_life)
 *
 * The parameter space is over-identified: shifting all α up by c and all β
 * down by c leaves λ_home and λ_away unchanged. We resolve this after each
 * step by re-centering so that Σα_i = 0 (equivalently, the league-average
 * attack strength is exp(0) = 1).
 *
 * Numerical gradient via finite differences — 10× slower than analytical
 * but 20× less error-prone and fine for a weekly batch job. A league of
 * 20 teams converges in ~200 iterations, ~30s on a modest CPU.
 */
class Fitter
{
    /**
     * @param  Collection<int,array{home:string,away:string,home_goals:int,away_goals:int,date:string}>  $matches
     */
    public function __construct(
        private readonly Collection $matches,
        private readonly float $halfLifeDays = 270.0,
        private readonly int $minMatchesPerTeam = 10,
        private readonly float $shrinkageWeight = 10.0,
    ) {}

    /**
     * @return array{
     *   teams: array<string, array{attack:float, defense:float, matches:int, shrunk:bool}>,
     *   gamma: float, rho: float,
     *   iterations: int, converged: bool, final_ll: float,
     *   training_start: string, training_end: string, n_matches: int
     * }
     */
    public function fit(int $maxIterations = 800, float $tolerance = 1e-7, float $learningRate = 0.08): array
    {
        $prep = $this->prepare();
        $teams        = $prep['teams'];
        $matches      = $prep['matches'];
        $teamMatches  = $prep['teamMatches'];
        $totalWeight  = $prep['totalWeight'];

        $nTeams = count($teams);

        // Initial parameters — attack/defense at 0 (i.e. λ = exp(γ) baseline),
        // small positive home advantage, tiny negative ρ (empirically Dixon-Coles
        // typically fits ρ around -0.05 to -0.15 in European football).
        $alpha = array_fill_keys($teams, 0.0);
        $beta  = array_fill_keys($teams, 0.0);
        $gamma = 0.25;
        $rho   = -0.08;

        $prevLL = -INF;
        $converged = false;
        $iteration = 0;
        // Decay the learning rate as we approach convergence — reduces
        // oscillation near the optimum where the initial rate overshoots.
        $currentLr = $learningRate;
        $stagnation = 0;

        for ($iteration = 1; $iteration <= $maxIterations; $iteration++) {
            [$grads, $ll] = $this->gradients($matches, $alpha, $beta, $gamma, $rho, $totalWeight);

            // Update all params in one step
            foreach ($teams as $t) {
                $alpha[$t] += $currentLr * $grads['alpha'][$t];
                $beta[$t]  += $currentLr * $grads['beta'][$t];
            }
            $gamma += $currentLr * $grads['gamma'];
            $rho   += $currentLr * $grads['rho'];

            // If improvement stalled for 20 iterations, halve the LR to
            // escape a shallow oscillation. Bottoms out at 1e-4 so we don't
            // freeze entirely — the tolerance check does the final stopping.
            if (abs($ll - $prevLL) < $tolerance * 10 && $currentLr > 1e-4) {
                $stagnation++;
                if ($stagnation >= 20) {
                    $currentLr *= 0.5;
                    $stagnation = 0;
                }
            } else {
                $stagnation = 0;
            }

            // Clamp ρ into a numerically safe range that keeps τ ≥ 0 for
            // reasonable λ (up to ~4 goals/game).
            $rho = max(-0.20, min(0.20, $rho));

            // Re-center α so Σα = 0 (identifiability). β is absorbed
            // symmetrically so we recenter that too.
            $alphaMean = array_sum($alpha) / $nTeams;
            $betaMean  = array_sum($beta) / $nTeams;
            foreach ($teams as $t) {
                $alpha[$t] -= $alphaMean;
                $beta[$t]  -= $betaMean;
            }

            if (abs($ll - $prevLL) < $tolerance) {
                $converged = true;
                break;
            }
            $prevLL = $ll;
        }

        // Shrinkage: teams with < minMatchesPerTeam weighted matches get pulled
        // toward the league average (0.0 for both α and β) with weight k / (k + n).
        $shrunkFlags = [];
        foreach ($teams as $t) {
            $n = $teamMatches[$t] ?? 0;
            if ($n < $this->minMatchesPerTeam) {
                $w = $n / ($n + $this->shrinkageWeight);
                $alpha[$t] = $w * $alpha[$t];  // shrink toward 0
                $beta[$t]  = $w * $beta[$t];
                $shrunkFlags[$t] = true;
            } else {
                $shrunkFlags[$t] = false;
            }
        }

        $teamOut = [];
        foreach ($teams as $t) {
            $teamOut[$t] = [
                'attack'  => $alpha[$t],
                'defense' => $beta[$t],
                'matches' => $teamMatches[$t] ?? 0,
                'shrunk'  => $shrunkFlags[$t],
            ];
        }

        $dates = $this->matches->pluck('date');
        return [
            'teams'          => $teamOut,
            'gamma'          => $gamma,
            'rho'            => $rho,
            'iterations'     => $iteration,
            'converged'      => $converged,
            'final_ll'       => $prevLL,
            'training_start' => $dates->min(),
            'training_end'   => $dates->max(),
            'n_matches'      => $this->matches->count(),
        ];
    }

    /**
     * Prepare training data: unique teams, per-match weights based on age,
     * per-team match counts (for shrinkage decisions).
     *
     * @return array{teams: list<string>, matches: list<array>, teamMatches: array<string,int>, totalWeight: float}
     */
    private function prepare(): array
    {
        $teams = $this->matches
            ->pluck('home')
            ->merge($this->matches->pluck('away'))
            ->unique()
            ->values()
            ->all();

        $newestTs = strtotime($this->matches->max('date'));
        $prepared = [];
        $teamMatches = [];
        $totalWeight = 0.0;

        foreach ($this->matches as $m) {
            $ageDays = ($newestTs - strtotime($m['date'])) / 86400.0;
            $w = pow(2.0, -$ageDays / $this->halfLifeDays);
            $prepared[] = [
                'home'       => $m['home'],
                'away'       => $m['away'],
                'home_goals' => (int) $m['home_goals'],
                'away_goals' => (int) $m['away_goals'],
                'weight'     => $w,
            ];
            $teamMatches[$m['home']] = ($teamMatches[$m['home']] ?? 0) + 1;
            $teamMatches[$m['away']] = ($teamMatches[$m['away']] ?? 0) + 1;
            $totalWeight += $w;
        }

        return [
            'teams'       => $teams,
            'matches'     => $prepared,
            'teamMatches' => $teamMatches,
            'totalWeight' => $totalWeight,
        ];
    }

    /**
     * Numerical gradient of the *mean* time-weighted log-likelihood wrt every
     * parameter, plus the current mean LL. Normalising by total weight keeps
     * gradients on a per-match scale so the learning rate works regardless of
     * training-set size.
     */
    private function gradients(array $matches, array $alpha, array $beta, float $gamma, float $rho, float $totalWeight): array
    {
        $eps = 1e-4;
        $ll = $this->meanLogLikelihood($matches, $alpha, $beta, $gamma, $rho, $totalWeight);

        $gradAlpha = [];
        $gradBeta  = [];

        foreach ($alpha as $t => $_) {
            $save = $alpha[$t]; $alpha[$t] = $save + $eps;
            $llp = $this->meanLogLikelihood($matches, $alpha, $beta, $gamma, $rho, $totalWeight);
            $alpha[$t] = $save;
            $gradAlpha[$t] = ($llp - $ll) / $eps;
        }
        foreach ($beta as $t => $_) {
            $save = $beta[$t]; $beta[$t] = $save + $eps;
            $llp = $this->meanLogLikelihood($matches, $alpha, $beta, $gamma, $rho, $totalWeight);
            $beta[$t] = $save;
            $gradBeta[$t] = ($llp - $ll) / $eps;
        }

        $llp = $this->meanLogLikelihood($matches, $alpha, $beta, $gamma + $eps, $rho, $totalWeight);
        $gradGamma = ($llp - $ll) / $eps;

        $llp = $this->meanLogLikelihood($matches, $alpha, $beta, $gamma, $rho + $eps, $totalWeight);
        $gradRho = ($llp - $ll) / $eps;

        return [
            ['alpha' => $gradAlpha, 'beta' => $gradBeta, 'gamma' => $gradGamma, 'rho' => $gradRho],
            $ll,
        ];
    }

    /**
     * Mean time-weighted log-likelihood: (Σ w_i · log P_i) / Σ w_i.
     */
    private function meanLogLikelihood(array $matches, array $alpha, array $beta, float $gamma, float $rho, float $totalWeight): float
    {
        $ll = 0.0;
        foreach ($matches as $m) {
            $lh = exp($alpha[$m['home']] + $beta[$m['away']] + $gamma);
            $la = exp($alpha[$m['away']] + $beta[$m['home']]);

            $ph = Model::poissonPmf($m['home_goals'], $lh);
            $pa = Model::poissonPmf($m['away_goals'], $la);
            $tau = Model::tau($m['home_goals'], $m['away_goals'], $lh, $la, $rho);

            $p = $ph * $pa * $tau;
            if ($p <= 0) $p = 1e-15;  // guard against log(0) when τ pushes prob below 0

            $ll += $m['weight'] * log($p);
        }
        return $ll / $totalWeight;
    }
}
