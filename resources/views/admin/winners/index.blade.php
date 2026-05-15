@extends('layouts.admin')
@section('title', 'Winners')
@section('page-title', 'Winners Wall & Hall of Fame')

@section('content')
<div style="max-width:960px">

    @if(session('success'))
        <div class="alert alert-green">{{ session('success') }}</div>
    @endif

    {{-- Hall of Fame leaderboard --}}
    @php $leaderboard = \App\Http\Controllers\HallOfFameController::buildLeaderboard(20); @endphp
    <div class="a-card" style="margin-bottom:1.5rem; border-color:rgba(251,191,36,.3);">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem;">
            <span style="font-weight:700; font-size:.9rem; color:#fff;">🥇 Hall of Fame</span>
            <a href="{{ route('hall-of-fame.index') }}" target="_blank" style="font-size:.72rem; color:#fcd34d; text-decoration:none;">↗ Public page</a>
        </div>
        @if($leaderboard->isEmpty())
            <p style="font-size:.78rem; color:var(--dim);">No approved winners with amounts yet.</p>
        @else
        <div style="overflow-x:auto;">
            <table class="a-table" style="font-size:.78rem;">
                <thead>
                    <tr><th>#</th><th>Username</th><th>Total Won</th><th>Submissions</th><th>Last Win</th></tr>
                </thead>
                <tbody>
                    @foreach($leaderboard as $i => $winner)
                    <tr>
                        <td style="font-weight:800; color:#fcd34d; width:36px;">
                            {{ $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : '#'.($i+1))) }}
                        </td>
                        <td style="font-weight:700; color:#fff;">{{ $winner->username }}</td>
                        <td style="font-weight:800; color:#10b981;">{{ $winner->currency }} {{ number_format($winner->total_won, 0) }}</td>
                        <td style="color:var(--dim);">{{ $winner->total_wins }} {{ \Illuminate\Support\Str::plural('win', $winner->total_wins) }}</td>
                        <td style="color:var(--dim); font-size:.7rem;">{{ $winner->last_win->format('M d, Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- Pending --}}
    <div class="a-card" style="margin-bottom:1.5rem;">
        <div style="font-size:.78rem; font-weight:700; color:#fff; margin-bottom:1rem;">
            ⏳ Pending Review
            @if($pending->count())
                <span style="background:#ef4444; color:#fff; border-radius:999px; padding:1px 8px; font-size:.65rem; margin-left:.4rem;">{{ $pending->count() }}</span>
            @endif
        </div>

        @if($pending->isEmpty())
            <p style="font-size:.78rem; color:var(--dim);">No pending submissions.</p>
        @else
        <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:.75rem;">
            @foreach($pending as $win)
            @php
                $purls = $win->screenshot_urls;
                $isReturning = \App\Models\WinnerSubmission::approved()
                    ->whereRaw('LOWER(username) = ?', [strtolower($win->username)])
                    ->exists();
            @endphp
            <div style="background:var(--surface); border:1px solid {{ $isReturning ? 'rgba(251,191,36,.4)' : 'var(--border)' }}; border-radius:10px; overflow:hidden;">

                {{-- Screenshot (clickable) --}}
                <div style="position:relative; cursor:zoom-in;" onclick="openLightbox({{ json_encode($purls) }}, 0)">
                    <img src="{{ $purls[0] }}" style="width:100%; aspect-ratio:4/3; object-fit:cover; display:block;" loading="lazy">
                    <div style="position:absolute;inset:0;background:rgba(0,0,0,0);transition:background .15s;" onmouseenter="this.style.background='rgba(0,0,0,.25)'" onmouseleave="this.style.background='rgba(0,0,0,0)'">
                        <span style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:rgba(0,0,0,.6);color:#fff;padding:.3rem .7rem;border-radius:999px;font-size:.7rem;font-weight:700;opacity:0;transition:opacity .15s;" class="zoom-hint">🔍 View</span>
                    </div>
                    @if(count($purls) > 1)
                        <span style="position:absolute;top:.4rem;right:.4rem;background:rgba(0,0,0,.75);color:#fff;font-size:.65rem;font-weight:700;padding:2px 7px;border-radius:999px;">📷 {{ count($purls) }}</span>
                    @endif
                </div>

                <div style="padding:.75rem;">
                    <div style="display:flex; align-items:center; gap:.4rem; margin-bottom:.25rem; flex-wrap:wrap;">
                        <span style="font-size:.8rem; font-weight:700; color:#fff;">{{ $win->username }}</span>
                        @if($isReturning)
                            @php
                                $prevEmail = \App\Models\WinnerSubmission::approved()
                                    ->whereRaw('LOWER(username) = ?', [strtolower($win->username)])
                                    ->whereNotNull('email')->value('email');
                                $emailOk = $prevEmail && $win->email && strtolower($prevEmail) === strtolower($win->email);
                            @endphp
                            @if($emailOk)
                                <span style="background:rgba(16,185,129,.15); border:1px solid rgba(16,185,129,.3); color:#6ee7b7; font-size:.6rem; font-weight:700; padding:1px 6px; border-radius:999px;">✓ EMAIL MATCH</span>
                            @else
                                <span style="background:rgba(239,68,68,.15); border:1px solid rgba(239,68,68,.3); color:#fca5a5; font-size:.6rem; font-weight:700; padding:1px 6px; border-radius:999px;">⚠ EMAIL MISMATCH</span>
                            @endif
                        @endif
                    </div>
                    {{-- Email — always shown, copyable --}}
                    <div style="display:flex; align-items:center; gap:.4rem; background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.08); border-radius:6px; padding:.3rem .5rem; margin-bottom:.4rem;">
                        <span style="font-size:.7rem; color:#7dd3fc; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                            ✉ {{ $win->email ?? '—' }}
                        </span>
                        @if($win->email)
                        <button onclick="navigator.clipboard.writeText('{{ $win->email }}').then(()=>this.textContent='✓').catch(()=>{})" title="Copy email"
                                style="background:none;border:none;color:rgba(255,255,255,.4);cursor:pointer;font-size:.65rem;padding:0;flex-shrink:0;">copy</button>
                        @endif
                    </div>
                    @if($win->pick_description)
                        <div style="font-size:.7rem; color:var(--dim); margin-bottom:.2rem;">📌 {{ $win->pick_description }}</div>
                    @endif
                    @if($win->match_details)
                        <div style="font-size:.7rem; color:var(--dim); margin-bottom:.2rem;">⚽ {{ $win->match_details }}</div>
                    @endif
                    @if($win->platform)
                        <div style="font-size:.7rem; color:var(--dim); margin-bottom:.2rem;">🎰 {{ $win->platform }}</div>
                    @endif
                    {{-- Amount (editable inline) --}}
                    <form method="POST" action="{{ route('admin.winners.update-amount', $win) }}"
                          style="display:flex; gap:.3rem; align-items:center; margin-bottom:.4rem; flex-wrap:wrap;">
                        @csrf
                        <select name="currency" style="background:var(--bg); border:1px solid var(--border); color:var(--text); border-radius:5px; padding:2px 4px; font-size:.65rem; width:58px;">
                            @foreach(['NGN','USD','GBP','EUR','KES','GHS','ZAR'] as $cur)
                                <option value="{{ $cur }}" {{ ($win->currency ?? 'NGN') === $cur ? 'selected' : '' }}>{{ $cur }}</option>
                            @endforeach
                        </select>
                        <input type="number" name="winning_amount" min="0" step="0.01"
                               value="{{ $win->winning_amount }}"
                               placeholder="Amount from screenshot"
                               style="flex:1; min-width:0; background:var(--bg); border:1px solid var(--border); color:var(--text); border-radius:5px; padding:3px 6px; font-size:.7rem;">
                        <button type="submit" style="background:rgba(16,185,129,.15); border:1px solid rgba(16,185,129,.3); color:#6ee7b7; border-radius:5px; padding:3px 8px; font-size:.65rem; font-weight:700; cursor:pointer; white-space:nowrap;">Save</button>
                    </form>
                    <div style="font-size:.65rem; color:var(--dim); margin-bottom:.75rem;">{{ $win->created_at->format('M d, Y g:i A') }}</div>

                    @if(count($purls) > 1)
                    <div style="display:flex; gap:4px; margin-bottom:.6rem; flex-wrap:wrap;">
                        @foreach($purls as $idx => $purl)
                        <img src="{{ $purl }}" onclick="openLightbox({{ json_encode($purls) }}, {{ $idx }})"
                             style="width:42px; height:42px; object-fit:cover; border-radius:5px; cursor:pointer; border:2px solid transparent; transition:border-color .15s;"
                             onmouseenter="this.style.borderColor='#10b981'" onmouseleave="this.style.borderColor='transparent'">
                        @endforeach
                    </div>
                    @endif

                    <div style="display:flex; gap:.4rem; flex-wrap:wrap;">
                        <a href="{{ route('admin.winners.edit', $win) }}" class="btn-a" style="flex:1; justify-content:center; font-size:.72rem; padding:.35rem .5rem; background:rgba(99,102,241,.15); border:1px solid rgba(99,102,241,.35); color:#a5b4fc; text-decoration:none; text-align:center;">✏️ Edit</a>
                        <form method="POST" action="{{ route('admin.winners.approve', $win) }}" style="flex:1;">
                            @csrf
                            <button type="submit" class="btn-a btn-green" style="width:100%; justify-content:center; font-size:.72rem; padding:.35rem .5rem;">✅ Approve</button>
                        </form>
                        <form method="POST" action="{{ route('admin.winners.reject', $win) }}" style="flex:1;"
                              onsubmit="return confirm('Reject and permanently delete this submission?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-a btn-red" style="width:100%; justify-content:center; font-size:.72rem; padding:.35rem .5rem;">❌ Reject</button>
                        </form>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Approved --}}
    <div class="a-card">
        <div style="font-size:.78rem; font-weight:700; color:#fff; margin-bottom:1rem;">✅ Published ({{ $approved->count() }})</div>

        @if($approved->isEmpty())
            <p style="font-size:.78rem; color:var(--dim);">No approved winners yet.</p>
        @else
        <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(210px,1fr)); gap:.6rem;">
            @foreach($approved as $win)
            @php $aurls = $win->screenshot_urls; @endphp
            <div style="background:var(--surface); border:1px solid rgba(16,185,129,.2); border-radius:10px; overflow:hidden;">
                <div style="position:relative; cursor:zoom-in;" onclick="openLightbox({{ json_encode($aurls) }}, 0)">
                    <img src="{{ $aurls[0] }}" style="width:100%; aspect-ratio:4/3; object-fit:cover; display:block;" loading="lazy">
                    @if(count($aurls) > 1)
                        <span style="position:absolute;top:.4rem;right:.4rem;background:rgba(0,0,0,.75);color:#fff;font-size:.65rem;font-weight:700;padding:2px 7px;border-radius:999px;">📷 {{ count($aurls) }}</span>
                    @endif
                </div>
                <div style="padding:.6rem;">
                    <div style="font-size:.75rem; font-weight:700; color:#fff; margin-bottom:.2rem;">{{ $win->username }}</div>
                    @if($win->email)
                        <div style="font-size:.65rem; color:#7dd3fc; margin-bottom:.2rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $win->email }}">✉ {{ $win->email }}</div>
                    @endif
                    @if($win->platform)
                        <div style="font-size:.65rem; color:var(--dim); margin-bottom:.2rem;">🎰 {{ $win->platform }}</div>
                    @endif
                    <form method="POST" action="{{ route('admin.winners.update-amount', $win) }}"
                          style="display:flex; gap:.25rem; align-items:center; margin-bottom:.3rem;">
                        @csrf
                        <select name="currency" style="background:var(--bg); border:1px solid var(--border); color:var(--text); border-radius:4px; padding:1px 3px; font-size:.6rem; width:52px;">
                            @foreach(['NGN','USD','GBP','EUR','KES','GHS','ZAR'] as $cur)
                                <option value="{{ $cur }}" {{ ($win->currency ?? 'NGN') === $cur ? 'selected' : '' }}>{{ $cur }}</option>
                            @endforeach
                        </select>
                        <input type="number" name="winning_amount" min="0" step="0.01"
                               value="{{ $win->winning_amount }}"
                               placeholder="Amount"
                               style="flex:1; min-width:0; background:var(--bg); border:1px solid var(--border); color:{{ $win->winning_amount ? '#10b981' : 'var(--text)' }}; border-radius:4px; padding:2px 5px; font-size:.65rem; font-weight:{{ $win->winning_amount ? '700' : '400' }};">
                        <button type="submit" style="background:rgba(16,185,129,.12); border:1px solid rgba(16,185,129,.25); color:#6ee7b7; border-radius:4px; padding:2px 6px; font-size:.6rem; font-weight:700; cursor:pointer;">✓</button>
                    </form>
                    <div style="display:flex; gap:.3rem; margin-top:.4rem;">
                        <a href="{{ route('admin.winners.edit', $win) }}" style="flex:1; display:flex; align-items:center; justify-content:center; background:rgba(99,102,241,.12); border:1px solid rgba(99,102,241,.3); color:#a5b4fc; border-radius:6px; font-size:.63rem; font-weight:700; padding:.25rem .4rem; text-decoration:none;">✏️ Edit</a>
                        <form method="POST" action="{{ route('admin.winners.reject', $win) }}" style="flex:1;"
                              onsubmit="return confirm('Remove this winner?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-a btn-red" style="width:100%; justify-content:center; font-size:.63rem; padding:.25rem .4rem;">Remove</button>
                        </form>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

