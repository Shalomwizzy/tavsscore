<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Models\XPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminXPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'x-admin@example.com',
            'password' => bcrypt('password'), 'role' => 'admin',
        ]);
    }

    public function test_panel_loads_with_post_history(): void
    {
        XPost::create(['kind' => 'growth', 'text' => 'Arsenal vs Chelsea today', 'tweet_id' => '123', 'status' => 'posted']);

        $this->actingAs($this->admin())
            ->get('/admin/x')
            ->assertStatus(200)
            ->assertSee('Arsenal vs Chelsea today')
            ->assertSee('Football growth posts');
    }

    public function test_toggle_disables_growth_posts(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/x/toggle', ['enabled' => '0'])
            ->assertRedirect();

        $this->assertSame('0', Setting::get('x_growth_enabled'));
    }
}
