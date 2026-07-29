<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdaptiveThresholdService;
use App\Services\PublicationQualityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AILearningController extends Controller
{
    public function __construct(private readonly AdaptiveThresholdService $adaptive) {}

    public function index(): View
    {
        $state = $this->adaptive->learnedState();

        return view('admin.ai-learning.index', compact('state'));
    }

    public function recalibrate(): RedirectResponse
    {
        $this->adaptive->forceRecalibrate();
        app(PublicationQualityService::class)->forget();

        return redirect()->route('admin.ai-learning.index')
            ->with('success', 'Calibration cache cleared — thresholds will recompute on next page load.');
    }
}
