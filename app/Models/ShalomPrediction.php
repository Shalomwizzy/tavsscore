<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShalomPrediction extends Model
{
    protected $fillable = [
        'match_id', 'model_version', 'home_win_probability', 'draw_probability',
        'away_win_probability', 'over_25_probability', 'btts_probability',
        'predicted_outcome', 'confidence', 'explanation', 'is_shadow',
        'was_correct', 'settled_at',
    ];

    protected $casts = [
        'home_win_probability' => 'float', 'draw_probability' => 'float',
        'away_win_probability' => 'float', 'over_25_probability' => 'float',
        'btts_probability' => 'float', 'confidence' => 'integer',
        'explanation' => 'array', 'is_shadow' => 'boolean',
        'was_correct' => 'boolean', 'settled_at' => 'datetime',
    ];

    public function match(): BelongsTo { return $this->belongsTo(FootballMatch::class, 'match_id'); }
}
