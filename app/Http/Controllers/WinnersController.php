<?php

namespace App\Http\Controllers;

use App\Models\WinnerSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WinnersController extends Controller
{
    public function index(): View
    {
        $winners = WinnerSubmission::approved()
            ->orderByDesc('created_at')
            ->paginate(12);

        return view('winners.index', compact('winners'));
    }

    public function submit(Request $request): RedirectResponse
    {
        $request->validate([
            'username'         => ['required', 'string', 'max:60'],
            'email'            => ['required', 'email', 'max:150'],
            'screenshots'      => ['required', 'array', 'min:1', 'max:5'],
            'screenshots.*'    => ['image', 'max:5120'],
            'pick_description' => ['nullable', 'string', 'max:255'],
            'match_details'    => ['nullable', 'string', 'max:255'],
            'platform'         => ['nullable', 'string', 'max:60'],
            'winning_amount'   => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'currency'         => ['nullable', 'string', 'max:10'],
        ]);

        $paths = [];
        foreach ($request->file('screenshots') as $file) {
            $ext      = $file->getClientOriginalExtension() ?: 'jpg';
            $filename = Str::uuid() . '.' . $ext;
            $file->move(public_path('images/winners'), $filename);
            $paths[] = 'images/winners/' . $filename;
        }

        WinnerSubmission::create([
            'username'         => $request->input('username'),
            'email'            => $request->input('email'),
            'screenshot_path'  => count($paths) === 1 ? $paths[0] : json_encode($paths),
            'pick_description' => $request->input('pick_description'),
            'match_details'    => $request->input('match_details') ?: null,
            'platform'         => $request->input('platform') ?: null,
            'winning_amount'   => $request->input('winning_amount'),
            'currency'         => $request->input('currency', 'USD'),
            'is_approved'      => false,
        ]);

        $count = count($paths);
        $label = $count === 1 ? '1 screenshot' : "{$count} screenshots";

        return back()->with('success', "🎉 Thanks! Your {$label} have been submitted and will appear after review.");
    }
}
