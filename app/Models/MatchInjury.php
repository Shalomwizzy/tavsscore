<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchInjury extends Model
{
    protected $fillable = [
        'match_id', 'team_api_id', 'team_name',
        'player_api_id', 'player_name', 'player_photo', 'type', 'reason',
    ];

    protected $casts = [
        'match_id'      => 'integer',
        'team_api_id'   => 'integer',
        'player_api_id' => 'integer',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class, 'match_id');
    }
}
