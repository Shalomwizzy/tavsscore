<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingCode;
use App\Models\Setting;
use App\Services\Booking\StakePlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;

/**
 * Personal auto-bet control desk. Sets the staking rules and the master arm
 * switch; the Mac worker reads this config and stakes each booking code on the
 * owner's own SportyBet account. Admin-only, single account, off by default.
 */
class AutoBetController extends Controller
{
    public function index(StakePlanService $stakePlan): View
    {
        $config = $stakePlan->config();
        $armed  = $stakePlan->isArmed();

        $today = now('Africa/Lagos')->toDateString();
        $preview = BookingCode::query()
            ->where('status', 'published')
            ->whereDate('pick_date', $today)
            ->orderByDesc('total_odds')
            ->get()
            ->map(fn (BookingCode $c) => [
                'code'  => $c->code,
                'note'  => $c->note ?: $c->slip_ref,
                'odds'  => (float) $c->total_odds,
                'stake' => $stakePlan->stakeFor((float) $c->total_odds, str_contains((string) $c->slip_ref, 'high-risk')),
            ]);

        $plannedTotal = $preview->sum('stake');

        $hasPhone    = filled(Setting::get('sporty_phone_enc'));
        $hasPassword = filled(Setting::get('sporty_password_enc'));

        return view('admin.auto-bet.index', compact('config', 'armed', 'preview', 'plannedTotal', 'hasPhone', 'hasPassword'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'autobet_min_stake'       => ['required', 'integer', 'min:10', 'max:1000000'],
            'autobet_max_stake'       => ['required', 'integer', 'min:10', 'max:1000000'],
            'autobet_daily_cap'       => ['required', 'integer', 'min:0', 'max:10000000'],
            'autobet_stake_low_odds'  => ['required', 'integer', 'min:10', 'max:1000000'],
            'autobet_stake_mid_odds'  => ['required', 'integer', 'min:10', 'max:1000000'],
            'autobet_stake_high_odds' => ['required', 'integer', 'min:10', 'max:1000000'],
            'autobet_stake_high_risk' => ['required', 'integer', 'min:10', 'max:1000000'],
        ]);

        if ($data['autobet_max_stake'] < $data['autobet_min_stake']) {
            return back()->with('error', 'Max stake must be at least the min stake.');
        }

        Setting::set('autobet_enabled', $request->boolean('autobet_enabled') ? '1' : '0');
        foreach ($data as $key => $value) {
            Setting::set($key, (string) $value);
        }

        // SportyBet credentials — stored encrypted, only ever decrypted for the
        // token-authed Mac worker. Left blank = keep the existing value.
        if (filled($request->input('sporty_phone'))) {
            Setting::set('sporty_phone_enc', Crypt::encryptString(trim((string) $request->input('sporty_phone'))));
        }
        if (filled($request->input('sporty_password'))) {
            Setting::set('sporty_password_enc', Crypt::encryptString((string) $request->input('sporty_password')));
        }

        return back()->with('success', 'Auto-bet rules saved'.($request->boolean('autobet_enabled') ? ' — auto-bet is ARMED.' : '.'));
    }
}
