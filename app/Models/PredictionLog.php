<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictionLog extends Model
{
    public const UPDATED_AT = null;

    public const RESULT_WIN = 'WIN';

    public const RESULT_LOSS = 'LOSS';

    public const RESULT_VOID = 'VOID';

    public const STAGE_PRE_LINEUP = 'pre_lineup';

    public const STAGE_POST_LINEUP = 'post_lineup';

    public const MARKET_1X2 = '1X2';

    public const MARKET_DRAW = 'draw';

    public const MARKET_GG = 'gg';

    public const MARKET_OVER15 = 'over15';

    public const MARKET_OVER25 = 'over25';

    public const MARKET_OVER35 = 'over35';

    public const MARKET_UNDER35 = 'under35';

    public const MARKET_UNDER45 = 'under45';

    public const MARKET_ASIAN_HANDICAP = 'asian_handicap';

    public const MARKET_EUROPEAN_HANDICAP = 'european_handicap';

    public const MARKET_DOUBLE_CHANCE = 'double_chance';

    public const MARKET_TEAM3PLUS = 'team3plus';

    public const MARKET_CORRECT_SCORE = 'correct_score';

    protected $fillable = [
        'prediction_id',
        'match_id',
        'league_id',
        'market',
        'predicted_outcome',
        'p_outcome',
        'p_home',
        'p_draw',
        'p_away',
        'model_version',
        'prediction_stage',
        'is_backfill',
        'kickoff_at',
        'actual_result',
        'settled_at',
    ];

    protected $casts = [
        'p_outcome' => 'decimal:5',
        'p_home' => 'decimal:5',
        'p_draw' => 'decimal:5',
        'p_away' => 'decimal:5',
        'is_backfill' => 'boolean',
        'kickoff_at' => 'datetime',
        'created_at' => 'datetime',
        'settled_at' => 'datetime',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class, 'match_id');
    }

    public function prediction(): BelongsTo
    {
        return $this->belongsTo(Prediction::class, 'prediction_id');
    }
}
