<?php

namespace App\Http\Controllers;

use App\Models\WinnerSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HallOfFameController extends Controller
{
    public function index(): View
    {
        $leaderboard = $this->buildLeaderboard();

        return view('winners.hall-of-fame', compact('leaderboard'));
    }

    public function checkUsername(Request $request): JsonResponse
    {
        $username = strtolower(trim($request->input('username', '')));
        $email    = strtolower(trim($request->input('email', '')));

        if (blank($username)) {
            return response()->json(['returning' => false, 'email_match' => false]);
        }

        $existing = WinnerSubmission::approved()
            ->whereRaw('LOWER(username) = ?', [$username])
            ->whereNotNull('email')
            ->value('email');

        $returning   = $existing !== null;
        $email_match = $returning && $email && strtolower($existing) === $email;

        return response()->json([
            'returning'   => $returning,
            'email_match' => $email_match,
            'has_email'   => $returning && $existing !== null,
        ]);
    }

    public static function buildLeaderboard(int $limit = 50): \Illuminate\Support\Collection
    {
        $rows = WinnerSubmission::approved()
            ->whereNotNull('winning_amount')
            ->where('winning_amount', '>', 0)
            ->orderByDesc('created_at')
            ->get(['id', 'username', 'winning_amount', 'platform', 'currency', 'match_details', 'created_at']);

        return $rows
            ->groupBy(fn ($r) => strtolower(trim($r->username)))
            ->map(function ($group) {
                $sorted = $group->sortByDesc('created_at');
                $latest = $sorted->first();
                return (object) [
                    'username'   => $latest->username,
                    'total_won'  => $group->sum('winning_amount'),
                    'total_wins' => $group->count(),
                    'currency'   => $latest->currency,
                    'last_win'   => $latest->created_at,
                    // All individual wins for this user, newest first
                    'wins'       => $sorted->map(fn ($w) => (object) [
                        'amount'       => $w->winning_amount,
                        'platform'     => $w->platform,
                        'match'        => $w->match_details,
                        'date'         => $w->created_at,
                        'currency'     => $w->currency,
                    ])->values(),
                ];
            })
            ->sortByDesc('total_won')
            ->values()
            ->take($limit);
    }
}
