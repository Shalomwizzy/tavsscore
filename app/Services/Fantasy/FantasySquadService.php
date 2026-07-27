<?php

namespace App\Services\Fantasy;

use App\Models\FantasySquad;
use App\Models\PlayerStatistic;
use Illuminate\Support\Collection;

/**
 * Builds a Fantasy-Premier-League-style "best XI" from our stored player stats.
 * No AI — pure form/value maths off season stats. Picks 15 players (2 GK,
 * 5 DEF, 5 MID, 3 FWD) inside a £100m budget and ≤3 per club, then the best
 * legal starting XI + captain, plus weekly "players to buy" suggestions.
 *
 * As richer per-gameweek data accumulates the same maths sharpens; today it
 * runs off season aggregates (goals, assists, minutes, rating, saves).
 */
class FantasySquadService
{
    private const BUDGET      = 100.0;
    private const MAX_PER_CLUB = 3;
    private const SQUAD = ['GK' => 2, 'DEF' => 5, 'MID' => 5, 'FWD' => 3];

    // FPL points per goal by position.
    private const GOAL_PTS = ['GK' => 6, 'DEF' => 6, 'MID' => 5, 'FWD' => 4];

    // Club kit as [body, sleeves/trim, pattern] where pattern is 'solid',
    // 'stripes' (vertical) or 'sash' (diagonal). Matched by substring on the
    // team name; unknown clubs fall back to a neutral solid kit.
    private const KITS = [
        'arsenal'           => ['#EF0107', '#FFFFFF', 'solid'],
        'aston villa'       => ['#670E36', '#95BFE5', 'solid'],
        'bournemouth'       => ['#D0021B', '#111111', 'stripes'],
        'brentford'         => ['#D20000', '#FFFFFF', 'stripes'],
        'brighton'          => ['#0057B8', '#FFFFFF', 'stripes'],
        'burnley'           => ['#6C1D45', '#99D6EA', 'solid'],
        'chelsea'           => ['#034694', '#FFFFFF', 'solid'],
        'crystal palace'    => ['#1B458F', '#C4122E', 'sash'],
        'everton'           => ['#003399', '#FFFFFF', 'solid'],
        'fulham'            => ['#EFEFEF', '#111111', 'solid'],
        'ipswich'           => ['#3A64A3', '#FFFFFF', 'solid'],
        'leeds'             => ['#EFEFEF', '#1D428A', 'solid'],
        'leicester'         => ['#003090', '#FDBE11', 'solid'],
        'liverpool'         => ['#C8102E', '#FFFFFF', 'solid'],
        'manchester city'   => ['#6CABDD', '#FFFFFF', 'solid'],
        'man city'          => ['#6CABDD', '#FFFFFF', 'solid'],
        'manchester united' => ['#DA291C', '#111111', 'solid'],
        'man united'        => ['#DA291C', '#111111', 'solid'],
        'man utd'           => ['#DA291C', '#111111', 'solid'],
        'newcastle'         => ['#241F20', '#FFFFFF', 'stripes'],
        'nottingham'        => ['#DD0000', '#FFFFFF', 'solid'],
        'forest'            => ['#DD0000', '#FFFFFF', 'solid'],
        'southampton'       => ['#D71920', '#FFFFFF', 'stripes'],
        'sunderland'        => ['#EB172B', '#FFFFFF', 'stripes'],
        'tottenham'         => ['#EFEFEF', '#132257', 'solid'],
        'spurs'             => ['#EFEFEF', '#132257', 'solid'],
        'west ham'          => ['#7A263A', '#1BB1E7', 'solid'],
        'wolverhampton'     => ['#FDB913', '#231F20', 'solid'],
        'wolves'            => ['#FDB913', '#231F20', 'solid'],
        'luton'             => ['#EFEFEF', '#F78F1E', 'solid'],
        'sheffield'         => ['#EE2737', '#FFFFFF', 'stripes'],
    ];

