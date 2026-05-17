<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RolloverPick extends Model
{
    protected $fillable = [
        'challenge_id', 'match_id', 'prediction_id',
        'day_number', 'pick_date',
        'implied_odds', 'stake_amount', 'potential_return',
        'was_correct', 'result_score',
        'groq_verdict', 'gemini_verdict', 'mistral_verdict', 'both_agree',
        'status', 'winner_reminder_sent',
    ];

    protected $casts = [
        'pick_date'       => 'date',
        'was_correct'     => 'boolean',
        'both_agree'           => 'boolean',
        'winner_reminder_sent' => 'boolean',
        'implied_odds'    => 'decimal:2',
        'stake_amount'    => 'decimal:2',
        'potential_return' => 'decimal:2',
    ];

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(RolloverChallenge::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class);
    }

    public function prediction(): BelongsTo
    {
        return $this->belongsTo(Prediction::class);
    }
}
