@extends('layouts.admin')

@section('title', 'X (Twitter)')
@section('page-title', 'X (Twitter)')

@section('content')
<div style="max-width:900px;">

    @if($needsMigration)
        <div style="background:#7f1d1d;border:1px solid #ef4444;color:#fee2e2;padding:.85rem 1.1rem;border-radius:8px;margin-bottom:1.25rem;font-size:.85rem;line-height:1.45;">
            <b>⚠️ Database update needed.</b> The X post-log table isn’t created on this server yet. Run this once on the server, then reload:
            <code style="display:block;margin-top:.4rem;background:#450a0a;padding:.4rem .6rem;border-radius:5px;overflow-x:auto;">php artisan migrate --force</code>
        </div>
    @endif

    {{-- Connection status --}}
    <div style="background:var(--card);border:1px solid {{ $connected ? 'rgba(16,185,129,.4)' : 'rgba(245,158,11,.4)' }};border-radius:10px;padding:1.1rem 1.25rem;margin-bottom:1.25rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
        <div>
            <div style="font-weight:800;color:{{ $connected ? '#10b981' : '#f59e0b' }};font-size:.95rem;">
                {{ $connected ? '● Connected — auto-posting is active' : '○ Not connected' }}
            </div>
            <div style="color:var(--dim);font-size:.78rem;margin-top:.25rem;">
                @if($connected)
                    Booking codes, results, and football posts publish to your X account automatically.
                @else
                    Add your X API keys to start posting.
                @endif
            </div>
        </div>
        <a href="{{ route('admin.settings.index') }}" class="btn-a" style="background:var(--accent);color:#fff;padding:.5rem 1rem;border-radius:7px;font-size:.82rem;font-weight:600;">Manage X account</a>
    </div>

    {{-- Stats --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:.75rem;margin-bottom:1.25rem;">
        @foreach(['today'=>'Posted today','posted'=>'Posted total','failed'=>'Failed','total'=>'All attempts'] as $k=>$label)
            <div style="background:var(--card);border:1px solid var(--border);border-radius:9px;padding:.8rem 1rem;">
                <div style="font-size:1.5rem;font-weight:800;color:{{ $k==='failed' && $stats[$k]>0 ? '#f87171' : 'var(--text)' }};">{{ $stats[$k] }}</div>
                <div style="font-size:.72rem;color:var(--dim);text-transform:uppercase;letter-spacing:.04em;">{{ $label }}</div>
            </div>
        @endforeach
    </div>

    {{-- The three activities --}}
    <div style="background:var(--card);border:1px solid var(--border);border-radius:10px;padding:1.25rem;margin-bottom:1.25rem;">
        <div class="sb-section-label" style="margin:0 0 .75rem;">Automated activities</div>

        <div style="display:flex;flex-direction:column;gap:.9rem;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;">
                <div>
                    <div style="font-weight:700;font-size:.85rem;">🎟️ Booking codes + results</div>
                    <div style="color:var(--dim);font-size:.76rem;margin-top:.2rem;">The day's highest-odds code auto-posts, and its win/lose result follows. Always on.</div>
                </div>
                <span style="color:#10b981;font-size:.75rem;font-weight:700;white-space:nowrap;">● On</span>
            </div>

            <div style="border-top:1px solid var(--border);padding-top:.9rem;display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;">
                <div>
                    <div style="font-weight:700;font-size:.85rem;">📣 Football growth posts</div>
                    <div style="color:var(--dim);font-size:.76rem;margin-top:.2rem;">4 data-driven posts/day (predictions, results, stats, match-day questions) to grow the account.</div>
                </div>
                <div style="display:flex;gap:.5rem;align-items:center;">
                    <form method="POST" action="{{ route('admin.x.toggle') }}">
                        @csrf
                        <input type="hidden" name="enabled" value="{{ $growthEnabled ? '0' : '1' }}">
                        <button type="submit" style="background:{{ $growthEnabled ? '#7f1d1d' : '#065f46' }};color:#fff;border:none;border-radius:7px;padding:.45rem .9rem;font-size:.8rem;font-weight:600;cursor:pointer;">
                            {{ $growthEnabled ? 'Turn OFF' : 'Turn ON' }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.x.post-now') }}">
                        @csrf
                        <button type="submit" style="background:var(--accent);color:#fff;border:none;border-radius:7px;padding:.45rem .9rem;font-size:.8rem;font-weight:600;cursor:pointer;">Post one now</button>
                    </form>
                    <span style="color:{{ $growthEnabled ? '#10b981' : '#f59e0b' }};font-size:.75rem;font-weight:700;white-space:nowrap;">{{ $growthEnabled ? '● On' : '○ Off' }}</span>
                </div>
            </div>

            <div style="border-top:1px solid var(--border);padding-top:.9rem;display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;">
                <div>
                    <div style="font-weight:700;font-size:.85rem;color:var(--dim);">🤝 Auto-engage / reply to others</div>
                    <div style="color:var(--dim);font-size:.76rem;margin-top:.2rem;">Not enabled — risks account suspension and needs paid X read access.</div>
                </div>
                <span style="color:var(--dim);font-size:.75rem;font-weight:700;white-space:nowrap;">— Off</span>
            </div>
        </div>
    </div>

    {{-- Recent posts --}}
    <div class="sb-section-label" style="margin:0 0 .6rem;">Recent posts</div>
    <div style="background:var(--card);border:1px solid var(--border);border-radius:10px;overflow:hidden;">
        @forelse($posts as $post)
            <div style="display:flex;gap:.9rem;align-items:flex-start;padding:.8rem 1rem;{{ !$loop->last ? 'border-bottom:1px solid var(--border);' : '' }}">
                <div style="flex-shrink:0;width:120px;">
                    <div style="font-size:.78rem;font-weight:600;">{{ $post->label() }}</div>
                    <div style="font-size:.68rem;color:var(--dim);margin-top:.15rem;">{{ $post->created_at->timezone('Africa/Lagos')->format('d M, H:i') }}</div>
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:.8rem;white-space:pre-line;color:var(--text);line-height:1.35;">{{ \Illuminate\Support\Str::limit($post->text, 180) }}</div>
                    @if($post->status === 'failed')
                        <div style="font-size:.7rem;color:#f87171;margin-top:.25rem;">⚠️ {{ \Illuminate\Support\Str::limit($post->error, 160) }}</div>
                    @endif
                </div>
                <div style="flex-shrink:0;text-align:right;">
                    @if($post->status === 'posted')
                        <span style="color:#10b981;font-size:.72rem;font-weight:700;">✓ posted</span>
                        @if($post->tweet_id)
                            <div><a href="https://x.com/i/status/{{ $post->tweet_id }}" target="_blank" rel="noopener" style="color:var(--accent);font-size:.7rem;">view →</a></div>
                        @endif
                    @else
                        <span style="color:#f87171;font-size:.72rem;font-weight:700;">✗ failed</span>
                    @endif
                </div>
            </div>
        @empty
            <div style="padding:1.5rem;text-align:center;color:var(--dim);font-size:.82rem;">No X posts yet. They'll appear here once posting starts.</div>
        @endforelse
    </div>

</div>
@endsection
