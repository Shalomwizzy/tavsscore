<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WinnerSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Str;

class WinnersAdminController extends Controller
{
    public function index(): View
    {
        $pending  = WinnerSubmission::where('is_approved', false)->orderByDesc('created_at')->get();
        $approved = WinnerSubmission::approved()->orderByDesc('created_at')->get();

        return view('admin.winners.index', compact('pending', 'approved'));
    }

    public function edit(WinnerSubmission $winner): View
    {
        return view('admin.winners.edit', compact('winner'));
    }

    public function update(Request $request, WinnerSubmission $winner): RedirectResponse
    {
        $request->validate([
            'username'         => ['required', 'string', 'max:60'],
            'email'            => ['nullable', 'email', 'max:120'],
            'pick_description' => ['nullable', 'string', 'max:300'],
            'match_details'    => ['nullable', 'string', 'max:200'],
            'platform'         => ['nullable', 'string', 'max:80'],
            'winning_amount'   => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'currency'         => ['nullable', 'string', 'max:10'],
            'is_approved'      => ['nullable', 'boolean'],
            'screenshots'      => ['nullable', 'array', 'max:5'],
            'screenshots.*'    => ['image', 'max:5120'],
            'remove_photos'    => ['nullable', 'array'],
            'remove_photos.*'  => ['string'],
        ]);

        $data = [
            'username'         => trim($request->input('username')),
            'email'            => $request->input('email') ?: null,
            'pick_description' => $request->input('pick_description') ?: null,
            'match_details'    => $request->input('match_details') ?: null,
            'platform'         => $request->input('platform') ?: null,
            'winning_amount'   => $request->input('winning_amount') ?: null,
            'currency'         => $request->input('currency', 'NGN'),
            'is_approved'      => $request->boolean('is_approved'),
        ];

        // Build current paths array
        $currentPaths = $winner->screenshot_urls; // already URL-prefixed
        // Strip the leading "/" to get the stored path form
        $storedPaths = array_map(fn ($u) => ltrim($u, '/'), $currentPaths);

        // Remove selected photos
        $toRemove = $request->input('remove_photos', []);
        foreach ($toRemove as $rel) {
            $rel = ltrim($rel, '/');
            $storedPaths = array_filter($storedPaths, fn ($p) => $p !== $rel);
            $full = public_path($rel);
            if (file_exists($full)) @unlink($full);
        }
        $storedPaths = array_values($storedPaths);

        // Upload new screenshots
        if ($request->hasFile('screenshots')) {
            foreach ($request->file('screenshots') as $file) {
                $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('images/winners'), $filename);
                $storedPaths[] = 'images/winners/' . $filename;
            }
        }

        // Persist screenshot_path in same format as original submission
        if (! empty($storedPaths)) {
            $data['screenshot_path'] = count($storedPaths) === 1
                ? $storedPaths[0]
                : json_encode(array_values($storedPaths));
        }

        $winner->update($data);

        return redirect()->route('admin.winners.index')
            ->with('success', "Winner \"{$data['username']}\" updated.");
    }

    public function updateAmount(Request $request, WinnerSubmission $winner): RedirectResponse
    {
        $request->validate([
            'winning_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'currency'       => ['nullable', 'string', 'max:10'],
        ]);

        $winner->update([
            'winning_amount' => $request->input('winning_amount') ?: null,
            'currency'       => $request->input('currency', $winner->currency ?? 'NGN'),
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
        // Delete all photos
        foreach ($winner->screenshot_urls as $url) {
            $path = public_path(ltrim($url, '/'));
            if (file_exists($path)) @unlink($path);
        }
        $winner->delete();

        return back()->with('success', 'Submission rejected and removed.');
    }
}
