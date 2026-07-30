<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesDateNav;
use App\Http\Controllers\Controller;
use App\Models\TennisPrediction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class TennisAdminController extends Controller
{
    use ResolvesDateNav;

    public function index(Request $request): View
    {
        $tz       = 'Africa/Lagos';
        $date     = $this->resolveDate($request->query('date'), $tz);
        $dateMeta = $this->buildDateMeta($date, $tz, 'admin.tennis.index');

        $predictions = TennisPrediction::with('match')
            ->whereHas('match', fn ($q) => $q->whereDate('match_date', $date->toDateString()))
            ->orderByDesc('confidence')->paginate(30)->withQueryString();

        $settled = TennisPrediction::query()
            ->whereNotNull('was_correct')
            ->whereHas('match', fn ($q) => $q->whereDate('match_date', '>=', now($tz)->subDays(30)->toDateString()))
            ->get(['was_correct']);
        $winStats = ['total' => $settled->count(), 'won' => $settled->where('was_correct', true)->count()];

        return view('admin.tennis.index', compact('predictions', 'dateMeta', 'winStats'));
    }

    public function fetchFixtures(): RedirectResponse
    {
        Artisan::call('tennis:fetch-fixtures');
        return back()->with('success', trim(Artisan::output()) ?: 'Tennis fixtures refreshed.');
    }

    public function generatePredictions(): RedirectResponse
    {
        Artisan::call('tennis:predict');
        return back()->with('success', trim(Artisan::output()) ?: 'Tennis predictions generated.');
    }

    public function settleResults(): RedirectResponse
    {
        Artisan::call('tennis:settle-results');
        return back()->with('success', trim(Artisan::output()) ?: 'Tennis results checked.');
    }
}
