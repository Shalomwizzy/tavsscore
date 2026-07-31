<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TennisMatch extends Model
{
    protected $fillable = [
        'source', 'source_key', 'tour', 'tournament', 'surface', 'match_date', 'scheduled_at',
        'round', 'best_of', 'player_one', 'player_two', 'winner',
        'player_one_country', 'player_two_country',
        'player_one_rank', 'player_two_rank', 'score', 'status', 'stats',
    ];

    protected $casts = ['match_date' => 'date', 'scheduled_at' => 'datetime', 'stats' => 'array'];

    public function prediction(): HasOne
    {
        return $this->hasOne(TennisPrediction::class);
    }
}
