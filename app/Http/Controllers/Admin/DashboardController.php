<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\DcLeagueParams;
use App\Models\DcTeamParams;
use App\Models\FootballMatch;
use App\Models\Prediction;
use App\Models\PredictionLog;
use App\Models\Team;
use App\Models\TeamAlias;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $tz = config('app.timezone');

        $resolvedPicks = Prediction::query()
            ->where('is_daily_pick', true)
            ->whereNotNull('was_correct')
            ->get(['was_correct', 'created_at']);

        $picks7d = $resolvedPicks->filter(fn ($p) => $p->created_at >= now($tz)->subDays(7));
        $correct7d = $picks7d->where('was_correct', true)->count();
        $total7d   = $picks7d->count();

        $correctAll = $resolvedPicks->where('was_correct', true)->count();
        $totalAll   = $resolvedPicks->count();

        $stats = [
            'total_matches'    => FootballMatch::count(),
            'live_matches'     => FootballMatch::whereIn('status', ['1H', 'HT', '2H', 'ET', 'BT', 'P', 'LIVE'])->count(),
            'total_predictions'=> Prediction::count(),
            'total_posts'      => BlogPost::count(),
            'published_posts'  => BlogPost::where('is_published', true)->count(),
            'ai_posts'         => BlogPost::where('is_ai_generated', true)->count(),
            'picks_today'      => Prediction::where('is_daily_pick', true)
                                    ->whereDate('created_at', now($tz)->toDateString())
                                    ->count(),
            'accuracy_7d'      => $total7d > 0 ? round($correct7d / $total7d * 100, 1) : null,
            'correct_7d'       => $correct7d,
            'wrong_7d'         => $total7d - $correct7d,
            'accuracy_all'     => $totalAll > 0 ? round($correctAll / $totalAll * 100, 1) : null,
            'picks_resolved'   => $totalAll,
        ];

        $recentMatches = FootballMatch::orderByDesc('match_time')->limit(10)->get();
        $recentPosts   = BlogPost::orderByDesc('created_at')->limit(6)->get();

        // ── Prediction engine infrastructure (Phase 1 + 1.5 + 2) ─────────
        // Surfaces the health of the measurement + Dixon-Coles layer on the
        // dashboard so ops can see at a glance whether DC is fitted, how much
        // history is loaded, and whether any teams need canonical review.
        $priorityLeagues = (array) config('leagues.season_priority', []);
        $lastFit         = DcLeagueParams::max('fit_at');
        $historicalMatches = FootballMatch::query()
            ->whereIn('league_id', $priorityLeagues)
            ->whereIn('status', ['FT', 'AET', 'PEN'])
            ->count();

        $logsByVersion = PredictionLog::query()
            ->selectRaw('model_version, COUNT(*) AS n')
            ->groupBy('model_version')
            ->pluck('n', 'model_version')
            ->all();

        $infra = [
            'dc_enabled'           => (bool) config('prediction.dc_enabled'),
            'dc_leagues_fitted'    => DcLeagueParams::count(),
            'dc_leagues_target'    => count($priorityLeagues),
            'dc_teams_persisted'   => DcTeamParams::count(),
            'dc_last_fit'          => $lastFit ? \Carbon\Carbon::parse($lastFit) : null,
            'historical_matches'   => $historicalMatches,
            'prediction_logs'      => array_sum($logsByVersion),
            'logs_by_version'      => $logsByVersion,
            'teams_tracked'        => Team::count(),
            'teams_pending_review' => TeamAlias::where('reviewed', false)->count(),
            'held_for_review'      => FootballMatch::where('held_for_review', true)->count(),
        ];

        return view('admin.dashboard', compact('stats', 'recentMatches', 'recentPosts', 'infra'));
    }
}
