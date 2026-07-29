@php
    $sourcePrediction = $prediction ?? (isset($predictionId) ? \App\Models\Prediction::query()->with('match')->find($predictionId) : null);
    $matchInsight = $insight ?? ($sourcePrediction ? app(\App\Services\MatchInsightService::class)->for($sourcePrediction) : null);
    $accent = $accent ?? '#6ee7b7';
@endphp

@if($matchInsight && ($matchInsight['available'] ?? false))
<details style="margin-top:1rem;border:1px solid var(--border);border-radius:11px;background:rgba(255,255,255,.018);overflow:hidden;">
    <summary style="cursor:pointer;list-style:none;padding:.8rem 1rem;color:#fff;font-size:.78rem;font-weight:800;display:flex;align-items:center;justify-content:space-between;gap:.75rem;">
        <span>🔎 Full match intelligence</span><span style="color:{{ $accent }};font-size:.68rem;">Form · stats · H2H · team news</span>
    </summary>
    <div style="border-top:1px solid var(--border);padding:1rem;">
        @if(!empty($matchInsight['reasons']))
        <div style="margin-bottom:1rem;">
            <div style="font-size:.64rem;font-weight:900;letter-spacing:.07em;text-transform:uppercase;color:{{ $accent }};margin-bottom:.5rem;">Why this pick was selected</div>
            <div style="display:grid;gap:.45rem;">
                @foreach($matchInsight['reasons'] as $reason)
                <div style="font-size:.74rem;line-height:1.5;color:var(--text-dim);padding:.55rem .65rem;border-left:2px solid {{ $accent }};background:rgba(255,255,255,.025);">{{ $reason }}</div>
                @endforeach
            </div>
        </div>
        @endif

        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.7rem;">
            @foreach(['home', 'away'] as $side)
                @php($team = $matchInsight[$side])
                <div style="border:1px solid var(--border);border-radius:9px;padding:.75rem;min-width:0;">
                    <div style="display:flex;align-items:center;gap:.45rem;margin-bottom:.55rem;">
                        @if(!empty($team['logo']))<img src="{{ $team['logo'] }}" alt="" loading="lazy" style="width:25px;height:25px;object-fit:contain;">@endif
                        <strong style="font-size:.76rem;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $team['name'] }}</strong>
                    </div>
                    <div style="display:flex;gap:3px;flex-wrap:wrap;margin-bottom:.55rem;">
                        @forelse($team['form'] as $form)
                            @php($colour = $form['result'] === 'W' ? '#6ee7b7' : ($form['result'] === 'L' ? '#fca5a5' : '#fcd34d'))
                            <span title="{{ $form['venue'] }} {{ $form['score'] }} vs {{ $form['opponent'] }}" style="display:inline-grid;place-items:center;width:18px;height:18px;border-radius:50%;font-size:.58rem;font-weight:900;color:{{ $colour }};background:color-mix(in srgb, {{ $colour }} 14%, transparent);border:1px solid color-mix(in srgb, {{ $colour }} 30%, transparent);">{{ $form['result'] }}</span>
                        @empty
                            <span style="font-size:.68rem;color:var(--text-dim);">No recent results stored yet</span>
                        @endforelse
                    </div>
                    @if(($team['recent']['played'] ?? 0) > 0)
                    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:.35rem;font-size:.65rem;color:var(--text-dim);">
                        <span>Form <b style="color:#fff;float:right;">{{ $team['recent']['wins'] }}-{{ $team['recent']['draws'] }}-{{ $team['recent']['losses'] }}</b></span>
                        <span>Goals <b style="color:#fff;float:right;">{{ $team['recent']['goals_for'] }}:{{ $team['recent']['goals_against'] }}</b></span>
                        <span>Scored/game <b style="color:#fff;float:right;">{{ $team['recent']['gpg'] }}</b></span>
                        <span>Conceded/game <b style="color:#fff;float:right;">{{ $team['recent']['cpg'] }}</b></span>
                    </div>
                    @endif
                </div>
            @endforeach
        </div>

        @if(($matchInsight['h2h']['total'] ?? 0) > 0)
        <div style="margin-top:1rem;">
            <div style="font-size:.64rem;font-weight:900;letter-spacing:.07em;text-transform:uppercase;color:var(--text-dim);margin-bottom:.5rem;">Head to head · last {{ $matchInsight['h2h']['total'] }}</div>
            <div style="display:flex;gap:.35rem;align-items:center;flex-wrap:wrap;margin-bottom:.5rem;font-size:.68rem;">
                <span style="color:#fff;font-weight:800;">{{ $matchInsight['home']['name'] }} {{ $matchInsight['h2h']['home_wins'] }}</span><span style="color:var(--text-dim);">wins</span>
                <span style="color:var(--text-dim);">·</span><span style="color:#fcd34d;font-weight:800;">{{ $matchInsight['h2h']['draws'] }} draws</span>
                <span style="color:var(--text-dim);">·</span><span style="color:#fff;font-weight:800;">{{ $matchInsight['away']['name'] }} {{ $matchInsight['h2h']['away_wins'] }}</span><span style="color:var(--text-dim);">wins</span>
            </div>
            <div style="display:grid;gap:.3rem;">
                @foreach($matchInsight['h2h']['results'] as $result)
                <div style="display:flex;justify-content:space-between;gap:.75rem;font-size:.68rem;color:var(--text-dim);"><span>{{ $result['score'] }}</span><span style="white-space:nowrap;color:var(--text-muted);">{{ $result['date'] }}</span></div>
                @endforeach
            </div>
        </div>
        @endif

        @if(!empty($matchInsight['injuries']['home']) || !empty($matchInsight['injuries']['away']))
        <div style="margin-top:1rem;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.7rem;">
            @foreach(['home', 'away'] as $side)
                @if(!empty($matchInsight['injuries'][$side]))
                <div style="padding:.7rem;border-radius:8px;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.18);">
                    <div style="font-size:.65rem;font-weight:800;color:#fca5a5;margin-bottom:.35rem;">🏥 {{ $matchInsight[$side]['name'] }} unavailable</div>
                    @foreach($matchInsight['injuries'][$side] as $injury)<div style="font-size:.67rem;color:var(--text-dim);line-height:1.5;">{{ $injury['player'] }} · {{ $injury['reason'] }}</div>@endforeach
                </div>
                @endif
            @endforeach
        </div>
        @endif
    </div>
</details>
@endif
