<?php

namespace Tests\Feature;

use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_subscription_redirects_back(): void
    {
        $response = $this->post('/newsletter/subscribe', [
            'email' => 'fan@example.com',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'fan@example.com']);
    }

    public function test_invalid_email_fails_validation(): void
    {
        $response = $this->post('/newsletter/subscribe', [
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('newsletter_subscribers', ['email' => 'not-an-email']);
    }

    public function test_missing_email_fails_validation(): void
    {
        $response = $this->post('/newsletter/subscribe', []);
        $response->assertSessionHasErrors('email');
    }

    public function test_duplicate_subscription_is_handled_gracefully(): void
    {
        NewsletterSubscriber::create([
            'email'             => 'existing@example.com',
            'confirm_token'     => 'conf-abc123',
            'unsubscribe_token' => 'unsub-abc123',
            'confirmed_at'      => now(),
        ]);

        // Second subscribe with same email should not crash
        $response = $this->post('/newsletter/subscribe', [
            'email' => 'existing@example.com',
        ]);

        $response->assertRedirect();
    }

    public function test_confirm_endpoint_with_valid_token(): void
    {
        NewsletterSubscriber::create([
            'email'             => 'confirm@example.com',
            'confirm_token'     => 'valid-token-xyz',
            'unsubscribe_token' => 'unsub-xyz-999',
        ]);

        $response = $this->get('/newsletter/confirm/valid-token-xyz');
        $response->assertStatus(200);

        $this->assertNotNull(
            NewsletterSubscriber::where('email', 'confirm@example.com')->first()->confirmed_at
        );
    }

    public function test_unsubscribe_endpoint_with_valid_token(): void
    {
        NewsletterSubscriber::create([
            'email'             => 'unsub@example.com',
            'confirm_token'     => 'conf-unsub-abc',
            'unsubscribe_token' => 'unsub-token-abc',
            'confirmed_at'      => now(),
        ]);

        $response = $this->get('/newsletter/unsubscribe/unsub-token-abc');
        $response->assertStatus(200);

        $this->assertNotNull(
            NewsletterSubscriber::where('email', 'unsub@example.com')->first()->unsubscribed_at
        );
    }
}