    public function build(int $leagueId = 39, ?int $season = null): ?FantasySquad
    {
        // Use the latest season we actually hold stats for (in the pre-season
        // gap the "current" season has no data yet, so fall back to last season).
        $season ??= PlayerStatistic::where('league_id', $leagueId)->max('season') ?? $this->currentSeason();

        $players = PlayerStatistic::query()
            ->where('league_id', $leagueId)
            ->where('season', $season)
            ->where('minutes', '>', 0)
            ->get()
            ->map(fn (PlayerStatistic $p) => $this->rate($p))
            ->filter()
            ->values();

        if ($players->count() < 11) {
            return null; // not enough data to field a team
        }

        $squad = $this->selectSquad($players);
        if (count($squad) < 11) {
            return null;
        }

        [$startingXi, $bench, $formation] = $this->pickStartingXi($squad);

        // Captain = highest projected points in the XI; vice = next.
        $ranked  = collect($startingXi)->sortByDesc('points')->values();
        $captain = $ranked->first();
        $vice    = $ranked->get(1);

        foreach ($startingXi as &$p) {
            $p['is_captain'] = $captain && $p['id'] === $captain['id'];
            $p['is_vice']    = $vice && $p['id'] === $vice['id'];
        }
        unset($p);

        $budgetUsed  = round(collect($squad)->sum('price'), 1);
        $totalPoints = (int) round(collect($startingXi)->sum('points') + ($captain['points'] ?? 0)); // captain doubles

        $transfers = $this->transfersToBuy($players, $squad);

        return FantasySquad::updateOrCreate(
            ['league_id' => $leagueId, 'season' => $season, 'gameweek' => $this->gameweekLabel()],
            [
                'formation'    => $formation,
                'budget_used'  => $budgetUsed,
                'total_points' => $totalPoints,
                'captain'      => $captain['name'] ?? null,
                'vice_captain' => $vice['name'] ?? null,
                'starting_xi'  => $startingXi,
                'bench'        => $bench,
                'transfers_in' => $transfers,
                'built_at'     => now(),
            ]
        );
    }

    /** Compute fantasy points + price for one player. Returns a flat array. */
    private function rate(PlayerStatistic $p): ?array
    {
        $pos = $this->normalisePosition($p->position);
        if (! $pos) return null;

        $raw     = is_array($p->raw) ? $p->raw : [];
        $saves   = (int) ($raw['goals']['saves'] ?? 0);
        $penSaved = (int) ($raw['penalty']['saved'] ?? 0);

        $apps    = max(1, (int) $p->appearances);

        $points  = 0.0;
        $points += $p->goals   * (self::GOAL_PTS[$pos] ?? 4);
        $points += $p->assists * 3;
        $points += round($p->minutes / 90) * 2;                 // appearance points
        $points -= $p->yellow_cards * 1;
        $points -= $p->red_cards * 3;
        if ($pos === 'GK') {
            $points += floor($saves / 3) + $penSaved * 5;
        }
        // Quality/form bonus from average match rating.
        $points += max(0, ((float) $p->rating - 6.7)) * $apps * 3;

        $points = max(0, round($points));

        return [
            'id'       => $p->player_api_id,
            'name'     => $p->player_name,
            'photo'    => $p->player_photo,
            'team'     => $p->team_name,
            'team_id'  => $p->team_api_id,
            'position' => $pos,
            'goals'    => (int) $p->goals,
            'assists'  => (int) $p->assists,
            'apps'     => (int) $p->appearances,
            'rating'   => round((float) $p->rating, 2),
            'points'   => $points,
            'price'    => $this->price($points, $pos),
            'kit'      => $this->kitFor($p->team_name),
        ];
    }

    /** [body, sleeves, pattern] kit for a club name, neutral solid fallback. */
    private function kitFor(?string $team): array
    {
        $t = mb_strtolower($team ?? '');
        foreach (self::KITS as $needle => $kit) {
            if (str_contains($t, $needle)) {
                return $kit;
            }
        }
        return ['#334155', '#94a3b8', 'solid'];
    }

    /** Prices derived sub-linearly from points so cheap performers offer value. */
    private function price(float $points, string $pos): float
    {
        $base  = ['GK' => 4.0, 'DEF' => 4.0, 'MID' => 4.5, 'FWD' => 4.5][$pos] ?? 4.0;
        $scaled = $base + 9.0 * pow(min(1.0, $points / 220), 0.85);
        return round(max($base, min(13.5, $scaled)) * 2) / 2; // nearest £0.5
    }

    /**
     * Select 15 players honouring position quotas, budget and club limits.
     * Fills each position with the best value first, then upgrades on points
     * while budget allows.
     */
    private function selectSquad(Collection $players): array
    {
        $byPos = $players->groupBy('position');
        $distinctClubs = $players->pluck('team_id')->unique()->count();
        $clubCap = $distinctClubs >= 6 ? self::MAX_PER_CLUB : PHP_INT_MAX;

        // Cheapest price available in each position — used to reserve enough
        // budget for the slots we haven't filled yet, so no position starves.
        $minPrice = [];
        foreach (self::SQUAD as $pos => $n) {
            $minPrice[$pos] = ($byPos[$pos] ?? collect())->min('price') ?? 4.0;
        }

        $need     = self::SQUAD;
        $squad    = [];
        $spend    = 0.0;
        $clubUsed = [];

        // Best points first within each position, but only if we can still
        // afford to fill every remaining required slot at minimum price.
        foreach (self::SQUAD as $pos => $count) {
            $pool = ($byPos[$pos] ?? collect())->sortByDesc('points')->values();
            foreach ($pool as $pl) {
                if ($need[$pos] <= 0) break;
                if (($clubUsed[$pl['team_id']] ?? 0) >= $clubCap) continue;

                $reserve = 0.0;
                foreach ($need as $p2 => $n2) {
                    $after    = ($p2 === $pos) ? $n2 - 1 : $n2;
                    $reserve += max(0, $after) * $minPrice[$p2];
                }
                if ($spend + $pl['price'] + $reserve > self::BUDGET + 0.001) continue;

                $squad[] = $pl;
                $spend  += $pl['price'];
                $clubUsed[$pl['team_id']] = ($clubUsed[$pl['team_id']] ?? 0) + 1;
                $need[$pos]--;
            }
        }

        // Backfill any slot still open (cheapest first) to keep the squad legal.
        foreach (self::SQUAD as $pos => $count) {
            if ($need[$pos] <= 0) continue;
            $ids  = collect($squad)->pluck('id')->all();
            $pool = ($byPos[$pos] ?? collect())
                ->reject(fn ($p) => in_array($p['id'], $ids, true))
                ->sortBy('price')->values();
            foreach ($pool as $pl) {
                if ($need[$pos] <= 0) break;
                if (($clubUsed[$pl['team_id']] ?? 0) >= $clubCap) continue;
                $squad[] = $pl;
                $clubUsed[$pl['team_id']] = ($clubUsed[$pl['team_id']] ?? 0) + 1;
                $need[$pos]--;
            }
        }

        return $squad;
    }

