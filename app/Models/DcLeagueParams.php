<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DcLeagueParams extends Model
{
    protected $table = 'dc_league_params';
    public const UPDATED_AT = null;

    protected $fillable = [
        'league_id', 'model_version',
        'gamma', 'rho', 'half_life_days',
        'fit_at', 'training_start', 'training_end',
        'training_matches', 'final_log_likelihood', 'iterations', 'converged',
    ];

    protected $casts = [
        'gamma'                => 'float',
        'rho'                  => 'float',
        'half_life_days'       => 'float',
        'fit_at'               => 'datetime',
        'training_start'       => 'date',
        'training_end'         => 'date',
        'training_matches'     => 'integer',
        'final_log_likelihood' => 'float',
        'iterations'           => 'integer',
        'converged'            => 'boolean',
        'created_at'           => 'datetime',
    ];

    public function teams(): HasMany
    {
        return $this->hasMany(DcTeamParams::class, 'league_id', 'league_id')
            ->where('model_version', $this->model_version);
    }
}
