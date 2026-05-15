<?php

namespace App\Http\Controllers;

use App\Mail\NewsletterWelcome;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class NewsletterController extends Controller
{
    /**
     * Subscribe - single opt-in. Auto-confirms the email and immediately fires
     * a welcome email listing benefits + sample of what's coming.
     */
    public function subscribe(Request $request): RedirectResponse
    {
        // Honeypot - bots fill the hidden "website" field
        if (! blank($request->input('website'))) {
            return back()->with('newsletter_status', 'Thanks!');
        }

        $data = $request->validate([
            'email'  => ['required', 'email', 'max:200'],
            'source' => ['nullable', 'string', 'max:50'],
        ]);

        $email  = strtolower(trim($data['email']));
        $ipHash = $request->ip() ? hash('sha256', config('app.key') . '|' . $request->ip()) : null;

        $existing = NewsletterSubscriber::where('email', $email)->first();

        if ($existing && $existing->isConfirmed()) {
            return back()->with('newsletter_status', "You're already subscribed - tomorrow's picks are on the way!");
        }

        $tokens = NewsletterSubscriber::freshTokens();
        $payload = [
            // Auto-confirm - no email-link verification step
            'confirmed_at'      => now(),
            'confirm_token'     => null,
            'unsubscribe_token' => $existing->unsubscribe_token ?? $tokens['unsubscribe_token'],
            'source'            => $data['source'] ?? 'unknown',
            'ip_hash'           => $ipHash ? mb_substr($ipHash, 0, 64) : null,
            'unsubscribed_at'   => null,
        ];

        if ($existing) {
            $existing->update($payload);
            $subscriber = $existing->fresh();
        } else {
            $subscriber = NewsletterSubscriber::create(['email' => $email] + $payload);
        }

        // Welcome email (silent failure - subscription still succeeded)
        try {
            Mail::to($email)->send(new NewsletterWelcome($subscriber));
        } catch (\Throwable $e) {
            Log::warning('Newsletter welcome send failed', ['email' => $email, 'error' => $e->getMessage()]);
        }

        return back()->with(
            'newsletter_status',
            "🎉 You're in! Tomorrow's 3 picks land in your inbox at 09:00 Lagos. Check your email for a quick welcome from us."
        );
    }

    /**
     * Legacy confirm route - kept for any old confirmation links still in flight.
     * New signups don't need this since they're auto-confirmed.
     */
    public function confirm(string $token): View
    {
        $sub = NewsletterSubscriber::where('confirm_token', $token)->first();

        // If a token matched, confirm - handles older links from before single opt-in
        if ($sub && ! $sub->confirmed_at) {
            $sub->update(['confirmed_at' => now(), 'confirm_token' => null]);
            return view('newsletter.confirmed', ['ok' => true, 'email' => $sub->email]);
        }

        // Token not found OR already used - still render success since most
        // hits on this URL are now stale links from auto-confirmed users
        return view('newsletter.confirmed', [
            'ok'      => $sub !== null,
            'email'   => $sub?->email,
            'message' => $sub ? null : "We couldn't find that link, but if you signed up recently you're already subscribed.",
        ]);
    }

    /**
     * One-click unsubscribe - token in URL.
     */
    public function unsubscribe(string $token): View
    {
        $sub = NewsletterSubscriber::where('unsubscribe_token', $token)->first();
        if (! $sub) {
            return view('newsletter.unsubscribed', ['ok' => false, 'message' => 'This unsubscribe link is invalid.']);
        }

        if (! $sub->unsubscribed_at) {
            $sub->update(['unsubscribed_at' => now()]);
        }

        return view('newsletter.unsubscribed', ['ok' => true, 'email' => $sub->email]);
    }
}
