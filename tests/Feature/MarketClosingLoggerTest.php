<?php

namespace Tests\Feature;

use App\Models\FootballMatch;
use App\Models\PredictionLog;
use App\Services\MarketClosingLogger;
use App\Services\OddsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MarketClosingLoggerTest extends TestCase
{
    use RefreshDatabase;

    private function match(): FootballMatch
    {
        return FootballMatch::create([
            'api_id'         => 555001,
            'home_team'      => 'Team A',
            'away_team'      => 'Team B',
            'league'         => 'Test League',
            'league_id'      => 42,
            'league_country' => 'Nigeria',
            'status'         => 'NS',
            'match_time'     => now()->addHours(2),
        ]);
    }

    public function test_writes_three_market_rows_from_odds(): void
    {
        $match = $this->match();

        $odds = Mockery::mock(OddsService::class);
        $odds->shouldReceive('normalisedImpliedProbabilities')
            ->once()->with(Mockery::on(fn ($m) => $m->id === $match->id))
            ->andReturn([
                'home_win'    => 0.55,
                'draw'        => 0.25,
                'away_win'    => 0.20,
                'over_25'     => 0.60,
                'btts'        => 0.58,
                'sample_size' => 12,
            ]);

        $written = (new MarketClosingLogger($odds))->logForMatch($match);

        $this->assertSame(3, $written);

        $rows = PredictionLog::where('model_version', MarketClosingLogger::MODEL_VERSION)->get()->keyBy('market');

        $this->assertEqualsWithDelta(0.55, (float) $rows['1X2']->p_outcome, 0.0001);
        $this->assertSame('Home Win', $rows['1X2']->predicted_outcome);
        $this->assertEqualsWithDelta(0.60, (float) $rows['over25']->p_outcome, 0.0001);
        $this->assertEqualsWithDelta(0.58, (float) $rows['gg']->p_outcome, 0.0001);

        foreach ($rows as $row) {
            $this->assertSame(PredictionLog::STAGE_PRE_LINEUP, $row->prediction_stage);
            $this->assertFalse((bool) $row->is_backfill);
            $this->assertNull($row->prediction_id);
        }
    }

    public function test_is_idempotent_on_repeat_calls(): void
    {
        $match = $this->match();

        $odds = Mockery::mock(OddsService::class);
        $odds->shouldReceive('normalisedImpliedProbabilities')
            ->twice()
            ->andReturn([
                'home_win' => 0.55, 'draw' => 0.25, 'away_win' => 0.20,
                'over_25'  => 0.60, 'btts' => 0.58, 'sample_size' => 12,
            ]);

        $logger = new MarketClosingLogger($odds);
        $logger->logForMatch($match);
        $logger->logForMatch($match);

        $this->assertSame(3, PredictionLog::where('model_version', MarketClosingLogger::MODEL_VERSION)->count());
    }

    public function test_updates_probabilities_when_odds_move(): void
    {
        $match = $this->match();

        $odds = Mockery::mock(OddsService::class);
        $odds->shouldReceive('normalisedImpliedProbabilities')
            ->twice()
            ->andReturn(
                ['home_win' => 0.55, 'draw' => 0.25, 'away_win' => 0.20, 'over_25' => 0.60, 'btts' => 0.58, 'sample_size' => 12],
                ['home_win' => 0.40, 'draw' => 0.30, 'away_win' => 0.30, 'over_25' => 0.55, 'btts' => 0.52, 'sample_size' => 12],
            );

        $logger = new MarketClosingLogger($odds);
        $logger->logForMatch($match);
        $logger->logForMatch($match);

        $row = PredictionLog::where('model_version', MarketClosingLogger::MODEL_VERSION)
            ->where('market', '1X2')->first();

        // After the second call the argmax is still Home Win, but at a lower prob
        $this->assertEqualsWithDelta(0.40, (float) $row->p_outcome, 0.0001);
    }

    public function test_returns_zero_when_odds_unavailable(): void
    {
        $match = $this->match();

        $odds = Mockery::mock(OddsService::class);
        $odds->shouldReceive('normalisedImpliedProbabilities')->once()->andReturn(null);

        $written = (new MarketClosingLogger($odds))->logForMatch($match);

        $this->assertSame(0, $written);
        $this->assertSame(0, PredictionLog::count());
    }
}
