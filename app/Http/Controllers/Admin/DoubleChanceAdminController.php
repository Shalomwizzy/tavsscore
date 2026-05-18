<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prediction;
use App\Services\PredictionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class DoubleChanceAdminController extends Controller
{
    public function __construct(private readonly PredictionService $predictionService) {}

    public function index(): View
    {
        $tz     = 'Africa/Lagos';
        $today  = CarbonImmutable::now($tz)->startOfDay();
        $cutoff = CarbonImmutable::now($tz)->endOfDay();

        $todayPicks = Prediction::query()
            ->with('match')
            ->where('is_double_chance_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->orderBy('double_chance_rank')
            ->get();

        $history = Prediction::query()
            ->with('match')
            ->where('is_double_chance_pick', true)
            ->whereHas('match', fn ($q) => $q->where('match_time', '<', $today))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->groupBy(fn ($p) => $p->match?->match_time?->setTimezone($tz)->format('Y-m-d') ?? 'unknown');

        $resolved = Prediction::where('is_double_chance_pick', true)
            ->where('double_chance_notified', true)
            ->with('match')
            ->get();

        $total   = $resolved->count();
        $correct = $resolved->filter(function ($p) {
            if (! $p->match) return false;
            $home = (int) $p->match->home_score;
            $away = (int) $p->match->away_score;
            return $p->double_chance_label === '1X' ? $home >= $away : $away >= $home;
        })->count();
        $accuracy = $total > 0 ? round($correct / $total * 100, 1) : null;

        return view('admin.double-chance.index', compact('todayPicks', 'history', 'total', 'correct', 'accuracy'));
    }

    public function refresh(): RedirectResponse
    {
        $picks = $this->predictionService->selectDoubleChancePicks();

        if ($picks->isNotEmpty()) {
            Artisan::call('picks:notify', ['--type' => 'doublechance']);
            $output = Artisan::output();
            $sent   = str_contains($output, 'Double Chance picks sent:');
            $msg    = $sent
                ? "Re-selected {$picks->count()} picks — notification sent to Telegram + push ✅"
                : "Re-selected {$picks->count()} picks — notification skipped (picks may already be sent today or an error occurred).";
        } else {
            $msg = 'No qualifying Double Chance picks found today (need DC probability ≥ 60%).';
        }

        return redirect()->route('admin.double-chance.index')->with('success', $msg);
    }

    public function notify(): RedirectResponse
    {
        Artisan::call('picks:notify', ['--type' => 'doublechance', '--force' => true]);
        $output = Artisan::output();
        $sent   = str_contains($output, 'Double Chance picks sent:');
        $msg    = $sent
            ? 'Double Chance notification sent to Telegram + push ✅'
            : 'No Double Chance picks found for today — nothing sent. Run Re-select first.';

        return redirect()->route('admin.double-chance.index')->with('success', $msg);
    }
}
