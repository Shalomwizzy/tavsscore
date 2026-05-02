<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class NewsletterAdminController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->string('filter', 'all')->toString();

        $query = NewsletterSubscriber::query();
        $query = match ($filter) {
            'confirmed'    => $query->active(),
            'pending'      => $query->pending(),
            'unsubscribed' => $query->whereNotNull('unsubscribed_at'),
            default        => $query,
        };

        $subscribers = $query->orderByDesc('created_at')->paginate(50)->withQueryString();

        $stats = [
            'total'        => NewsletterSubscriber::count(),
            'confirmed'    => NewsletterSubscriber::active()->count(),
            'pending'      => NewsletterSubscriber::pending()->count(),
            'unsubscribed' => NewsletterSubscriber::whereNotNull('unsubscribed_at')->count(),
            'sent_today'   => NewsletterSubscriber::whereDate('last_sent_at', now('Africa/Lagos')->toDateString())->count(),
        ];

        return view('admin.newsletter.index', compact('subscribers', 'stats', 'filter'));
    }

    /**
     * CSV export — confirmed subscribers by default, or whatever filter is on the URL.
     */
    public function export(Request $request): StreamedResponse
    {
        $filter = $request->string('filter', 'confirmed')->toString();

        $query = NewsletterSubscriber::query();
        $query = match ($filter) {
            'pending'      => $query->pending(),
            'unsubscribed' => $query->whereNotNull('unsubscribed_at'),
            'all'          => $query,
            default        => $query->active(),
        };

        $filename = 'subscribers-' . $filter . '-' . now('Africa/Lagos')->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['email', 'status', 'source', 'subscribed_at', 'confirmed_at', 'unsubscribed_at', 'last_sent_at']);

            $query->orderByDesc('created_at')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [
                        $r->email,
                        $r->unsubscribed_at ? 'unsubscribed' : ($r->confirmed_at ? 'confirmed' : 'pending'),
                        $r->source ?? '',
                        $r->created_at?->toIso8601String() ?? '',
                        $r->confirmed_at?->toIso8601String() ?? '',
                        $r->unsubscribed_at?->toIso8601String() ?? '',
                        $r->last_sent_at?->toIso8601String() ?? '',
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Manually trigger today's newsletter send.
     */
    public function sendNow(): RedirectResponse
    {
        Artisan::call('newsletter:send-daily');
        $output = Artisan::output();

        return redirect()->route('admin.newsletter.index')
            ->with('success', "Newsletter triggered. {$output}");
    }

    public function destroy(NewsletterSubscriber $subscriber): RedirectResponse
    {
        $subscriber->delete();
        return redirect()->route('admin.newsletter.index')->with('success', 'Subscriber removed.');
    }
}