{{-- All submissions data table --}}
@php $allSubmissions = \App\Models\WinnerSubmission::orderByDesc('created_at')->get(); @endphp
<div class="a-card" style="margin-top:1.5rem;">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem;">
        <span style="font-weight:700; font-size:.9rem; color:#fff;">📋 All Submissions Data</span>
        <span style="font-size:.7rem; color:var(--dim);">{{ $allSubmissions->count() }} total</span>
    </div>
    <div style="overflow-x:auto;">
        <table class="a-table" style="font-size:.73rem; min-width:680px;">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Match</th>
                    <th>Platform</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($allSubmissions as $s)
                <tr>
                    <td style="color:var(--dim); white-space:nowrap;">{{ $s->created_at->format('M d, Y') }}</td>
                    <td style="font-weight:700; color:#fff;">{{ $s->username }}</td>
                    <td>
                        @if($s->email)
                            <span style="color:#7dd3fc; cursor:pointer;" onclick="navigator.clipboard.writeText('{{ $s->email }}')" title="Click to copy">{{ $s->email }}</span>
                        @else
                            <span style="color:rgba(255,255,255,.2);">—</span>
                        @endif
                    </td>
                    <td style="color:var(--dim);">{{ $s->match_details ?: '—' }}</td>
                    <td style="color:var(--dim);">{{ $s->platform ?: '—' }}</td>
                    <td style="color:#10b981; font-weight:600;">
                        @if($s->winning_amount)
                            {{ $s->currency }} {{ number_format($s->winning_amount, 0) }}
                        @else
                            <span style="color:rgba(255,255,255,.2);">—</span>
                        @endif
                    </td>
                    <td>
                        @if($s->is_approved)
                            <span style="color:#6ee7b7; font-weight:700; font-size:.65rem;">✅ Approved</span>
                        @else
                            <span style="color:#fbbf24; font-weight:700; font-size:.65rem;">⏳ Pending</span>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex; gap:.35rem; align-items:center;">
                            <a href="{{ route('admin.winners.edit', $s) }}"
                               style="background:rgba(99,102,241,.15); border:1px solid rgba(99,102,241,.35); color:#a5b4fc; border-radius:5px; padding:2px 10px; font-size:.65rem; font-weight:700; text-decoration:none; white-space:nowrap;">✏️ Edit</a>
                            <form method="POST" action="{{ route('admin.winners.reject', $s) }}"
                                  onsubmit="return confirm('Delete this submission permanently?')">
                                @csrf @method('DELETE')
                                <button type="submit" style="background:rgba(239,68,68,.15); border:1px solid rgba(239,68,68,.3); color:#fca5a5; border-radius:5px; padding:2px 10px; font-size:.65rem; font-weight:700; cursor:pointer;">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Lightbox --}}
