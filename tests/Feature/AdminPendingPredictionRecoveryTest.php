<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PendingPredictionRecoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPendingPredictionRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Recovery Admin',
            'email' => 'recovery-admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
    }

    public function test_admin_daily_predictions_page_has_past_pending_settlement_control(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.daily-football-predictions.index'))
            ->assertOk()
            ->assertSee('Settle past pending');
    }

    public function test_admin_can_trigger_verified_past_pending_recovery(): void
    {
        $recovery = new class extends PendingPredictionRecoveryService
        {
            public function recover(): array
            {
                return [
                    'football_settled' => 2,
                    'tennis_settled' => 1,
                    'football_pending' => 1,
                    'tennis_pending' => 0,
                    'warnings' => [],
                ];
            }
        };
        $this->app->instance(PendingPredictionRecoveryService::class, $recovery);

        $this->actingAs($this->admin())
            ->post(route('admin.daily-football-predictions.settle-past'))
            ->assertRedirect()
            ->assertSessionHas('success', 'Past-result recovery finished: 3 prediction(s) settled; 1 remain pending because no verified final score is available yet.');
    }

    public function test_guests_cannot_trigger_past_pending_recovery(): void
    {
        $this->post(route('admin.daily-football-predictions.settle-past'))
            ->assertRedirect(route('admin.login'));
    }
}
