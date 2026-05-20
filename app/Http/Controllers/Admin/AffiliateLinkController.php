<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AffiliateLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AffiliateLinkController extends Controller
{
    public function index(): View
    {
        $links = AffiliateLink::orderBy('sort_order')->get();

        return view('admin.affiliate-links.index', compact('links'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'platform'     => ['required', 'string', 'max:60'],
            'slug'         => ['required', 'string', 'max:60', 'regex:/^[a-z0-9\-]+$/'],
            'register_url' => ['required', 'url', 'max:500'],
            'website_url'  => ['nullable', 'url', 'max:500'],
            'promo_text'   => ['nullable', 'string', 'max:200'],
            'color'        => ['nullable', 'string', 'max:7'],
            'logo_emoji'   => ['nullable', 'string', 'max:10'],
            'sort_order'   => ['nullable', 'integer', 'min:0', 'max:99'],
        ]);

        AffiliateLink::create($data);

        return back()->with('success', "Affiliate link for {$data['platform']} added.");
    }

    public function update(Request $request, AffiliateLink $affiliateLink): RedirectResponse
    {
        $data = $request->validate([
            'platform'     => ['required', 'string', 'max:60'],
            'slug'         => ['required', 'string', 'max:60', 'regex:/^[a-z0-9\-]+$/'],
            'register_url' => ['required', 'url', 'max:500'],
            'website_url'  => ['nullable', 'url', 'max:500'],
            'promo_text'   => ['nullable', 'string', 'max:200'],
            'color'        => ['nullable', 'string', 'max:7'],
            'logo_emoji'   => ['nullable', 'string', 'max:10'],
            'sort_order'   => ['nullable', 'integer', 'min:0', 'max:99'],
        ]);

        $affiliateLink->update($data);

        return back()->with('success', "Affiliate link for {$affiliateLink->platform} updated.");
    }

    public function destroy(AffiliateLink $affiliateLink): RedirectResponse
    {
        $name = $affiliateLink->platform;
        $affiliateLink->delete();

        return back()->with('success', "Affiliate link for {$name} deleted.");
    }

    public function toggle(AffiliateLink $affiliateLink): RedirectResponse
    {
        $affiliateLink->update(['is_active' => ! $affiliateLink->is_active]);

        $status = $affiliateLink->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "{$affiliateLink->platform} {$status}.");
    }
}
