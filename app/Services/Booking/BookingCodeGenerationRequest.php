<?php

namespace App\Services\Booking;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * A small, durable hand-off between the authenticated admin desk (Hostinger)
 * and the local Mac worker. The server never opens SportyBet or creates a
 * booking code; it only queues a request for the Mac browser worker.
 */
class BookingCodeGenerationRequest
{
    private const CACHE_KEY = 'booking_code_generation_request';

    /** @return array{id:string,requested_at:string} */
    public function request(): array
    {
        $request = [
            'id' => (string) Str::uuid(),
            'requested_at' => now('Africa/Lagos')->toIso8601String(),
        ];

        Cache::put(self::CACHE_KEY, $request, now()->addHours(4));

        return $request;
    }

    /** @return array{id:string,requested_at:string}|null */
    public function pending(): ?array
    {
        $request = Cache::get(self::CACHE_KEY);

        return is_array($request) && filled($request['id'] ?? null) ? $request : null;
    }

    /** Only complete the exact request the Mac worker collected. */
    public function complete(?string $id): bool
    {
        $request = $this->pending();
        if (! $request || blank($id) || ! hash_equals((string) $request['id'], (string) $id)) {
            return false;
        }

        Cache::forget(self::CACHE_KEY);

        return true;
    }
}
