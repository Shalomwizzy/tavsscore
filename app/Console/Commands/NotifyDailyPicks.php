<?php

namespace App\Console\Commands;

use App\Models\Prediction;
use App\Services\OneSignalService;
use App\Services\TelegramService;
use App\Support\LeagueCoverage;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class NotifyDailyPicks extends Command
{
    protected $signature = 'picks:notify {--type= : Which pick type to notify: daily|draw|gg|over15|over25|team3plus|doublechance|lineup|correctscore (default: all)} {--force : Bypass already-sent cache guard}';

    protected $description = 'Send push + Telegram for today\'s picks. Use --type= to send one group at a time for staggered scheduling.';

    public function handle(OneSignalService $oneSignal, TelegramService $telegram): int
    {
        $tz     = 'Africa/Lagos';
        $today  = CarbonImmutable::now($tz)->startOfDay();
        $cutoff = CarbonImmutable::now($tz)->endOfDay();
        $type   = $this->option('type') ?: 'all';
        $force  = (bool) $this->option('force');
        $date   = now('Africa/Lagos')->toDateString();
        $url    = config('app.url');

        // ── Daily picks + correct scores ───────────────────────────
        if ($type === 'all' || $type === 'daily') {
            $picks = Prediction::query()
                ->with('match')
                ->where('is_daily_pick', true)
                ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
                ->orderBy('pick_rank')
                ->get();

            if ($picks->isNotEmpty()) {
                $lines = $picks->map(function ($p) {
                    $match = $p->match;
                    if (! $match) return null;
                    $conf   = $p->confidence ? " ({$p->confidence}%)" : '';
                    $league = LeagueCoverage::formatName($match->league, $match->league_country);
                    $league = $league ? "[{$league}] " : '';
                    return "{$league}{$match->home_team} vs {$match->away_team}: {$p->predicted_outcome}{$conf}";
                })->filter()->values();

                $topPick = $picks->first();
                $topMatch = $topPick->match ? "{$topPick->match->home_team} vs {$topPick->match->away_team}" : '';
                $oneSignal->notifyDailyPicks($topMatch, $topPick->predicted_outcome ?? '', $picks->count());

                $telegram->sendDailyPicks($picks->map(function ($p) {
                    $match = $p->match;
                    if (! $match) return null;
                    return ['match' => "{$match->home_team} vs {$match->away_team}", 'league' => LeagueCoverage::formatName($match->league, $match->league_country), 'tip' => $p->predicted_outcome ?? '', 'confidence' => $p->confidence ?? ''];
                })->filter()->values()->toArray(), $url);

                Cache::put('picks_sent_daily_' . now('Africa/Lagos')->toDateString(), true, now()->addHours(36));
                $this->info("Daily picks sent: {$picks->count()}");
            } else {
                $this->warn('No daily picks today — skipped.');
            }

        }

        // ── Draw picks ─────────────────────────────────────────────
        if ($type === 'all' || $type === 'draw') {
            $drawPicks = Prediction::query()
                ->with('match')
                ->where('is_draw_pick', true)
                ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
                ->orderBy('draw_rank')
                ->get();

            if ($drawPicks->isNotEmpty()) {
                $topDraw = $drawPicks->first();
                $topDrawMatch = $topDraw->match ? "{$topDraw->match->home_team} vs {$topDraw->match->away_team}" : '';
                $oneSignal->notifyDrawPicks($topDrawMatch, $drawPicks->count());
                $telegram->sendDrawPicks($drawPicks->map(fn ($p) => $p->match ? ['match' => "{$p->match->home_team} vs {$p->match->away_team}", 'league' => LeagueCoverage::formatName($p->match->league, $p->match->league_country), 'confidence' => $p->confidence ?? ''] : null)->filter()->values()->toArray(), $url);
                Cache::put('picks_sent_draw_' . now('Africa/Lagos')->toDateString(), true, now()->addHours(36));
                $this->info("Draw picks sent: {$drawPicks->count()}");
            } else {
                $this->warn('No draw picks today — skipped.');
            }
        }

        // ── GG picks ───────────────────────────────────────────────
        if ($type === 'all' || $type === 'gg') {
            $ggPicks = Prediction::query()
                ->with('match')
                ->where('is_gg_pick', true)
                ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
                ->orderBy('gg_rank')
                ->get();

            if ($ggPicks->isNotEmpty()) {
                $topGG = $ggPicks->first();
                $topGGMatch = $topGG->match ? "{$topGG->match->home_team} vs {$topGG->match->away_team}" : '';
                $oneSignal->notifyGGPicks($topGGMatch, $ggPicks->count());
                $telegram->sendGGPicks($ggPicks->map(fn ($p) => $p->match ? ['match' => "{$p->match->home_team} vs {$p->match->away_team}", 'league' => LeagueCoverage::formatName($p->match->league, $p->match->league_country), 'confidence' => $p->confidence ?? ''] : null)->filter()->values()->toArray(), $url);
                Cache::put('picks_sent_gg_' . now('Africa/Lagos')->toDateString(), true, now()->addHours(36));
                $this->info("GG picks sent: {$ggPicks->count()}");
            } else {
                $this->warn('No GG picks today — skipped.');
            }
        }

        // ── Over 1.5 picks ─────────────────────────────────────────
        if ($type === 'all' || $type === 'over15') {
            $over15Picks = Prediction::query()
                ->with('match')
                ->where('is_over15_pick', true)
                ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
                ->orderBy('over15_rank')
                ->get();

            if ($over15Picks->isNotEmpty()) {
                $topO15 = $over15Picks->first();
                $topO15Match = $topO15->match ? "{$topO15->match->home_team} vs {$topO15->match->away_team}" : '';
                $oneSignal->notifyOver15Picks($topO15Match, round($topO15->over_15_prob ?? 0) . '%', $over15Picks->count());
                $telegram->sendOver15Picks($over15Picks->map(fn ($p) => $p->match ? ['match' => "{$p->match->home_team} vs {$p->match->away_team}", 'league' => LeagueCoverage::formatName($p->match->league, $p->match->league_country), 'prob' => round($p->over_15_prob ?? 0)] : null)->filter()->values()->toArray(), $url);
                Cache::put('picks_sent_over15_' . now('Africa/Lagos')->toDateString(), true, now()->addHours(36));
                $this->info("Over 1.5 picks sent: {$over15Picks->count()}");
            } else {
                $this->warn('No Over 1.5 picks today — skipped.');
            }
        }

        // ── Over 2.5 picks ─────────────────────────────────────────
        if ($type === 'all' || $type === 'over25') {
            $over25Picks = Prediction::query()
                ->with('match')
                ->where('is_over25_pick', true)
                ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
                ->orderBy('over25_rank')
                ->get();

            if ($over25Picks->isNotEmpty()) {
                $topO25 = $over25Picks->first();
                $topO25Match = $topO25->match ? "{$topO25->match->home_team} vs {$topO25->match->away_team}" : '';
                $oneSignal->notifyOver25Picks($topO25Match, round($topO25->over_25_prob ?? 0) . '%', $over25Picks->count());
                $telegram->sendOver25Picks($over25Picks->map(fn ($p) => $p->match ? ['match' => "{$p->match->home_team} vs {$p->match->away_team}", 'league' => LeagueCoverage::formatName($p->match->league, $p->match->league_country), 'prob' => round($p->over_25_prob ?? 0)] : null)->filter()->values()->toArray(), $url);
                Cache::put('picks_sent_over25_' . now('Africa/Lagos')->toDateString(), true, now()->addHours(36));
                $this->info("Over 2.5 picks sent: {$over25Picks->count()}");
            } else {
                $this->warn('No Over 2.5 picks today — skipped.');
            }
        }

        // ── Team 3+ picks ──────────────────────────────────────────
        if ($type === 'all' || $type === 'team3plus') {
            $team3Picks = Prediction::query()
                ->with('match')
                ->where('is_team3plus_pick', true)
                ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
                ->orderBy('team3plus_rank')
                ->get();

            if ($team3Picks->isNotEmpty()) {
                $topT3 = $team3Picks->first();
                $topT3Match = $topT3->match ? "{$topT3->match->home_team} vs {$topT3->match->away_team}" : '';
                $topT3Label = $topT3->team3plus_label ?? 'Home 3+';
                $topT3IsHome = str_starts_with($topT3Label, 'Home');
                $topT3Team = $topT3->match ? ($topT3IsHome ? $topT3->match->home_team : $topT3->match->away_team) : '';
                $oneSignal->notifyTeam3Picks($topT3Match, $topT3Team, $team3Picks->count());
                $telegram->sendTeam3PlusPicks($team3Picks->map(function ($p) {
                    if (! $p->match) return null;
                    $label    = $p->team3plus_label ?? 'Home 3+';
                    $isHome   = str_starts_with($label, 'Home');
                    $market   = str_ends_with($label, '2+') ? '2+' : '3+';
                    $teamName = $isHome ? $p->match->home_team : $p->match->away_team;
                    $prob     = $market === '2+'
                        ? round($isHome ? ($p->home_2plus_prob ?? 0) : ($p->away_2plus_prob ?? 0))
                        : round($isHome ? ($p->home_3plus_prob ?? 0) : ($p->away_3plus_prob ?? 0));
                    return ['match' => "{$p->match->home_team} vs {$p->match->away_team}", 'league' => LeagueCoverage::formatName($p->match->league, $p->match->league_country), 'team' => $teamName, 'market' => $market, 'prob' => $prob];
                })->filter()->values()->toArray(), $url);
                Cache::put('picks_sent_team3plus_' . now('Africa/Lagos')->toDateString(), true, now()->addHours(36));
                $this->info("Team Goals NO picks sent: {$team3Picks->count()}");
            } else {
                $this->warn('No Team 3+ picks today — skipped.');
            }
        }

        // ── Double Chance picks ────────────────────────────────────
        if ($type === 'all' || $type === 'doublechance') {
            if (! $force && Cache::has("picks_sent_doublechance_{$date}")) {
                $this->info('Double Chance already notified today — skipping (use --force to re-send).');
            } else {
                $dcPicks = Prediction::query()
                    ->with('match')
                    ->where('is_double_chance_pick', true)
                    ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
                    ->orderBy('double_chance_rank')
                    ->get();

                if ($dcPicks->isNotEmpty()) {
                    $topDC = $dcPicks->first();
                    $topDCMatch = $topDC->match ? "{$topDC->match->home_team} vs {$topDC->match->away_team}" : '';
                    $topDCLabel = $topDC->double_chance_label ?? '1X';
                    $topDCProb  = $topDCLabel === '1X'
                        ? round((float) $topDC->home_win_prob + (float) $topDC->draw_prob, 1)
                        : round((float) $topDC->away_win_prob + (float) $topDC->draw_prob, 1);
                    $oneSignal->notifyDoubleChancePicks($topDCMatch, $topDCLabel, $topDCProb . '%', $dcPicks->count());

                    $telegram->sendDoubleChancePicks($dcPicks->map(function ($p) {
                        if (! $p->match) return null;
                        $label = $p->double_chance_label ?? '1X';
                        $dc1x  = round((float) $p->home_win_prob + (float) $p->draw_prob, 1);
                        $dc2x  = round((float) $p->away_win_prob + (float) $p->draw_prob, 1);
                        return [
                            'match'  => "{$p->match->home_team} vs {$p->match->away_team}",
                            'league' => LeagueCoverage::formatName($p->match->league, $p->match->league_country),
                            'label'  => $label,
                            'prob'   => $label === '1X' ? $dc1x : $dc2x,
                        ];
                    })->filter()->values()->toArray(), $url);

                    Cache::put('picks_sent_doublechance_' . now('Africa/Lagos')->toDateString(), true, now()->addHours(36));
                    $this->info("Double Chance picks sent: {$dcPicks->count()}");
                } else {
                    $this->warn('No Double Chance picks today — skipped.');
                }
            }
        }

        // ── Lineup picks ───────────────────────────────────────────
        if ($type === 'lineup') {
            $lineupPicks = Prediction::query()
                ->with('match')
                ->where('has_lineup', true)
                ->whereNotNull('confidence')
                ->whereNotNull('predicted_outcome')
                ->where('predicted_outcome', '!=', 'Competitive Match')
                ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
                ->orderByDesc('confidence')
                ->limit(10)
                ->get();

            if ($lineupPicks->isNotEmpty()) {
                $lines      = [];
                $telegramData = [];
                foreach ($lineupPicks as $p) {
                    $match = $p->match;
                    if (! $match) continue;
                    $league  = LeagueCoverage::formatName($match->league, $match->league_country);
                    $league  = $league ? "[{$league}] " : '';
                    $lines[] = "{$league}{$match->home_team} vs {$match->away_team}: {$p->predicted_outcome} ({$p->confidence}%)";
                    $telegramData[] = ['match' => "{$match->home_team} vs {$match->away_team}", 'league' => LeagueCoverage::formatName($match->league, $match->league_country), 'tip' => $p->predicted_outcome, 'confidence' => $p->confidence ?? ''];
                }
                if (! empty($lines)) {
                    $topLineup = $lineupPicks->first();
                    $topLineupMatch = $topLineup->match ? "{$topLineup->match->home_team} vs {$topLineup->match->away_team}" : '';
                    $oneSignal->notifyLineupUpdated($topLineupMatch, $topLineup->predicted_outcome ?? '', count($lines));
                    $telegram->sendLineupPicks($telegramData, $url);
                    $this->info("Lineup picks sent: " . count($lines));
                }
            } else {
                $this->warn('No lineup picks today — skipped.');
            }
        }

        // ── Correct score picks ────────────────────────────────────
        if ($type === 'correctscore') {
            $scorePicks = Prediction::query()
                ->with('match')
                ->where('is_correct_score_pick', true)
                ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
                ->orderBy('correct_score_rank')
                ->get()
                ->filter(fn ($p) => ! empty($p->likely_scores));

            if ($scorePicks->isNotEmpty()) {
                $telegramData = $scorePicks->map(function ($p) {
                    $match = $p->match;
                    if (! $match) return null;
                    return ['match' => "{$match->home_team} vs {$match->away_team}", 'league' => LeagueCoverage::formatName($match->league, $match->league_country), 'scores' => array_slice(is_array($p->likely_scores) ? $p->likely_scores : [], 0, 5)];
                })->filter()->values()->toArray();

                $pushLines = $scorePicks->map(function ($p) {
                    $match = $p->match;
                    if (! $match || empty($p->likely_scores)) return null;
                    $league = LeagueCoverage::formatName($match->league, $match->league_country);
                    $league = $league ? "[{$league}] " : '';
                    $top    = $p->likely_scores[0]['score'] ?? '';
                    return "{$league}{$match->home_team} vs {$match->away_team}: {$top}";
                })->filter()->values();

                $topCS = $scorePicks->first();
                $topCSMatch = $topCS->match ? "{$topCS->match->home_team} vs {$topCS->match->away_team}" : '';
                $topCSScore = $topCS->likely_scores[0]['score'] ?? '?-?';
                $oneSignal->notifyCorrectScorePicks($topCSMatch, $topCSScore, $scorePicks->count());
                $telegram->sendCorrectScores($telegramData, $url);
                $this->info("Correct score picks sent: {$scorePicks->count()}");
            } else {
                $this->warn('No correct score predictions today — skipped.');
            }
        }

        return self::SUCCESS;
    }
}