    /**
     * Best legal starting XI from the 15: 1 GK, 3-5 DEF, 2-5 MID, 1-3 FWD.
     * Returns [startingXi, bench, formationString].
     */
    private function pickStartingXi(array $squad): array
    {
        $byPos = collect($squad)->groupBy('position')
            ->map(fn ($g) => $g->sortByDesc('points')->values());

        $xi = [];
        // Best keeper.
        $gk = ($byPos['GK'] ?? collect())->first();
        if ($gk) $xi[] = $gk;

        // Minimums.
        $def = ($byPos['DEF'] ?? collect())->take(3)->all();
        $mid = ($byPos['MID'] ?? collect())->take(2)->all();
        $fwd = ($byPos['FWD'] ?? collect())->take(1)->all();
        $xi  = array_merge($xi, $def, $mid, $fwd);

        // Fill the remaining outfield slots with the highest points left,
        // respecting maxes (5 DEF, 5 MID, 3 FWD).
        $counts = ['DEF' => count($def), 'MID' => count($mid), 'FWD' => count($fwd)];
        $maxes  = ['DEF' => 5, 'MID' => 5, 'FWD' => 3];
        $chosen = collect($xi)->pluck('id')->all();

        $remaining = collect($squad)
            ->whereIn('position', ['DEF', 'MID', 'FWD'])
            ->reject(fn ($p) => in_array($p['id'], $chosen, true))
            ->sortByDesc('points')
            ->values();

        foreach ($remaining as $p) {
            if (count($xi) >= 11) break;
            $pos = $p['position'];
            if ($counts[$pos] >= $maxes[$pos]) continue;
            $xi[] = $p;
            $counts[$pos]++;
        }

        $benchIds = collect($xi)->pluck('id')->all();
        $bench    = collect($squad)->reject(fn ($p) => in_array($p['id'], $benchIds, true))
            ->sortBy(fn ($p) => $p['position'] === 'GK' ? 0 : 1) // sub keeper first
            ->values()->all();

        $formation = "{$counts['DEF']}-{$counts['MID']}-{$counts['FWD']}";

        return [array_values($xi), $bench, $formation];
    }

    /** Top value players by points-per-£ as weekly "buy" suggestions. */
    private function transfersToBuy(Collection $players, array $squad): array
    {
        $inSquad = collect($squad)->pluck('id')->all();

        return $players
            ->reject(fn ($p) => in_array($p['id'], $inSquad, true))
            ->filter(fn ($p) => $p['apps'] >= 3 && $p['points'] > 0)
            ->sortByDesc(fn ($p) => $p['points'] / max(4.0, $p['price']))
            ->take(6)
            ->map(fn ($p) => [
                'name'     => $p['name'],
                'team'     => $p['team'],
                'photo'    => $p['photo'],
                'position' => $p['position'],
                'price'    => $p['price'],
                'points'   => $p['points'],
                'kit'      => $p['kit'],
                'value'    => round($p['points'] / max(4.0, $p['price']), 1),
            ])
            ->values()->all();
    }

    private function normalisePosition(?string $pos): ?string
    {
        return match ($pos) {
            'Goalkeeper' => 'GK',
            'Defender'   => 'DEF',
            'Midfielder' => 'MID',
            'Attacker'   => 'FWD',
            default      => null,
        };
    }

    private function currentSeason(): int
    {
        // Football seasons are labelled by their starting year (Aug–May).
        $now = now('Africa/Lagos');
        return $now->month >= 7 ? $now->year : $now->year - 1;
    }

    private function gameweekLabel(): string
    {
        return 'Week of ' . now('Africa/Lagos')->startOfWeek()->format('M j');
    }
}
