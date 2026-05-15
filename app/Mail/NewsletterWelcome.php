<?php

namespace App\Mail;

use App\Models\NewsletterSubscriber;
use App\Models\Prediction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsletterWelcome extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public NewsletterSubscriber $subscriber)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🎉 Welcome to TavsScore - your daily picks start tomorrow',
        );
    }

    public function content(): Content
    {
        // Pull a sample of today's picks if any - gives subscribers an instant
        // taste of what they signed up for.
        $today  = now('Africa/Lagos')->startOfDay();
        $cutoff = now('Africa/Lagos')->endOfDay();

        $picks = Prediction::query()
            ->with('match')
            ->where('is_daily_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->orderBy('pick_rank')
            ->get();

        return new Content(
            view: 'emails.newsletter-welcome',
            with: [
                'unsubscribeUrl' => route('newsletter.unsubscribe', $this->subscriber->unsubscribe_token),
                'picksUrl'       => route('picks.index'),
                'samplePicks'    => $picks,
            ],
        );
    }
}
