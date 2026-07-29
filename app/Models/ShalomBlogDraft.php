<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShalomBlogDraft extends Model
{
    protected $fillable = ['match_id', 'shalom_prediction_id', 'title', 'excerpt', 'content', 'status', 'generated_at'];
    protected $casts = ['generated_at' => 'datetime'];
    public function match(): BelongsTo { return $this->belongsTo(FootballMatch::class, 'match_id'); }
    public function prediction(): BelongsTo { return $this->belongsTo(ShalomPrediction::class, 'shalom_prediction_id'); }
}
