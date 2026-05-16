<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalibrationSnapshot extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'period_label',
        'threshold',
        'acc_pct',
        'total_picks',
        'calibration_error_avg',
        'cold_markets_count',
    ];

    protected $casts = [
        'threshold'             => 'integer',
        'acc_pct'               => 'decimal:2',
        'total_picks'           => 'integer',
        'calibration_error_avg' => 'decimal:2',
        'cold_markets_count'    => 'integer',
        'created_at'            => 'datetime',
    ];
}
