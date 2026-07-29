<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RolloverChallenge;
use App\Models\RolloverPick;
use App\Services\RolloverService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RolloverAdminController extends Controller
{
    public function index(): View
    {
        $challenge = RolloverChallenge::query()
            ->with(['picks.match'])
            ->latest('started_at')
            ->first();

        $history = RolloverChallenge::query()
            ->with(['picks'])
            ->latest('started_at')
            ->limit(5)
            ->get();

        return view('admin.rollover.index', compact('challenge', 'history'));
    }

    public function newChallenge(Request $request, RolloverService $rollover): RedirectResponse
    {
        $stake = (float) $request->input('initial_stake', 10000);
        $stake = max(1000, min(1_000_000, $stake));

        $rollover->startNewChallenge($stake);

        return back()->with('success', 'New rollover challenge started with stake ' . number_format($stake, 0) . ' NGN.');
    }

    public function selectPick(RolloverService $rollover): RedirectResponse
    {
        $pick = $rollover->selectTodaysPick();

        if (! $pick) {
            return back()->with('error', 'No suitable pick found for today, or one already exists.');
        }

        return back()->with('success', "Today's rollover pick selected: {$pick->match?->home_team} vs {$pick->match?->away_team}.");
    }

    public function voidPick(RolloverPick $pick): RedirectResponse
    {
        $pick->update(['status' => 'void', 'was_correct' => null]);
        $this->recalcDayReturn($pick);
        return back()->with('success', 'Pick voided.');
    }

    /**
     * Recompute a day's ticket return after a leg's status changes: void legs
     * drop out (odds 1.0), everything else multiplies in. Keeps the stored
     * day-level potential_return correct after a void or override.
     */
    private function recalcDayReturn(RolloverPick $pick): void
    {
        $legs = RolloverPick::query()
            ->where('challenge_id', $pick->challenge_id)
            ->where('day_number', $pick->day_number)
            ->get();
        if ($legs->isEmpty()) return;

        $stake    = (float) $legs->first()->stake_amount;
        $combined = 1.0;
        foreach ($legs as $leg) {
            if ($leg->status === 'void') continue;
            $combined *= max(1.0, (float) $leg->implied_odds);
        }
        $return = round($stake * $combined, 2);

        RolloverPick::query()
            ->where('challenge_id', $pick->challenge_id)
            ->where('day_number', $pick->day_number)
            ->update(['potential_return' => $return]);
    }

    public function overridePick(Request $request, RolloverPick $pick): RedirectResponse
    {
        $request->validate([
            'status'       => ['required', 'in:won,lost,pending,void'],
            'result_score' => ['nullable', 'string', 'max:10', 'regex:/^\d+-\d+$/'],
        ]);

        $newStatus   = $request->input('status');
        $resultScore = $request->input('result_score') ?: $pick->result_score;

        $wasCorrect = match ($newStatus) {
            'won'   => true,
            'lost'  => false,
            default => null,
        };

        $pick->update([
            'status'       => $newStatus,
            'was_correct'  => $wasCorrect,
            'result_score' => $resultScore,
        ]);

        // Recompute the day's return now that a leg's status changed (e.g.
        // un-voiding a leg restores it to the accumulator).
        $this->recalcDayReturn($pick);

        $challenge = $pick->challenge;
        if ($challenge) {
            if ($newStatus === 'won') {
                $challenge->update(['status' => $pick->day_number >= 10 ? 'complete' : 'active']);
            } elseif ($newStatus === 'lost') {
                $challenge->update(['status' => 'complete']);
            } elseif (in_array($newStatus, ['pending', 'void'], true) && $pick->day_number < 10) {
                $challenge->update(['status' => 'active']);
            }
        }

        return back()->with('success', "Day {$pick->day_number} overridden → {$newStatus}.");
    }
}
