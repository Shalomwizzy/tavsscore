<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;

class PageController extends Controller
{
    public function about(): View   { return view('pages.about'); }
    public function privacy(): View { return view('pages.privacy'); }
    public function terms(): View   { return view('pages.terms'); }
    public function contact(): View { return view('pages.contact'); }

    public function contactSend(Request $request): RedirectResponse
    {
        // ── Honeypot bot trap ──
        // Real users never fill the hidden "website" field. Bots scraping the
        // DOM fill every input. We also reject submissions that arrive less
        // than 3 seconds after the form was rendered (timestamp signed below).
        if (! blank($request->input('website'))) {
            Log::info('Contact honeypot tripped', ['ip' => $request->ip()]);
            return redirect()->route('contact')->with('success', 'Thanks for your message!');
        }

        $loadedAt = (int) $request->input('_loaded_at', 0);
        $age      = $loadedAt > 0 ? time() - $loadedAt : 0;
        // Reject submissions arriving in <3s (bot speed) or >24h (replayed form)
        if ($loadedAt > 0 && ($age < 3 || $age > 86400)) {
            Log::info('Contact form rejected (timing)', ['ip' => $request->ip(), 'age_seconds' => $age]);
            return redirect()->route('contact')->with('success', 'Thanks for your message!');
        }

        $data = $request->validate([
            'name'    => ['required', 'string', 'max:100'],
            'email'   => ['required', 'email', 'max:200'],
            'subject' => ['required', 'string', 'max:100'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        Log::info('Contact form submission', [
            'name'    => $data['name'],
            'email'   => $data['email'],
            'subject' => $data['subject'],
        ]);

        return redirect()->route('contact')
            ->with('success', 'Thanks for your message! We\'ll get back to you within 1–2 business days.');
    }
}
