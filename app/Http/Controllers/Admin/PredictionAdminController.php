<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prediction;
use App\Services\PredictionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PredictionAdminController extends Controller
{
    public function __construct(private readonly PredictionService $predictionService)
    {
    }

    public function index(): View
    {
        $predictions = Prediction::with('match')->orderByDesc('created_at')->paginate(30);

        return view('admin.predictions.index', compact('predictions'));
    }

    public function generate(): RedirectResponse
    {
        $predictions = $this->predictionService->generateForUpcomingMatches();

        return redirect()->route('admin.predictions')
            ->with('success', "Generated {$predictions->count()} predictions.");
    }
}
