<?php

namespace App\Mail;

use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class DailyPicksNewsletter extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public NewsletterSubscriber $subscriber,
        public Collection $picks,
        public string $dateLabel,
        public Collection $yesterdayRecap = new Collection(),
    ) {}

    public function envelope(): Envelope
    {
        // Vary subject by highest-confidence headline pick to keep it fresh
        $highestConf = $this->picks->map(fn ($p) => (int) ($p['tips'][0]['confidence'] ?? $p['confidence_pct'] ?? 0))->max() ?: 0;
        $tag = $highestConf >= 75 ? 'High Confidence' : ($highestConf >= 60 ? 'Smart Picks' : 'Daily Picks');

        return new Envelope(
            subject: "🔥 Today's 3 Best Football Picks ({$tag}) — {$this->dateLabel}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.daily-picks',
            with: [
                'unsubscribeUrl' => route('newsletter.unsubscribe', $this->subscriber->unsubscribe_token),
            ],
        );
    }
}
