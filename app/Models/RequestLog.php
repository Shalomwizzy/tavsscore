<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'path', 'method', 'status_code', 'ip_hash', 'country',
        'user_agent', 'referer', 'is_bot',
    ];

    protected $casts = [
        'status_code' => 'integer',
        'is_bot'      => 'boolean',
        'created_at'  => 'datetime',
    ];
}
