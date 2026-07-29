<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\FootballMatch;
use App\Models\Prediction;
use App\Models\RolloverChallenge;
use App\Models\RolloverPick;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    // ── Home ────────────────────────────────────────────────────────

    public function test_home_page_loads(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    public function test_home_page_shows_top_pick_when_exists(): void
    {
        $match = FootballMatch::create([
            'api_id'     => 1,
            'league'     => 'Premier League',
            'league_country' => 'England',
            'home_team'  => 'Arsenal',
            'away_team'  => 'Chelsea',
            'status'     => 'NS',
            'match_time' => now('Africa/Lagos')->setTime(15, 0),
        ]);

        Prediction::create([
            'match_id'          => $match->id,
            'home_win_prob'     => 55.00,
            'draw_prob'         => 25.00,
            'away_win_prob'     => 20.00,
            'predicted_outcome' => 'Home Win',
            'confidence'        => 78,
            'is_daily_pick'     => true,
            'pick_rank'         => 1,
            'analysis'          => 'AI analysis text',
        ]);

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Arsenal');
    }

    public function test_home_page_shows_rollover_when_active(): void
    {
        RolloverChallenge::create([
            'started_at'    => now()->toDateString(),
            'initial_stake' => 10000,
            'status'        => 'active',
        ]);

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Rollover');
    }

    // ── Live ─────────────────────────────────────────────────────────

    public function test_live_scores_page_loads(): void
    {
        $this->get('/live')->assertStatus(200);
    }

    public function test_tennis_page_loads(): void
    {
        $this->get(route('tennis.index'))->assertOk();
    }

    // ── Predictions ───────────────────────────────────────────────────

    public function test_predictions_index_loads(): void
    {
        $this->get('/predictions')->assertStatus(200);
    }

    public function test_prediction_show_page_loads(): void
    {
        $match = FootballMatch::create([
            'api_id'       => 99,
            'league'       => 'La Liga',
            'league_country' => 'Spain',
            'home_team'    => 'Real Madrid',
            'away_team'    => 'Barcelona',
            'status'       => 'NS',
            'match_time'   => now()->addHours(5),
        ]);

        Prediction::create([
            'match_id'          => $match->id,
            'home_win_prob'     => 50.00,
            'draw_prob'         => 25.00,
            'away_win_prob'     => 25.00,
            'predicted_outcome' => 'Home Win',
            'confidence'        => 72,
            'analysis'          => 'Match preview analysis.',
        ]);

        $this->get('/predictions/' . $match->slug)->assertStatus(200);
    }

    public function test_prediction_show_returns_404_for_unknown_slug(): void
    {
        $this->get('/predictions/nonexistent-match-00000')->assertStatus(404);
    }

    // ── Picks ──────────────────────────────────────────────────────────

    public function test_picks_page_loads(): void
    {
        $this->get('/picks')->assertStatus(200);
    }

    public function test_lineup_picks_page_loads(): void
    {
        $this->get('/lineup-picks')->assertStatus(200);
    }

    public function test_correct_score_page_loads(): void
    {
        $this->get('/correct-score')->assertStatus(200);
    }

    // ── Rollover ──────────────────────────────────────────────────────

    public function test_rollover_index_loads(): void
    {
        $this->get('/rollover')->assertStatus(200);
    }

    public function test_specialty_goal_and_handicap_pages_load(): void
    {
        $this->get(route('under35-picks.index'))->assertOk();
        $this->get(route('under45-picks.index'))->assertOk();
        $this->get(route('handicap-picks.index'))->assertOk();
    }

    public function test_rollover_show_loads_for_valid_date(): void
    {
        $challenge = RolloverChallenge::create([
            'started_at'    => now()->toDateString(),
            'initial_stake' => 10000,
            'status'        => 'active',
        ]);

        $match = FootballMatch::create([
            'api_id'       => 200,
            'league'       => 'Bundesliga',
            'league_country' => 'Germany',
            'home_team'    => 'Bayern Munich',
            'away_team'    => 'Dortmund',
            'status'       => 'FT',
            'home_score'   => 2,
            'away_score'   => 1,
            'match_time'   => now(),
        ]);

        RolloverPick::create([
            'challenge_id'    => $challenge->id,
            'match_id'        => $match->id,
            'day_number'      => 1,
            'pick_date'       => now()->toDateString(),
            'implied_odds'    => 1.30,
            'stake_amount'    => 10000,
            'potential_return' => 13000,
            'groq_verdict'    => 'Home Win',
            'both_agree'      => true,
            'status'          => 'pending',
        ]);

        $this->get('/rollover/' . now()->toDateString())->assertStatus(200);
    }

    // ── Stats / Results ─────────────────────────────────────────────

    public function test_stats_page_loads(): void
    {
        $this->get('/stats')->assertStatus(200);
    }

    public function test_results_page_loads(): void
    {
        $this->get('/results')->assertStatus(200);
    }

    public function test_removed_africa_page_returns_not_found(): void
    {
        $this->get('/africa')->assertNotFound();
    }

    // ── Winners / Hall of Fame ──────────────────────────────────────

    public function test_winners_page_loads(): void
    {
        $this->get('/winners')->assertStatus(200);
    }

    public function test_hall_of_fame_page_loads(): void
    {
        $this->get('/hall-of-fame')->assertStatus(200);
    }

    // ── Blog ───────────────────────────────────────────────────────

    public function test_blog_index_loads(): void
    {
        $baseUrl = config('app.url');

        $this->get('/football-news')
            ->assertStatus(200)
            ->assertSee('name="robots" content="index, follow', false)
            ->assertSee('rel="canonical" href="' . $baseUrl . '/football-news"', false);
    }

    public function test_blog_show_loads_for_published_post(): void
    {
        $baseUrl = config('app.url');

        $post = BlogPost::create([
            'title'        => 'Test football post',
            'slug'         => 'test-football-post',
            'content'      => 'Some long content here for a football analysis article.',
            'is_published' => true,
            'published_at' => now()->subDay(),
            'image_path'   => 'images/blog/test-football-post.png',
        ]);

        $this->get($post->public_path)
            ->assertStatus(200)
            ->assertSee('property="og:type"        content="article"', false)
            ->assertSee($baseUrl . '/images/blog/test-football-post.png', false);

        $this->get('/blog/' . $post->slug)
            ->assertStatus(301)
            ->assertRedirect($post->public_url);
    }

    // ── Static pages ────────────────────────────────────────────────

    public function test_about_page_loads(): void
    {
        $this->get('/about')->assertStatus(200);
    }

    public function test_privacy_page_loads(): void
    {
        $this->get('/privacy')->assertStatus(200);
    }

    public function test_terms_page_loads(): void
    {
        $this->get('/terms')->assertStatus(200);
    }

    public function test_contact_page_loads(): void
    {
        $this->get('/contact')->assertStatus(200);
    }

    // ── SEO ──────────────────────────────────────────────────────────

    public function test_sitemap_returns_xml(): void
    {
        $baseUrl = config('app.url');
        $response = $this->get('/sitemap.xml');
        $response->assertStatus(200);
        $this->assertStringContainsString('xml', $response->headers->get('Content-Type'));
        $response->assertSee($baseUrl . '/football-news', false);
    }

    public function test_legacy_blog_index_permanently_redirects_to_football_news(): void
    {
        $this->get('/blog?category=Football%20News')
            ->assertStatus(301)
            ->assertRedirect(url('/football-news?category=Football%20News'));
    }

    public function test_robots_txt_loads(): void
    {
        $response = $this->get('/robots.txt');
        $response->assertStatus(200);
    }
}