<div id="lb-overlay" onclick="closeLightbox()" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.92); z-index:99999; align-items:center; justify-content:center; flex-direction:column; gap:.75rem;">
    <button onclick="closeLightbox()" style="position:fixed;top:1rem;right:1.25rem;background:rgba(255,255,255,.1);border:none;color:#fff;font-size:1.5rem;width:40px;height:40px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;">×</button>

    <button id="lb-prev" onclick="event.stopPropagation(); lbNav(-1)" style="position:fixed;left:1rem;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.1);border:none;color:#fff;font-size:1.4rem;width:44px;height:44px;border-radius:50%;cursor:pointer;display:none;align-items:center;justify-content:center;">‹</button>
    <button id="lb-next" onclick="event.stopPropagation(); lbNav(1)"  style="position:fixed;right:1rem;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.1);border:none;color:#fff;font-size:1.4rem;width:44px;height:44px;border-radius:50%;cursor:pointer;display:none;align-items:center;justify-content:center;">›</button>

    <img id="lb-img" src="" onclick="event.stopPropagation()"
         style="max-width:min(960px,92vw); max-height:88vh; object-fit:contain; border-radius:8px; box-shadow:0 24px 80px rgba(0,0,0,.6);">

    <div id="lb-counter" style="color:rgba(255,255,255,.5); font-size:.75rem;"></div>
