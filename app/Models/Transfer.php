<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transfer extends Model
{
    protected $fillable = [
        'player_api_id', 'player_name', 'transfer_date', 'type',
        'team_in_id', 'team_in_name', 'team_out_id', 'team_out_name',
    ];

    protected $casts = [
        'player_api_id' => 'integer',
        'team_in_id'    => 'integer',
        'team_out_id'   => 'integer',
        'transfer_date' => 'date',
    ];
}
