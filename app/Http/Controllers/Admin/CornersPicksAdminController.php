<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesDateNav;
use App\Http\Controllers\Controller;
use App\Models\FixtureStatistic;
use App\Models\Prediction;
use App\Services\FootballPredictionBoardRefresher;
use App\Services\PredictionService;
use App\Support\PickHelpers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class CornersPicksAdminController extends Controller
{
    use ResolvesDateNav;

    public function __construct(
        private readonly PredictionService $predictionService,
        private readonly FootballPredictionBoardRefresher $boardRefresher,
    ) {}

    public function index(Request $request): View
    {
        $tz       = 'Africa/Lagos';
        $date     = $this->resolveDate($request->query('date'), $tz);
        $dateMeta = $this->buildDateMeta($date, $tz, 'admin.corners.index');
        $start    = $date->copy()->startOfDay();
        $end      = $date->copy()->endOfDay();

        $picks = Prediction::query()
            ->with('match')
            ->where('is_corners_pick', true)
            ->whereHas('match', fn ($query) => $query
                ->whereBetween('match_time', [$start, $end])
                ->whereNotIn('status', ['CANC', 'PST', 'ABD', 'AWD', 'WO'])
            )
            ->orderBy('corners_rank')
            ->get();

        $cards = $picks->map(function (Prediction $pick): array {
            $match    = $pick->match;
            $label    = (string) ($pick->corners_label ?: 'Corners');
            $board    = is_array($pick->market_board) ? $pick->market_board : [];
            $finished = $match && in_array($match->status, ['FT', 'AET', 'PEN'], true);

            return [
                'pick'        => $pick,
                'match'       => $match,
                'label'       => $label,
                'probability' => round((float) ($board[$label] ?? 0)),
                'reasons'     => PickHelpers::reasonBullets($pick->analysis, 3),
                'finished'    => $finished,
                'result'      => $finished ? PickHelpers::resolveForMatch($match, $label) : null,
            ];
        });

        $settled = $cards->filter(fn (array $card) => $card['result'] !== null);
        $correct = $settled->filter(fn (array $card) => $card['result'] === true)->count();

        // A corner market is only calculated once each team has at least three
        // completed fixtures with corner statistics. Show this in admin so an
        // empty page has an actionable explanation instead of looking broken.
        $statRows = FixtureStatistic::query()->whereNotNull('corners')->count();
        $readyTeams = FixtureStatistic::query()
            ->whereNotNull('corners')
            ->select('team_name')
            ->groupBy('team_name')
            ->havingRaw('COUNT(*) >= 3')
            ->get()
            ->count();

        return view('admin.corners.index', compact(
            'cards', 'dateMeta', 'statRows', 'readyTeams', 'settled', 'correct',
        ));
    }

    /** Re-rank corner markets from the existing stored fixture statistics. */
    public function refresh(): RedirectResponse
    {
        $picks = $this->predictionService->selectCornersPicks();
        if ($picks->isNotEmpty()) {
            Artisan::call('picks:notify', ['--type' => 'corners', '--force' => true]);
        }

        return redirect()->route('admin.corners.index')
            ->with('success', "Selected {$picks->count()} qualifying corner pick(s) from stored match statistics.");
    }

    /** Pull recent finished-match stats, rebuild boards, then select corners. */
    public function rebuild(): RedirectResponse
    {
        try {
            Artisan::call('stats:fetch-fixture-stats', ['--days' => 7, '--sleep' => 300]);
            $statsOutput = trim(Artisan::output());

            $this->boardRefresher->refreshFixturesAndBoards();
            $picks = $this->predictionService->selectCornersPicks();
            if ($picks->isNotEmpty()) {
                Artisan::call('picks:notify', ['--type' => 'corners', '--force' => true]);
            }

            $notice = "Corner data refreshed and boards rebuilt. {$picks->count()} qualifying pick(s) selected.";
            if (str_contains($statsOutput, 'quota exhausted')) {
                $notice .= ' API-Football quota is exhausted, so no new historical stats were added in this run.';
            }

            return redirect()->route('admin.corners.index')->with('success', $notice);
        } catch (\Throwable $exception) {
            return redirect()->route('admin.corners.index')->with('error', $exception->getMessage());
        }
    }
}
