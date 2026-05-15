<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WinnerSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WinnersAdminController extends Controller
{
    public function index(): View
    {
        $pending  = WinnerSubmission::where('is_approved', false)->orderByDesc('created_at')->get();
        $approved = WinnerSubmission::approved()->orderByDesc('created_at')->get();

        return view('admin.winners.index', compact('pending', 'approved'));
    }

    public function updateAmount(Request $request, WinnerSubmission $winner): RedirectResponse
    {
        $request->validate([
            'winning_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'currency'       => ['nullable', 'string', 'max:10'],
        ]);

        $winner->update([
            'winning_amount' => $request->input('winning_amount') ?: null,
            'currency'       => $request->input('currency', $winner->currency ?? 'USD'),
        ]);

        return back()->with('success', 'Amount updated.');
    }

    public function approve(WinnerSubmission $winner): RedirectResponse
    {
        $winner->update(['is_approved' => true]);

        return back()->with('success', 'Winner approved and published.');
    }

    public function reject(WinnerSubmission $winner): RedirectResponse
    {
        if (file_exists(public_path($winner->screenshot_path))) {
            unlink(public_path($winner->screenshot_path));
        }
        $winner->delete();

        return back()->with('success', 'Submission rejected and removed.');
    }
}
