<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetricsSnapshot extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'period_start', 'model_version', 'market', 'league_id',
        'n', 'wins', 'brier', 'log_loss',
    ];

    protected $casts = [
        'period_start' => 'date',
        'n'            => 'integer',
        'wins'         => 'integer',
        'league_id'    => 'integer',
        'brier'        => 'float',
        'log_loss'     => 'float',
        'created_at'   => 'datetime',
    ];
}
