<?php

namespace App\Http\Controllers;

use App\Models\FootballMatch;
use App\Models\Prediction;
use App\Services\GroqService;
use App\Services\PredictionService;
use App\Support\PickHelpers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PredictionPageController extends Controller
{
    public function __construct(private readonly PredictionService $predictionService) {}

    public function index(Request $request): View
    {
        $tz       = config('app.timezone');
        $todayStr = now($tz)->toDateString();

        // Parse ?date=YYYY-MM-DD — clamped to last 365 days, no future
        $requested = $this->resolveDate($request->query('date'), $tz);
        $isToday   = $requested->toDateString() === $todayStr;

        $dateMeta = [
            'iso'        => $requested->toDateString(),
            'is_today'   => $isToday,
            'prev_iso'   => $requested->copy()->subDay()->toDateString(),
            'next_iso'   => $isToday ? null : $requested->copy()->addDay()->toDateString(),
            'today_iso'  => $todayStr,
            'min_iso'    => now($tz)->subDays(365)->toDateString(),
            'max_iso'    => $todayStr,
            'pretty'     => $requested->format('l, F j Y'),
        ];

        return view('predictions.index', compact('dateMeta'));
    }

    private function resolveDate(?string $raw, string $tz): Carbon
    {
        $today = now($tz)->startOfDay();
        if (blank($raw)) return $today;

        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $raw, $tz);
            if (! $parsed) return $today;
        } catch (\Throwable) {
            return $today;
        }

        $parsed = $parsed->startOfDay();
        if ($parsed->gt($today)) return $today;
        if ($parsed->lt($today->copy()->subDays(365))) return $today;
        return $parsed;
    }

    public function show(string $slug): View|Response
    {
        // Slug ends with -{api_id}; pull the id off the tail for an O(1) lookup
        if (! preg_match('/-(\d+)$/', $slug, $m)) {
            abort(404);
        }
        $apiId = (int) $m[1];

        $match = FootballMatch::query()
            ->with('prediction')
            ->where('api_id', $apiId)
            ->first();

        if (! $match || ! $match->prediction) {
            abort(404);
        }

        // Canonical slug check — redirect if the URL teams don't match the actual ones
        if ($slug !== $match->slug) {
            return redirect()->route('predictions.show', $match->slug, 301);
        }

        $p = $match->prediction;

        $confidencePct = round(PickHelpers::confidencePct($p));
        $isAi = ! blank($p->analysis)
            && $p->analysis !== GroqService::FALLBACK_ANALYSIS
            && $p->analysis !== 'Prediction pending';

        // Same Groq tip extraction logic used on /picks (kept inline so this page
        // is self-contained and survives if the picks helpers move)
        $tip      = null;
        $body     = $p->analysis;
        if ($isAi && $p->analysis) {
            if (preg_match('/💡\s*Tip:\s*(.+?)(?:\.|$)/iu', $p->analysis, $tm)) {
                $tip  = trim($tm[1]);
                $body = trim(str_replace($tm[0], '', $p->analysis));
            } elseif (preg_match('/(?:my clearest tip is|best bet is|i recommend|i\'?d back|back\s+the?)\s+([^.]+)/i', $p->analysis, $tm)) {
                $tip = trim($tm[1]);
            }
        }

        $injuries     = \App\Models\MatchInjury::query()->where('match_id', $match->id)->get()->groupBy('team_name');
        $apiPrediction = \App\Models\ApiPrediction::query()->where('match_id', $match->id)->first();

        return view('predictions.show', [
            'match'        => $match,
            'pred'         => $p,
            'injuries'     => $injuries,
            'apiPrediction'=> $apiPrediction,
            'confidencePct'=> $confidencePct,
            'isAi'         => $isAi,
            'tip'          => $tip,
            'body'         => $body,
        ]);
    }
}
