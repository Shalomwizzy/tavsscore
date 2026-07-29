<?php

namespace Tests\Feature;

use App\Models\FootballMatch;
use App\Models\Prediction;
use App\Models\PredictionLog;
use App\Services\PredictionLogger;
use App\Services\PublicationQualityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicationQualityServiceTest extends TestCase
{
    use RefreshDatabase;

    private int $logMatchSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        app(PublicationQualityService::class)->forget();
        config()->set('prediction.dc_enabled', false);
    }

    private function prediction(array $overrides = []): Prediction
    {
        $match = FootballMatch::create([
            'api_id' => 881001,
            'home_team' => 'Home FC',
            'away_team' => 'Away FC',
            'league' => 'Test League',
            'league_id' => 999,
            'league_country' => 'Test',
            'status' => 'NS',
            'match_time' => now()->addHours(3),
            'fixture_data_checked_at' => now(),
            'intel_checked_at' => now(),
        ]);

        return Prediction::create(array_merge([
            'match_id' => $match->id,
            'home_win_prob' => 72,
            'draw_prob' => 17,
            'away_win_prob' => 11,
            'predicted_outcome' => 'Home Win',
            'confidence' => 72,
            'analysis' => 'Verified pre-match analysis.',
        ], $overrides));
    }

    private function log(Prediction $prediction, string $market, float $probability, string $result): void
    {
        $sequence = ++$this->logMatchSequence;
        $match = FootballMatch::create([
            'api_id' => 990000 + $sequence,
            'home_team' => 'Logged Home '.$sequence,
            'away_team' => 'Logged Away '.$sequence,
            'league' => 'Test League',
            'league_id' => 999,
            'league_country' => 'Test',
            'status' => 'FT',
            'match_time' => now()->subDay(),
        ]);

        PredictionLog::create([
            'prediction_id' => null,
            'match_id' => $match->id,
            'league_id' => 999,
            'market' => $market,
            'predicted_outcome' => 'Home Win',
            'p_outcome' => $probability,
            'model_version' => PredictionLogger::VERSION_BASELINE,
            'prediction_stage' => PredictionLog::STAGE_PRE_LINEUP,
            'is_backfill' => false,
            'kickoff_at' => now()->subDay(),
            'actual_result' => $result,
            'settled_at' => now(),
        ]);
    }

    public function test_it_holds_a_materially_overconfident_band_when_sample_is_large_enough(): void
    {
        config()->set('prediction.dc_enabled', false);
        $prediction = $this->prediction();

        // 6/30 wins at a stated 72% is decisively over-confident.
        foreach (range(1, 30) as $i) {
            $this->log($prediction, PredictionLog::MARKET_1X2, 0.72, $i <= 6 ? PredictionLog::RESULT_WIN : PredictionLog::RESULT_LOSS);
        }

        $gate = app(PublicationQualityService::class);
        $result = $gate->evaluate($prediction->fresh('match'), PredictionLog::MARKET_1X2, 0.72, 'Home Win');

        $this->assertFalse($result['allowed']);
        $this->assertSame('held', $result['status']);
        $this->assertSame(30, $result['calibration_sample']);
        $this->assertLessThan(-0.07, $result['calibration_gap']);
    }

    public function test_it_keeps_a_new_market_in_shadow_instead_of_claiming_proven_accuracy(): void
    {
        config()->set('prediction.dc_enabled', false);
        $prediction = $this->prediction();

        $result = app(PublicationQualityService::class)->evaluate(
            $prediction->fresh('match'),
            PredictionLog::MARKET_OVER15,
            0.84,
            'Over 1.5 Goals',
        );

        $this->assertTrue($result['allowed']);
        $this->assertSame('shadow', $result['status']);
        $this->assertSame(0, $result['calibration_sample']);
    }

    public function test_it_holds_a_supported_market_without_a_three_point_captured_odds_edge(): void
    {
        config()->set('prediction.dc_enabled', false);
        $prediction = $this->prediction([
            'opening_odds' => ['home_win' => 70, 'draw' => 18, 'away_win' => 12],
        ]);

        $result = app(PublicationQualityService::class)->evaluate(
            $prediction->fresh('match'),
            PredictionLog::MARKET_1X2,
            0.72,
            'Home Win',
        );

        $this->assertFalse($result['allowed']);
        $this->assertSame('held', $result['status']);
        $this->assertEqualsWithDelta(0.02, $result['edge'], 0.0001);
    }

    public function test_scorecard_marks_a_large_well_calibrated_sample_as_proven(): void
    {
        config()->set('prediction.dc_enabled', false);
        $prediction = $this->prediction();

        foreach (range(1, 100) as $i) {
            $this->log($prediction, PredictionLog::MARKET_1X2, 0.70, $i <= 70 ? PredictionLog::RESULT_WIN : PredictionLog::RESULT_LOSS);
        }

        $rows = app(PublicationQualityService::class)->scorecard();
        $row = collect($rows)->firstWhere('market', PredictionLog::MARKET_1X2);

        $this->assertNotNull($row);
        $this->assertSame('proven', $row['state']);
        $this->assertSame(100, $row['settled_n']);
    }

    public function test_it_holds_a_pick_when_fixture_or_near_kickoff_intel_is_stale(): void
    {
        $prediction = $this->prediction();
        $prediction->match->update([
            'fixture_data_checked_at' => now()->subMinutes(91),
            'intel_checked_at' => now()->subHours(9),
        ]);

        $result = app(PublicationQualityService::class)->evaluate(
            $prediction->fresh('match'),
            PredictionLog::MARKET_1X2,
            0.72,
            'Home Win',
        );

        $this->assertFalse($result['allowed']);
        $this->assertSame('held', $result['status']);
        $this->assertFalse($result['freshness']['fresh']);
    }

    public function test_it_uses_a_smaller_league_sample_when_local_calibration_is_poor(): void
    {
        $prediction = $this->prediction();
        foreach (range(1, 15) as $i) {
            $this->log($prediction, PredictionLog::MARKET_1X2, 0.72, $i <= 3 ? PredictionLog::RESULT_WIN : PredictionLog::RESULT_LOSS);
        }

        $result = app(PublicationQualityService::class)->evaluate(
            $prediction->fresh('match'),
            PredictionLog::MARKET_1X2,
            0.72,
            'Home Win',
        );

        $this->assertFalse($result['allowed']);
        $this->assertSame('league', $result['calibration_scope']);
        $this->assertSame(15, $result['calibration_sample']);
    }

    public function test_logger_records_a_selected_specialty_market_as_its_own_experiment(): void
    {
        $prediction = $this->prediction([
            'market_board' => ['Under 3.5 Goals' => 92],
            'is_under35_pick' => true,
        ]);

        app(PredictionLogger::class)->logLive($prediction->fresh('match'));

        $row = PredictionLog::query()
            ->where('market', PredictionLog::MARKET_UNDER35)
            ->where('match_id', $prediction->match_id)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('Under 3.5 Goals', $row->predicted_outcome);
        $this->assertEqualsWithDelta(0.92, (float) $row->p_outcome, 0.0001);
    }
}
