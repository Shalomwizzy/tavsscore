<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShalomBlogDraft;
use App\Models\ShalomPrediction;
use App\Services\ShalomAIService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class ShalomAIController extends Controller
{
    public function index(): View
    {
        $predictions = ShalomPrediction::with('match')->latest()->limit(60)->get();
        $drafts = ShalomBlogDraft::with('match')->latest('generated_at')->limit(10)->get();
        $settled = ShalomPrediction::whereNotNull('was_correct')->count();
        $wins = ShalomPrediction::where('was_correct', true)->count();
        $stats = ['total' => ShalomPrediction::count(), 'settled' => $settled, 'wins' => $wins, 'hit_rate' => $settled ? round($wins / $settled * 100, 1) : null];
        return view('admin.shalom-ai.index', compact('predictions', 'drafts', 'stats'));
    }

    public function train(): RedirectResponse { Artisan::call('shalom:train'); return back()->with('success', trim(Artisan::output())); }
    public function predict(): RedirectResponse { Artisan::call('shalom:predict'); return back()->with('success', trim(Artisan::output())); }
    public function settle(): RedirectResponse { Artisan::call('shalom:settle'); return back()->with('success', trim(Artisan::output())); }
    public function draft(): RedirectResponse { Artisan::call('shalom:draft'); return back()->with('success', trim(Artisan::output())); }
}