</div>

<style>
.zoom-hint { pointer-events:none; }
div:hover .zoom-hint { opacity:1 !important; }
</style>

<script>
var _lbUrls = [], _lbIdx = 0;

function openLightbox(urls, idx) {
    _lbUrls = urls; _lbIdx = idx;
    var ol = document.getElementById('lb-overlay');
    ol.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    lbRender();
}

function closeLightbox() {
    document.getElementById('lb-overlay').style.display = 'none';
    document.body.style.overflow = '';
}

function lbNav(dir) {
    _lbIdx = (_lbIdx + dir + _lbUrls.length) % _lbUrls.length;
    lbRender();
}

function lbRender() {
    document.getElementById('lb-img').src = _lbUrls[_lbIdx];
    var multi = _lbUrls.length > 1;
    document.getElementById('lb-prev').style.display = multi ? 'flex' : 'none';
    document.getElementById('lb-next').style.display = multi ? 'flex' : 'none';
    document.getElementById('lb-counter').textContent = multi ? (_lbIdx+1) + ' / ' + _lbUrls.length : '';
}

document.addEventListener('keydown', function(e) {
    var ol = document.getElementById('lb-overlay');
    if (ol.style.display === 'none') return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft')  lbNav(-1);
    if (e.key === 'ArrowRight') lbNav(1);
});
</script>
@endsection
