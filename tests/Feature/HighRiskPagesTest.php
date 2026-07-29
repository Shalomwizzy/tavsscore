<?php

namespace Tests\Feature;

use App\Models\BookingCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HighRiskPagesTest extends TestCase
{
    use RefreshDatabase;

    private function highRiskTicket(string $status = 'published'): BookingCode
    {
        return BookingCode::create([
            'platform' => 'sportybet',
            'code' => 'RISK22',
            'slip_ref' => 'high-risk',
            'total_odds' => 128.75,
            'status' => $status,
            'pick_date' => now('Africa/Lagos')->toDateString(),
            'fixtures' => [[
                'home' => 'Arsenal',
                'away' => 'Chelsea',
                'market' => 'Over 2.5 Goals',
                'model_prob' => 58,
                'est_odds' => 1.72,
            ]],
            'settled_at' => in_array($status, ['won', 'lost'], true) ? now() : null,
        ]);
    }

    public function test_public_high_risk_page_shows_a_live_ticket_and_copy_control(): void
    {
        $this->highRiskTicket();

        $this->get(route('high-risk.index'))
            ->assertOk()
            ->assertSee('RISK22')
            ->assertSee('Copy code')
            ->assertSee('Arsenal');
    }

    public function test_admin_high_risk_page_is_available_to_admins(): void
    {
        $this->highRiskTicket();
        $admin = User::create([
            'name' => 'Risk Admin',
            'email' => 'risk-admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.high-risk.index'))
            ->assertOk()
            ->assertSee('High Risk Desk')
            ->assertSee('RISK22');
    }
}
