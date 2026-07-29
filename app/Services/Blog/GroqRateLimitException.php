<?php

namespace App\Services\Blog;

use RuntimeException;

class GroqRateLimitException extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds = 60)
    {
        parent::__construct('Groq rate limit reached.');
    }
}
