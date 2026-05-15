<!doctype html>
<html>
<body style="margin:0; padding:0; background:#f5f7fb; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f7fb; padding:24px 12px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width:620px; background:#ffffff; border-radius:14px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,.06);">

                    {{-- Header --}}
                    <tr>
                        <td style="padding:26px 30px; background:linear-gradient(135deg,#f59e0b,#d97706); color:#fff;">
                            <div style="font-size:12px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; opacity:.9;">⭐ Daily Football Intelligence</div>
                            <div style="font-size:24px; font-weight:800; margin-top:5px; line-height:1.25;">Today's 3 Best Picks</div>
                            <div style="font-size:13px; opacity:.85; margin-top:4px;">{{ $dateLabel }}</div>
                        </td>
                    </tr>

                    {{-- Intro --}}
                    <tr>
                        <td style="padding:22px 30px 8px; color:#374151; font-size:14px; line-height:1.65;">
                            <p style="margin:0;">
                                Our AI scanned today's matches and these 3 stood out.
                                Each pick comes with the reasoning, confidence level, and a link back to full analysis.
                            </p>
                        </td>
                    </tr>

                    {{-- Picks --}}
                    <tr>
                        <td style="padding:8px 30px 22px;">
                            @foreach($picks as $i => $pick)
                            @php
                                $rankBadge = $i === 0 ? '🔥 Pick of the Day' : ($i === 1 ? '⭐ Pick #2' : '🚀 Pick #3');
                                $headline  = $pick['tips'][0] ?? null;
                                $confPct   = $headline ? (int) ($headline['confidence'] ?? 0) : (int) ($pick['confidence_pct'] ?? 0);
                                $band      = \App\Support\PickHelpers::confidenceBand($confPct);
                                $marketLbl = $headline['market'] ?? $pick['pick_label'] ?? '-';
                                $reasons   = $pick['reasons'] ?? [];
                                $rationale = $headline['rationale'] ?? null;
                            @endphp
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:18px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:11px;">
                                <tr>
                                    <td style="padding:18px 20px;">
                                        {{-- Rank + match --}}
                                        <div style="font-size:11px; font-weight:800; color:#d97706; letter-spacing:.05em; text-transform:uppercase; margin-bottom:5px;">{{ $rankBadge }}</div>
                                        <div style="font-size:11px; color:#6b7280; font-weight:600;">{{ $pick['match']['league'] ?? '' }}</div>
                                        <div style="font-size:18px; font-weight:800; color:#111827; margin:6px 0;">
                                            {{ $pick['match']['home'] ?? '?' }}
                                            <span style="color:#9ca3af; font-weight:600;">vs</span>
                                            {{ $pick['match']['away'] ?? '?' }}
                                        </div>
                                        <div style="font-size:12px; color:#6b7280; margin-bottom:14px;">⏰ Kick-off {{ $pick['match']['time'] ?? '' }}</div>

                                        {{-- Best pick block --}}
                                        <table width="100%" cellpadding="0" cellspacing="0" style="background:#ecfdf5; border:1px solid #a7f3d0; border-radius:9px;">
                                            <tr>
                                                <td style="padding:13px 16px;">
                                                    <div style="font-size:10px; font-weight:800; color:#059669; letter-spacing:.06em; text-transform:uppercase; margin-bottom:4px;">🎯 Prediction</div>
                                                    <table width="100%" cellpadding="0" cellspacing="0">
                                                        <tr>
                                                            <td style="font-size:17px; font-weight:800; color:#065f46; line-height:1.3;">
                                                                {{ $marketLbl }}
                                                            </td>
                                                            <td align="right" style="white-space:nowrap;">
                                                                <span style="font-size:13px; background:#10b981; color:#fff; padding:3px 10px; border-radius:99px; font-weight:800;">{{ $confPct }}%</span>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                    <div style="font-size:13px; font-weight:700; margin-top:8px; color:{{ $band['color'] }};">
                                                        {{ $band['emoji'] }} {{ $band['label'] }} confidence
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>

                                        {{-- Why --}}
                                        @if(!empty($reasons))
                                        <div style="margin-top:14px;">
                                            <div style="font-size:11px; font-weight:800; color:#374151; letter-spacing:.05em; text-transform:uppercase; margin-bottom:6px;">💡 Why we like it</div>
                                            <ul style="margin:0; padding-left:20px; color:#374151; font-size:13.5px; line-height:1.7;">
                                                @foreach($reasons as $reason)
                                                <li>{{ $reason }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                        @elseif($rationale)
                                        <div style="margin-top:14px; font-size:13.5px; color:#374151; line-height:1.65;">
                                            <strong style="color:#1f2937;">💡 Why:</strong> {{ $rationale }}
                                        </div>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                            @endforeach

                            {{-- CTA --}}
                            <p style="margin:18px 0 4px; text-align:center;">
                                <a href="{{ url('/picks') }}" style="display:inline-block; background:linear-gradient(135deg,#10b981,#059669); color:#fff; text-decoration:none; padding:13px 28px; border-radius:9px; font-weight:800; font-size:14px; box-shadow:0 4px 12px rgba(16,185,129,.25);">
                                    See full analysis on TavsScore →
                                </a>
                            </p>
                        </td>
                    </tr>

                    {{-- Yesterday's recap --}}
                    @if($yesterdayRecap->isNotEmpty())
                    @php
                        $resolved = $yesterdayRecap->whereNotNull('was_correct');
                        $correct  = $resolved->where('was_correct', true)->count();
                        $total    = $resolved->count();
                    @endphp
                    <tr>
                        <td style="padding:6px 30px 22px;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="background:#fefce8; border:1px solid #fde68a; border-radius:11px;">
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <div style="font-size:11px; font-weight:800; color:#92400e; letter-spacing:.05em; text-transform:uppercase; margin-bottom:8px;">
                                            📊 Yesterday's recap
                                            @if($total > 0)
                                                <span style="float:right; color:{{ $correct >= ($total - $correct) ? '#059669' : '#dc2626' }};">{{ $correct }}/{{ $total }} correct</span>
                                            @endif
                                        </div>
                                        @foreach($yesterdayRecap as $y)
                                        <div style="font-size:13px; color:#374151; line-height:1.7; padding:3px 0;">
                                            @if($y['was_correct'] === true)
                                                <span style="color:#059669; font-weight:800;">✅</span>
                                            @elseif($y['was_correct'] === false)
                                                <span style="color:#dc2626; font-weight:800;">❌</span>
                                            @else
                                                <span style="color:#9ca3af;">⏳</span>
                                            @endif
                                            <strong>{{ $y['home'] }} vs {{ $y['away'] }}</strong>
                                            @if($y['home_score'] !== null && $y['away_score'] !== null)
                                                <span style="color:#6b7280;">({{ $y['home_score'] }}–{{ $y['away_score'] }})</span>
                                            @endif
                                            <span style="color:#6b7280;"> - picked {{ $y['outcome'] }}</span>
                                        </div>
                                        @endforeach
                                        <div style="margin-top:10px; font-size:11px; color:#92400e;">
                                            We publish every result - wins and misses. <a href="{{ url('/stats') }}" style="color:#92400e; font-weight:600;">See full track record →</a>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    @endif

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:18px 30px; background:#f9fafb; color:#6b7280; font-size:12px; text-align:center; line-height:1.7; border-top:1px solid #e5e7eb;">
                            <strong style="color:#374151;">For entertainment only.</strong> Football has variance - even high-confidence picks lose. Don't bet money you can't afford to lose.<br>
                            <a href="{{ $unsubscribeUrl }}" style="color:#9ca3af; text-decoration:underline;">Unsubscribe</a> ·
                            <a href="{{ url('/') }}" style="color:#9ca3af; text-decoration:underline;">tavsscore.com</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
