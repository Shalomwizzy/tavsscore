@extends('layouts.admin')
@section('title', 'Newsletter Subscribers')
@section('page-title', 'Newsletter Subscribers')

@section('content')

<div class="page-hd">
    <span class="page-hd-title">📬 Newsletter Subscribers</span>
    <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
        <a href="{{ route('admin.newsletter.export', ['filter' => $filter]) }}" class="btn-a btn-blue">⬇ Export CSV</a>
        <form method="POST" action="{{ route('admin.newsletter.send-now') }}"
              onsubmit="return confirm('Send today\'s picks newsletter to all confirmed subscribers RIGHT NOW?');">
            @csrf
            <button type="submit" class="btn-a btn-green">📨 Send today's newsletter</button>
        </form>
    </div>
</div>

{{-- Stats --}}
<div class="stat-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); margin-bottom:1.25rem;">
    <div class="stat-card">
        <span class="stat-val">{{ number_format($stats['total']) }}</span>
        <span class="stat-lbl">📬 Total subscribers</span>
    </div>
    <div class="stat-card" style="background:linear-gradient(135deg,rgba(16,185,129,.10),rgba(16,185,129,.04));border-color:rgba(16,185,129,.25);">
        <span class="stat-val" style="color:#6ee7b7;">{{ number_format($stats['confirmed']) }}</span>
        <span class="stat-lbl">✓ Confirmed</span>
    </div>
    <div class="stat-card">
        <span class="stat-val" style="color:#fcd34d;">{{ number_format($stats['pending']) }}</span>
        <span class="stat-lbl">⏳ Pending opt-in</span>
    </div>
    <div class="stat-card">
        <span class="stat-val" style="color:#fca5a5;">{{ number_format($stats['unsubscribed']) }}</span>
        <span class="stat-lbl">🚪 Unsubscribed</span>
    </div>
    <div class="stat-card">
        <span class="stat-val">{{ number_format($stats['sent_today']) }}</span>
        <span class="stat-lbl">📨 Sent today</span>
    </div>
</div>

{{-- Filter tabs --}}
<div style="display:flex; gap:.4rem; flex-wrap:wrap; margin-bottom:.75rem;">
    @foreach(['all' => 'All', 'confirmed' => 'Confirmed', 'pending' => 'Pending', 'unsubscribed' => 'Unsubscribed'] as $key => $label)
    <a href="{{ route('admin.newsletter.index', ['filter' => $key]) }}"
       class="btn-a {{ $filter === $key ? '' : 'btn-gray' }}"
       style="{{ $filter === $key ? 'background:var(--green-d); border:1px solid var(--green-b); color:#6ee7b7;' : '' }}">
        {{ $label }}
    </a>
    @endforeach
</div>

<div class="a-card">
    <div style="overflow-x:auto;">
        <table class="a-table">
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Source</th>
                    <th>Subscribed</th>
                    <th>Last sent</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($subscribers as $sub)
                <tr>
                    <td style="color:#fff; font-weight:600; font-family:'SF Mono',Menlo,monospace; font-size:.8rem;">
                        {{ $sub->email }}
                    </td>
                    <td>
                        @if($sub->unsubscribed_at)
                            <span class="badge badge-red">Unsubscribed</span>
                        @elseif($sub->confirmed_at)
                            <span class="badge badge-green">✓ Confirmed</span>
                        @else
                            <span class="badge badge-gray">⏳ Pending</span>
                        @endif
                    </td>
                    <td style="color:var(--dim); font-size:.74rem;">{{ $sub->source ?? '-' }}</td>
                    <td style="color:var(--dim); font-size:.74rem; white-space:nowrap;">{{ $sub->created_at?->format('M d, H:i') }}</td>
                    <td style="color:var(--dim); font-size:.74rem; white-space:nowrap;">
                        {{ $sub->last_sent_at?->format('M d, H:i') ?? '-' }}
                    </td>
                    <td>
                        <form method="POST" action="{{ route('admin.newsletter.destroy', $sub) }}"
                              onsubmit="return confirm('Delete this subscriber permanently?');"
                              style="display:inline;">
                            @csrf @method('DELETE')
                            <button type="submit" style="background:none; border:none; color:#fca5a5; cursor:pointer; font-size:.72rem; font-weight:600;">Delete</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="color:var(--dim); text-align:center; padding:2.5rem;">
                        No subscribers in this view yet.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($subscribers->hasPages())
    @include('admin.partials.pagination', ['paginator' => $subscribers])
    @endif
</div>

<div style="margin-top:1.25rem; padding:.875rem 1rem; border-radius:8px; background:rgba(59,130,246,.08); border:1px solid rgba(59,130,246,.2); font-size:.74rem; color:#93c5fd;">
    <strong>ℹ️ How it works:</strong>
    Newsletter goes out automatically every day at <code style="background:rgba(255,255,255,.1);padding:1px 5px;border-radius:3px;">09:00 Lagos</code> via the <code>newsletter:send-daily</code> command (scheduled).
    Subscribers must double-opt-in via email before they receive anything. Each email has a one-click unsubscribe link.
    CSV exports include all data, no anonymisation - handle accordingly under GDPR.
</div>

@endsection
