<?php

namespace Tests\Feature;

use App\Models\FootballMatch;
use App\Models\Prediction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression: specialty pages must grade their OWN market from the score, not
 * the shared was_correct column (which tracks the headline predicted_outcome).
 * A 2-0 is a GG loss even when the headline "Home Win" was correct; a 1-1 is a
 * GG win even when the headline lost.
 */
class SpecialtyGradingTest extends TestCase
{
    use RefreshDatabase;

    private function match(int $home, int $away): FootballMatch
    {
        return FootballMatch::create([
            'api_id'         => rand(10000, 99999),
            'home_team'      => 'Alpha FC',
            'away_team'      => 'Beta FC',
            'league'         => 'Premier League',
            'league_country' => 'England',
            'status'         => 'FT',
            'match_time'     => now('Africa/Lagos')->setTime(15, 0),
            'home_score'     => $home,
            'away_score'     => $away,
        ]);
    }

    private function ggPick(FootballMatch $m, string $headline, bool $wasCorrect): Prediction
    {
        return Prediction::create([
            'match_id'          => $m->id,
            'home_win_prob'     => 55.0,
            'draw_prob'         => 25.0,
            'away_win_prob'     => 20.0,
            'predicted_outcome' => $headline,
            'confidence'        => 72,
            'analysis'          => 'Test analysis for grading.',
            'is_gg_pick'        => true,
            'gg_rank'           => 1,
            'was_correct'       => $wasCorrect, // headline result — must be ignored by the GG page
        ]);
    }

    public function test_gg_pick_on_2_0_is_shown_as_loss_even_when_headline_won(): void
    {
        // Headline "Home Win" was correct (2-0) and stored was_correct=true,
        // but only one team scored → GG must render as a LOSS.
        $m = $this->match(2, 0);
        $this->ggPick($m, 'Home Win', true);

        $res = $this->get('/gg-picks?date=' . now('Africa/Lagos')->toDateString());
        $res->assertOk();
        $res->assertSee('result-loss');       // card graded as a loss
        $res->assertSee('❌');                 // loss badge, not the ✅ won badge
    }

    public function test_gg_pick_on_1_1_is_shown_as_win_even_when_headline_lost(): void
    {
        // Headline "Home Win" was wrong (1-1 draw) and stored was_correct=false,
        // but both teams scored → GG must render as a WIN.
        $m = $this->match(1, 1);
        $this->ggPick($m, 'Home Win', false);

        $res = $this->get('/gg-picks?date=' . now('Africa/Lagos')->toDateString());
        $res->assertOk();
        $res->assertSee('result-win');
    }

    public function test_draw_pick_grades_from_score_not_headline(): void
    {
        // Headline "Over 2.5 Goals" won on a 3-3, but a 3-3 IS a draw → draw win.
        $m = $this->match(3, 3);
        Prediction::create([
            'match_id'          => $m->id,
            'home_win_prob'     => 33.0,
            'draw_prob'         => 34.0,
            'away_win_prob'     => 33.0,
            'predicted_outcome' => 'Over 2.5 Goals',
            'confidence'        => 60,
            'analysis'          => 'Test analysis for grading.',
            'is_draw_pick'      => true,
            'draw_rank'         => 1,
            'was_correct'       => true, // headline (Over 2.5) result — ignored by draw page
        ]);

        $res = $this->get('/draw-picks?date=' . now('Africa/Lagos')->toDateString());
        $res->assertOk();
        $res->assertSee('result-win');
    }
}
