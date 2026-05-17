<?php

namespace App\Http\Controllers;

use App\Services\PredictionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PredictionController extends Controller
{
    public function __construct(private readonly PredictionService $predictionService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $tz   = config('app.timezone');
        $date = $this->parseDate($request->query('date'), $tz);
        $page = max(1, (int) $request->query('page', 1));

        return response()->json(
            $this->predictionService->allPredictions($date, $page)
        )->header('Cache-Control', 'public, max-age=60');
    }

    private function parseDate(?string $raw, string $tz): ?Carbon
    {
        if (blank($raw)) return null;
        try {
            $d = Carbon::createFromFormat('Y-m-d', $raw, $tz);
            if (! $d) return null;
            $d = $d->startOfDay();
            $today = now($tz)->startOfDay();
            if ($d->gt($today)) return null;
            if ($d->lt($today->copy()->subDays(365))) return null;
            return $d;
        } catch (\Throwable) {
            return null;
        }
    }

    public function show(int $match_id): JsonResponse
    {
        $prediction = $this->predictionService->predictionForMatch($match_id);

        if (! $prediction) {
            return response()->json([
                'message' => 'Prediction not found.',
            ], 404);
        }

        return response()->json([
            'data' => $prediction,
        ]);
    }

    public function generate(): JsonResponse
    {
        $predictions = $this->predictionService->generateForUpcomingMatches();

        return response()->json([
            'message' => "Generated {$predictions->count()} predictions.",
            'count' => $predictions->count(),
        ] + $this->predictionService->allPredictions());
    }
}
