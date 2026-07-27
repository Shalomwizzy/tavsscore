<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingCode;
use App\Services\Booking\BetslipSpecService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints the external automation worker talks to (behind worker.token):
 *  - GET  betslip-spec  → today's selections to build codes for
 *  - POST booking-codes → store a generated code + shareable link
 *
 * The worker never receives or needs any user data. Codes are public betslips.
 */
class BookingWorkerController extends Controller
{
    public function spec(BetslipSpecService $specs): JsonResponse
    {
        return response()->json($specs->today());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'platform'   => ['required', 'string', 'max:40'],
            'code'       => ['required', 'string', 'max:60'],
            'link'       => ['nullable', 'url', 'max:500'],
            'slip_ref'   => ['nullable', 'string', 'max:60'],
            'fixtures'   => ['nullable', 'array'],
            'total_odds' => ['nullable', 'numeric', 'min:1'],
            'status'     => ['nullable', 'in:pending,published,failed,expired'],
            'note'       => ['nullable', 'string', 'max:500'],
            'pick_date'  => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $platform = strtolower(trim($data['platform']));
        $pickDate = $data['pick_date'] ?? now('Africa/Lagos')->toDateString();

        // Idempotent per platform + slip + day so re-runs update rather than duplicate.
        $code = BookingCode::query()->updateOrCreate(
            [
                'platform'  => $platform,
                'slip_ref'  => $data['slip_ref'] ?? null,
                'pick_date' => $pickDate,
            ],
            [
                'code'       => $data['code'],
                'link'       => $data['link'] ?? null,
                'fixtures'   => $data['fixtures'] ?? null,
                'total_odds' => $data['total_odds'] ?? null,
                'status'     => $data['status'] ?? 'published',
                'note'       => $data['note'] ?? null,
                'source'     => 'auto',
                'expires_at' => $data['expires_at'] ?? null,
            ]
        );

        return response()->json(['ok' => true, 'id' => $code->id], 201);
    }
}
