<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerStatistic extends Model
{
    protected $fillable = [
        'player_api_id', 'player_name', 'player_photo', 'age', 'nationality',
        'team_api_id', 'team_name', 'league_id', 'season',
        'position', 'appearances', 'lineups', 'minutes', 'goals', 'assists',
        'yellow_cards', 'red_cards', 'rating', 'raw',
    ];

    protected $casts = [
        'player_api_id' => 'integer',
        'team_api_id'   => 'integer',
        'league_id'     => 'integer',
        'season'        => 'integer',
        'age'           => 'integer',
        'appearances'   => 'integer',
        'lineups'       => 'integer',
        'minutes'       => 'integer',
        'goals'         => 'integer',
        'assists'       => 'integer',
        'yellow_cards'  => 'integer',
        'red_cards'     => 'integer',
        'rating'        => 'float',
        'raw'           => 'array',
    ];
}
