<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelRun extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'model_version',
        'trained_at',
        'training_data_start',
        'training_data_end',
        'hyperparameters',
        'notes',
    ];

    protected $casts = [
        'trained_at'          => 'datetime',
        'training_data_start' => 'date',
        'training_data_end'   => 'date',
        'hyperparameters'     => 'array',
        'created_at'          => 'datetime',
    ];
}
