<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DcTeamParams extends Model
{
    protected $table = 'dc_team_params';
    public const UPDATED_AT = null;

    protected $fillable = [
        'league_id', 'model_version', 'team_name',
        'attack', 'defense', 'matches_used', 'is_shrunk',
    ];

    protected $casts = [
        'attack'       => 'float',
        'defense'      => 'float',
        'matches_used' => 'integer',
        'is_shrunk'    => 'boolean',
        'created_at'   => 'datetime',
    ];
}
