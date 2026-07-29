<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Booking\BookingCodeGenerationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    // ── Authentication guards ──────────────────────────────────────

    public function test_admin_dashboard_redirects_guests(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_admin_predictions_redirects_guests(): void
    {
        $this->get('/admin/predictions')->assertRedirect('/admin/login');
    }

    public function test_admin_matches_redirects_guests(): void
    {
        $this->get('/admin/matches')->assertRedirect('/admin/login');
    }

    public function test_admin_picks_redirects_guests(): void
    {
        $this->get('/admin/picks')->assertRedirect('/admin/login');
    }

    public function test_admin_rollover_redirects_guests(): void
    {
        $this->get('/admin/rollover')->assertRedirect('/admin/login');
    }

    public function test_admin_lineup_picks_redirects_guests(): void
    {
        $this->get('/admin/lineup-picks')->assertRedirect('/admin/login');
    }

    public function test_admin_correct_score_redirects_guests(): void
    {
        $this->get('/admin/correct-score')->assertRedirect('/admin/login');
    }

    public function test_admin_corner_picks_redirects_guests(): void
    {
        $this->get('/admin/corners')->assertRedirect('/admin/login');
    }

    public function test_admin_draw_picks_redirects_guests(): void
    {
        $this->get('/admin/draw-picks')->assertRedirect('/admin/login');
    }

    public function test_admin_gg_picks_redirects_guests(): void
    {
        $this->get('/admin/gg-picks')->assertRedirect('/admin/login');
    }

    public function test_admin_stats_redirects_guests(): void
    {
        $this->get('/admin/stats')->assertRedirect('/admin/login');
    }

    public function test_admin_winners_redirects_guests(): void
    {
        $this->get('/admin/winners')->assertRedirect('/admin/login');
    }

    public function test_admin_newsletter_redirects_guests(): void
    {
        $this->get('/admin/newsletter')->assertRedirect('/admin/login');
    }

    public function test_admin_blog_redirects_guests(): void
    {
        $this->get('/admin/blog')->assertRedirect('/admin/login');
    }

    public function test_admin_analytics_redirects_guests(): void
    {
        $this->get('/admin/analytics')->assertRedirect('/admin/login');
    }

    public function test_regular_user_cannot_access_admin(): void
    {
        $user = User::create([
            'name'     => 'Regular User',
            'email'    => 'user@example.com',
            'password' => bcrypt('password'),
            'role'     => 'user',
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirect('/admin/login');
    }

    // ── Admin login page ────────────────────────────────────────────

    public function test_admin_login_page_loads(): void
    {
        $this->get('/admin/login')->assertStatus(200);
    }

    public function test_invalid_login_returns_error(): void
    {
        $this->post('/admin/login', [
            'email'    => 'notreal@example.com',
            'password' => 'wrongpassword',
        ])->assertSessionHasErrors();
    }

    // ── Admin access for admin role ────────────────────────────────

    public function test_admin_user_can_access_dashboard(): void
    {
        $admin = User::create([
            'name'     => 'Admin User',
            'email'    => 'admin@example.com',
            'password' => bcrypt('password'),
            'role'     => 'admin',
        ]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertStatus(200);
    }

    public function test_admin_user_can_access_predictions(): void
    {
        $admin = User::create([
            'name'     => 'Admin User',
            'email'    => 'admin2@example.com',
            'password' => bcrypt('password'),
            'role'     => 'admin',
        ]);

        $this->actingAs($admin)
            ->get('/admin/predictions')
            ->assertStatus(200);
    }

    public function test_admin_user_can_access_matches(): void
    {
        $admin = User::create([
            'name'     => 'Admin User',
            'email'    => 'admin3@example.com',
            'password' => bcrypt('password'),
            'role'     => 'admin',
        ]);

        $this->actingAs($admin)
            ->get('/admin/matches')
            ->assertStatus(200);
    }

    public function test_admin_user_can_access_rollover(): void
    {
        $admin = User::create([
            'name'     => 'Admin User',
            'email'    => 'admin4@example.com',
            'password' => bcrypt('password'),
            'role'     => 'admin',
        ]);

        $this->actingAs($admin)
            ->get('/admin/rollover')
            ->assertStatus(200);
    }

    public function test_admin_user_can_access_specialty_pick_pages(): void
    {
        $admin = User::create([
            'name'     => 'Specialty Admin',
            'email'    => 'specialty-admin@example.com',
            'password' => bcrypt('password'),
            'role'     => 'admin',
        ]);

        foreach (['/admin/under35', '/admin/under45', '/admin/handicap', '/admin/european-handicap'] as $path) {
            $this->actingAs($admin)->get($path)->assertStatus(200)->assertSee("Today's", false);
        }

        $this->actingAs($admin)->get('/admin/corners')->assertStatus(200)->assertSee('Corner Intelligence', false);
    }

    public function test_admin_can_queue_booking_code_generation_for_mac_worker(): void
    {
        config(['services.booking_worker.token' => 'test-worker-token']);
        $admin = User::create([
            'name' => 'Booking Admin', 'email' => 'booking-admin@example.com',
            'password' => bcrypt('password'), 'role' => 'admin',
        ]);

        $this->actingAs($admin)->post('/admin/booking-code/generate')
            ->assertRedirect()->assertSessionHas('success');

        $this->assertNotNull(app(BookingCodeGenerationRequest::class)->pending());
    }

    public function test_admin_login_with_valid_credentials(): void
    {
        User::create([
            'name'     => 'Admin',
            'email'    => 'admin@test.com',
            'password' => bcrypt('secret123'),
            'role'     => 'admin',
        ]);

        $this->post('/admin/login', [
            'email'    => 'admin@test.com',
            'password' => 'secret123',
        ])->assertRedirect('/admin');
    }
}
