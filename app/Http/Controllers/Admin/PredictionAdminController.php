<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prediction;
use App\Services\FootballPredictionBoardRefresher;
use App\Services\PredictionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class PredictionAdminController extends Controller
{
    public function __construct(private readonly PredictionService $predictionService)
    {
    }

    public function rebuild(): RedirectResponse
    {
        // These pull fixtures + run the AI slate, which takes minutes — longer
        // than LiteSpeed's default web timeout, so lift the PHP limit and keep
        // running even if the admin closes the tab.
        @set_time_limit(0);
        ignore_user_abort(true);

        try {
            app(FootballPredictionBoardRefresher::class)->refreshFixturesAndBoards();
            return redirect()->route('admin.predictions')->with('success', 'Latest fixtures were pulled and every prediction board was rebuilt.');
        } catch (\Throwable $exception) {
            return redirect()->route('admin.predictions')->with('error', $exception->getMessage());
        }
    }

    public function index(Request $request): View
    {
        $timezone = config('app.timezone');
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $date = $this->resolveDate($request->query('date'), $today, $timezone);

        $query = Prediction::query()
            ->with('match')
            ->whereHas('match', fn ($matches) => $matches->whereBetween('match_time', [$date, $date->endOfDay()]));

        $metrics = [
            'total' => (clone $query)->count(),
            'won' => (clone $query)->where('was_correct', true)->count(),
            'lost' => (clone $query)->where('was_correct', false)->count(),
            'pending' => (clone $query)->whereNull('was_correct')->count(),
        ];
        $resolved = $metrics['won'] + $metrics['lost'];
        $metrics['accuracy'] = $resolved > 0 ? round($metrics['won'] / $resolved * 100, 1) : null;

        $predictions = $query->orderByDesc('created_at')->paginate(30)->withQueryString();
        $dateMeta = [
            'iso' => $date->toDateString(),
            'pretty' => $date->format('l, F j, Y'),
            'is_today' => $date->isSameDay($today),
            'today_iso' => $today->toDateString(),
            'yesterday_iso' => $today->subDay()->toDateString(),
            'previous_iso' => $date->subDay()->toDateString(),
            'next_iso' => $date->lt($today) ? $date->addDay()->toDateString() : null,
        ];

        return view('admin.predictions.index', compact('predictions', 'metrics', 'dateMeta'));
    }

    public function generate(): RedirectResponse
    {
        @set_time_limit(0);
        ignore_user_abort(true);

        // Pull today's fixtures FIRST — otherwise, on a day whose matches have not
        // been fetched yet, there is nothing upcoming to predict and this returns 0.
        try {
            Artisan::call('fetch:matches');
        } catch (\Throwable $exception) {
            return redirect()->route('admin.predictions')->with('error', 'Could not fetch fixtures: '.$exception->getMessage());
        }

        $predictions = $this->predictionService->generateForUpcomingMatches();

        return redirect()->route('admin.predictions')
            ->with('success', "Fetched fixtures and generated {$predictions->count()} predictions.");
    }

    private function resolveDate(?string $raw, CarbonImmutable $today, string $timezone): CarbonImmutable
    {
        if (blank($raw)) return $today;

        try {
            $date = CarbonImmutable::createFromFormat('Y-m-d', $raw, $timezone)->startOfDay();
            return $date->gt($today) || $date->lt($today->subDays(365)) ? $today : $date;
        } catch (\Throwable) {
            return $today;
        }
    }
}
