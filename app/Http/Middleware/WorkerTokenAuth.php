<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the external booking-code automation worker via a shared secret
 * sent in the X-Worker-Token header (compared to services.booking_worker.token).
 */
class WorkerTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.booking_worker.token');
        $given    = (string) $request->header('X-Worker-Token', '');

        if ($expected === '' || ! hash_equals($expected, $given)) {
            abort(401, 'Invalid worker token.');
        }

        return $next($request);
    }
}
