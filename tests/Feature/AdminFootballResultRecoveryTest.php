<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PendingPredictionRecoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFootballResultRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Football Results Admin',
            'email' => 'football-results-admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
    }

    public function test_football_matches_page_has_a_past_result_check_button(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.matches'))
            ->assertOk()
            ->assertSee('Check past Football results');
    }

    public function test_admin_can_trigger_football_result_recovery(): void
    {
        $recovery = new class extends PendingPredictionRecoveryService
        {
            public function recoverFootball(): array
            {
                return ['settled' => 4, 'pending' => 2, 'warnings' => []];
            }
        };
        $this->app->instance(PendingPredictionRecoveryService::class, $recovery);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.check-past-results'))
            ->assertRedirect()
            ->assertSessionHas('success', 'Football result check finished: 4 prediction(s) settled; 2 remain pending because no verified final score is available yet.');
    }
}
