<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\XPost;
use App\Services\XService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class XController extends Controller
{
    public function index(XService $x): View
    {
        $connected = $x->isConfigured();
        $growthEnabled = $x->growthEnabled();
        $posts = XPost::query()->latest()->limit(60)->get();
        $stats = [
            'total'  => XPost::count(),
            'posted' => XPost::where('status', 'posted')->count(),
            'failed' => XPost::where('status', 'failed')->count(),
            'today'  => XPost::whereDate('created_at', now('Africa/Lagos')->toDateString())->count(),
        ];

        return view('admin.x.index', compact('connected', 'growthEnabled', 'posts', 'stats'));
    }

    public function toggle(Request $request): RedirectResponse
    {
        $enabled = $request->boolean('enabled');
        Setting::set('x_growth_enabled', $enabled ? '1' : '0');

        return back()->with('success', 'Football growth posts '.($enabled ? 'enabled' : 'disabled').'.');
    }

    public function postNow(XService $x): RedirectResponse
    {
        if (! $x->isConfigured()) {
            return back()->with('error', 'Connect the X account first (Settings → X Auto-Posting).');
        }

        @set_time_limit(0);
        Artisan::call('x:post-football');

        return back()->with('success', trim(Artisan::output()) ?: 'Requested a football post.');
    }
}
