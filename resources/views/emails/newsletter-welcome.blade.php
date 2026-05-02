<!doctype html>
<html>
<body style="margin:0; padding:0; background:#f5f7fb; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f7fb; padding:32px 16px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; background:#ffffff; border-radius:14px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,.06);">

                    {{-- Header --}}
                    <tr>
                        <td style="padding:32px 32px 24px; background:linear-gradient(135deg,#10b981,#059669); color:#fff; text-align:center;">
                            <div style="font-size:48px; line-height:1; margin-bottom:8px;">⚽</div>
                            <div style="font-size:13px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; opacity:.85;">TavsScore</div>
                            <div style="font-size:26px; font-weight:800; margin-top:6px; line-height:1.3;">You're in! 🎉</div>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:30px 32px 24px; color:#1f2937; font-size:15px; line-height:1.7;">
                            <p style="margin:0 0 14px; font-size:16px;">Welcome to the squad! 👋</p>
                            <p style="margin:0 0 14px;">
                                You're now subscribed to <strong>TavsScore Daily Picks</strong> — the
                                AI-powered football tips you'll actually look forward to opening.
                            </p>
                            <p style="margin:0 0 14px;">
                                Starting <strong style="color:#059669;">tomorrow at 09:00 Lagos time</strong>,
                                we'll send today's 3 best picks straight to your inbox.
                                Free, every single day.
                            </p>

                            {{-- Benefits --}}
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0; background:#f9fafb; border-radius:10px; border:1px solid #e5e7eb;">
                                <tr>
                                    <td style="padding:18px 22px;">
                                        <div style="font-size:11px; font-weight:800; color:#059669; text-transform:uppercase; letter-spacing:.06em; margin-bottom:10px;">
                                            What you'll get
                                        </div>
                                        <table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; color:#374151; line-height:1.65;">
                                            <tr><td style="padding:4px 0;">⭐ <strong>3 best AI-picked bets</strong> from today's matches</td></tr>
                                            <tr><td style="padding:4px 0;">🎯 Confidence % + reasoning for every tip</td></tr>
                                            <tr><td style="padding:4px 0;">🤝 Cross-validated by <strong>two AIs</strong> (Groq + Gemini)</td></tr>
                                            <tr><td style="padding:4px 0;">📊 Bookmaker consensus check on every pick</td></tr>
                                            <tr><td style="padding:4px 0;">🇳🇬 Pidgin &amp; 🇰🇪 Swahili translations available on the site</td></tr>
                                            <tr><td style="padding:4px 0;">📜 <strong>Full track record published</strong> — see <a href="{{ url('/stats') }}" style="color:#059669; text-decoration:none; font-weight:600;">accuracy stats</a> publicly, no hiding misses</td></tr>
                                            <tr><td style="padding:4px 0;">🔓 100% free forever. No upsells, no premium tier.</td></tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            @if($samplePicks->isNotEmpty())
                            <p style="margin:24px 0 12px;"><strong style="color:#111827;">Here's a peek at today's picks 👇</strong></p>
                            @foreach($samplePicks as $i => $p)
                            @php
                                $headline = $p->tips[0] ?? null;
                                $rank     = $i === 0 ? '👑 Pick of the Day' : ($i === 1 ? '⭐ Pick #2' : '🔥 Pick #3');
                            @endphp
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:10px; background:#ecfdf5; border:1px solid #a7f3d0; border-radius:8px;">
                                <tr>
                                    <td style="padding:12px 16px;">
                                        <div style="font-size:10px; font-weight:800; color:#047857; letter-spacing:.06em; text-transform:uppercase; margin-bottom:4px;">{{ $rank }}</div>
                                        <div style="font-size:14px; font-weight:700; color:#111827;">{{ $p->match?->home_team }} vs {{ $p->match?->away_team }}</div>
                                        @if($headline)
                                        <div style="font-size:13px; color:#065f46; margin-top:4px;">
                                            <strong>{{ $headline['market'] }}</strong> · <span style="background:#10b981; color:#fff; padding:1px 7px; border-radius:99px; font-size:11px;">{{ $headline['confidence'] }}%</span>
                                        </div>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                            @endforeach
                            @endif

                            {{-- CTA --}}
                            <p style="margin:28px 0 8px; text-align:center;">
                                <a href="{{ $picksUrl }}" style="display:inline-block; background:linear-gradient(135deg,#10b981,#059669); color:#fff; text-decoration:none; padding:13px 32px; border-radius:9px; font-weight:800; font-size:15px; box-shadow:0 4px 12px rgba(16,185,129,.25);">
                                    See today's picks →
                                </a>
                            </p>

                            <p style="margin:28px 0 0; font-size:13px; color:#6b7280; padding-top:18px; border-top:1px solid #e5e7eb; line-height:1.6;">
                                <strong style="color:#374151;">For entertainment only.</strong>
                                Football picks involve variance — even high-confidence tips lose. Never bet money you can't afford to lose.
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:18px 32px; background:#f9fafb; color:#9ca3af; font-size:12px; text-align:center; line-height:1.6;">
                            TavsScore · Football live scores &amp; AI predictions<br>
                            <a href="{{ $unsubscribeUrl }}" style="color:#6b7280; text-decoration:underline;">Unsubscribe</a> ·
                            <a href="{{ url('/') }}" style="color:#6b7280; text-decoration:underline;">tavsscore.com</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
