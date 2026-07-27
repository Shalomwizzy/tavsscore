<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TennisPrediction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'tennis_match_id', 'player_one_win_prob', 'player_two_win_prob',
        'predicted_winner', 'confidence', 'features', 'ai_panel', 'analysis', 'was_correct',
    ];
    protected $casts = [
        'player_one_win_prob' => 'float', 'player_two_win_prob' => 'float',
        'confidence' => 'integer', 'features' => 'array', 'ai_panel' => 'array', 'was_correct' => 'boolean',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(TennisMatch::class, 'tennis_match_id');
    }
}
