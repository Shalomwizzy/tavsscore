@extends('layouts.admin')

@section('title', $config['title'] . ' Control Room')
@section('page-title', $config['title'])

@section('content')
<style>
    .sm-head{position:relative;overflow:hidden;padding:1.4rem;border:1px solid rgba(217,255,121,.22);border-radius:17px;background:radial-gradient(circle at 95% 5%,rgba(217,255,121,.19),transparent 25%),linear-gradient(135deg,#10241a,#09141e);margin-bottom:1rem}.sm-head:after{content:"";position:absolute;right:-45px;bottom:-105px;width:230px;height:230px;border-radius:50%;border:1px solid rgba(217,255,121,.2)}.sm-head>*{position:relative;z-index:1}.sm-eye{color:#d9ff79;font-size:.64rem;font-weight:900;letter-spacing:.12em;text-transform:uppercase}.sm-title{margin:.4rem 0;color:#fff;font-size:1.55rem;font-weight:900}.sm-sub{max-width:700px;margin:0;color:var(--dim);font-size:.78rem;line-height:1.55}.sm-actions{display:flex;gap:.55rem;flex-wrap:wrap;margin-top:1rem}.sm-rebuild{background:#d9ff79!important;color:#102015!important;border-color:#d9ff79!important}.sm-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem;margin:1rem 0}.sm-stat{padding:.9rem;border:1px solid var(--border);border-radius:12px;background:var(--card)}.sm-stat span{display:block;color:var(--dim);font-size:.65rem;text-transform:uppercase;letter-spacing:.08em;font-weight:900}.sm-stat strong{display:block;margin-top:.3rem;color:#fff;font-size:1.35rem}.sm-card-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.sm-card{position:relative;overflow:hidden;padding:1rem;border:1px solid rgba(158,181,167,.22);border-radius:15px;background:linear-gradient(145deg,#111f1a,#0b141f)}.sm-card:before{content:"";position:absolute;inset:0 0 auto;height:3px;background:linear-gradient(90deg,#d9ff79,#3bbf7c)}.sm-rank{font-size:.64rem;letter-spacing:.1em;font-weight:900;color:#d9ff79}.sm-match{margin:.45rem 0 .22rem;color:#fff;font-weight:900;font-size:1.05rem}.sm-meta{color:var(--dim);font-size:.68rem}.sm-market{margin:.8rem 0;padding:.7rem;border-radius:10px;background:rgba(217,255,121,.07);border:1px solid rgba(217,255,121,.14);color:#eaffbd;font-size:.78rem;font-weight:850}.sm-market b{float:right;font-size:.95rem}.sm-start{display:inline-block;margin-top:.45rem;margin-right:.25rem;padding:.25rem .4rem;border-radius:6px;background:rgba(255,255,255,.08);color:#fff;font-size:.65rem}.sm-label{margin-top:.8rem;color:var(--dim);font-size:.61rem;letter-spacing:.08em;text-transform:uppercase;font-weight:900}.sm-reasons{display:grid;gap:.35rem;margin-top:.4rem}.sm-reason{border-left:2px solid #d9ff79;background:rgba(255,255,255,.025);padding:.45rem .55rem;color:#c0d0c5;font-size:.69rem;line-height:1.45}.sm-intel{margin-top:.7rem}.sm-intel details{background:rgba(0,0,0,.12)!important;border-color:rgba(158,181,167,.17)!important}.sm-empty{border:1px dashed rgba(217,255,121,.28);border-radius:14px;padding:1.4rem;background:rgba(217,255,121,.035);color:var(--dim);font-size:.78rem;line-height:1.55}.sm-empty strong{display:block;margin-bottom:.35rem;color:#fff;font-size:.9rem}.sm-history{margin-top:1rem;border:1px solid var(--border);border-radius:13px;background:var(--card);overflow:hidden}.sm-history h2{margin:0;padding:1rem;color:#fff;font-size:.9rem;border-bottom:1px solid var(--border)}.sm-history-row{display:flex;justify-content:space-between;gap:.8rem;padding:.75rem 1rem;border-bottom:1px solid rgba(255,255,255,.06);font-size:.72rem;color:var(--dim)}.sm-history-row strong{color:#d6e5da}.sm-history-row:last-child{border-bottom:0}@media(max-width:760px){.sm-grid,.sm-card-grid{grid-template-columns:1fr}.sm-head{padding:1.1rem}.sm-actions form,.sm-actions .btn-a{width:100%}.sm-actions .btn-a{justify-content:center}.sm-history-row{align-items:flex-start;flex-direction:column;gap:.25rem}}
</style>

<section class="sm-head">
    <div class="sm-eye">Specialty market control room</div>
    <h1 class="sm-title">{{ $config['icon'] }} {{ $config['title'] }}</h1>
    <p class="sm-sub">Only exact-market signals at <strong style="color:#eaffbd">90% confidence or higher</strong> can be published. Pulling the latest data refreshes fixtures, rebuilds the model board, selects qualified picks, and sends only valid signals to Telegram and OneSignal.</p>
    <div class="sm-actions">
        <form method="POST" action="{{ route('admin.' . $config['admin_route'] . '.rebuild') }}">
            @csrf
            <button class="btn-a sm-rebuild" onclick="return confirm('This will pull current fixtures, rebuild prediction boards, and notify only picks at 90% or higher. Continue?')">↻ Pull latest data + rebuild</button>
        </form>
        <form method="POST" action="{{ route('admin.' . $config['admin_route'] . '.refresh') }}">
            @csrf
            <button class="btn-a btn-gray" onclick="return confirm('Re-select the existing model data and notify qualified picks?')">Re-select stored data</button>
        </form>
        <a class="btn-a btn-gray" href="{{ route($config['route']) }}" target="_blank" rel="noopener">View user page ↗</a>
    </div>
</section>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

<section class="sm-grid" aria-label="{{ $config['title'] }} performance">
    <div class="sm-stat"><span>Today's 90% signals</span><strong>{{ $todayCards->count() }}</strong></div>
    <div class="sm-stat"><span>Settled picks</span><strong>{{ $total }}</strong></div>
    <div class="sm-stat"><span>Historical accuracy</span><strong>{{ $total ? round($correct / $total * 100, 1).'%' : '—' }}</strong></div>
</section>

@if($todayCards->isNotEmpty())
<section class="sm-card-grid" aria-label="Today's {{ $config['title'] }} picks">
    @foreach($todayCards as $card)
        @php($pick = $card['pick'])
        @php($match = $pick->match)
        <article class="sm-card">
            <div class="sm-rank">#{{ $pick->{$config['rank']} }} · QUALIFIED SIGNAL</div>
            <h2 class="sm-match">{{ $match?->home_team }} <span style="color:var(--dim);font-size:.78rem">vs</span> {{ $match?->away_team }}</h2>
            <div class="sm-meta">{{ $match?->league ?: 'League pending' }} · {{ $match?->match_time?->timezone('Africa/Lagos')->format('D, d M · H:i') ?: 'Time pending' }} WAT</div>

            <div class="sm-market">{{ $card['label'] }} <b>{{ $card['probability'] }}%</b></div>
            @if($card['european_start'])
                <span class="sm-start">Virtual start: {{ $card['european_start'] }}</span>
                <span class="sm-start">Selection: {{ $card['european_selection'] }}</span>
            @endif
            @if($card['likely_score'])<span class="sm-start">Likely score: {{ $card['likely_score'] }}</span>@endif

            <div class="sm-label">Why the model selected it</div>
            <div class="sm-reasons">
                @forelse($card['reasons'] as $reason)<div class="sm-reason">{{ $reason }}</div>@empty
                    <div class="sm-reason">The exact market probability reached the strict 90% publishing threshold after the latest model evaluation.</div>
                @endforelse
            </div>
            <div class="sm-intel">
                @include('partials.match-intelligence', ['insight' => $card['insight'], 'prediction' => $pick, 'accent' => '#d9ff79'])
            </div>
        </article>
    @endforeach
</section>
@else
    <div class="sm-empty"><strong>No qualified {{ $config['title'] }} signal yet.</strong>That is intentional: the system will not publish a pick unless the exact market clears 90%. Use “Pull latest data + rebuild” after fixtures or statistics update.</div>
@endif

<section class="sm-history">
    <h2>Recent resolved and pending history</h2>
    @forelse($history as $pick)
        <div class="sm-history-row">
            <span>{{ $pick->match?->match_time?->timezone('Africa/Lagos')->format('d M Y') ?: 'Date pending' }} · <strong>{{ $pick->match?->home_team }} vs {{ $pick->match?->away_team }}</strong></span>
            <strong>{{ $config['market'] ?? $pick->{$config['label_field']} }}</strong>
        </div>
    @empty
        <div class="sm-history-row">No published history for this market yet.</div>
    @endforelse
</section>
@endsection
