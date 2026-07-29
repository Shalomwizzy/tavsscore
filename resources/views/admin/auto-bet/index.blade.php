@extends('layouts.admin')

@section('title', 'Auto-Bet')

@section('content')
<div style="max-width:820px;">
    <h1 style="font-size:1.3rem; font-weight:700; margin:0 0 .35rem;">🤖 Auto-Bet (personal)</h1>
    <p style="color:var(--dim); font-size:.85rem; margin:0 0 1.25rem;">
        Stakes today's booking codes on <strong>your own</strong> SportyBet account, on your Mac.
        Real money — off until you arm it.
    </p>

    @if(session('success'))
        <div style="background:#d1fae5;border:1px solid #6ee7b7;color:#065f46;padding:.7rem 1rem;border-radius:6px;margin-bottom:1rem;font-size:.85rem;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div style="background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;padding:.7rem 1rem;border-radius:6px;margin-bottom:1rem;font-size:.85rem;">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.auto-bet.update') }}">
        @csrf
        <div style="background:var(--card);border:1px solid var(--border);border-radius:10px;padding:1.5rem;display:flex;flex-direction:column;gap:1.1rem;">

            <label style="display:flex;align-items:center;gap:.6rem;font-weight:700;font-size:.95rem;{{ $armed ? 'color:#059669' : '' }}">
                <input type="checkbox" name="autobet_enabled" value="1" {{ $armed ? 'checked' : '' }} style="width:18px;height:18px;">
                Arm auto-bet {{ $armed ? '(ARMED — placing real bets)' : '(off)' }}
            </label>

            <div class="sb-section-label">Global limits (₦)</div>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.8rem;">
                @foreach(['autobet_min_stake'=>'Min stake','autobet_max_stake'=>'Max stake','autobet_daily_cap'=>'Daily cap'] as $k=>$lbl)
                <label style="font-size:.8rem;color:var(--dim);">{{ $lbl }}
                    <input type="number" name="{{ $k }}" value="{{ $config[$k] }}" min="0" step="10" style="width:100%;margin-top:.3rem;padding:.5rem;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);">
                </label>
                @endforeach
            </div>

            <div class="sb-section-label">Stake by odds band (₦) — bigger stake on safer odds</div>
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:.8rem;">
                @foreach([
                    'autobet_stake_low_odds'=>'Odds 2–20 (safest)',
                    'autobet_stake_mid_odds'=>'Odds 20–200',
                    'autobet_stake_high_odds'=>'Odds ≥ 200 (longshots)',
                    'autobet_stake_high_risk'=>'High-risk tickets',
                ] as $k=>$lbl)
                <label style="font-size:.8rem;color:var(--dim);">{{ $lbl }}
                    <input type="number" name="{{ $k }}" value="{{ $config[$k] }}" min="10" step="10" style="width:100%;margin-top:.3rem;padding:.5rem;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);">
                </label>
                @endforeach
            </div>

            <button type="submit" style="align-self:flex-start;background:var(--green,#10b981);color:#04231a;font-weight:700;border:none;border-radius:8px;padding:.6rem 1.4rem;cursor:pointer;">Save rules</button>
        </div>
    </form>

    <div style="margin-top:1.5rem;background:var(--card);border:1px solid var(--border);border-radius:10px;padding:1.25rem;">
        <div class="sb-section-label" style="margin-bottom:.75rem;">Today's codes → planned stake ({{ $preview->count() }} codes, ₦{{ number_format($plannedTotal) }} total)</div>
        @if($preview->isEmpty())
            <p style="color:var(--dim);font-size:.85rem;margin:0;">No published codes for today yet.</p>
        @else
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:.82rem;">
                <thead><tr style="text-align:left;color:var(--dim);">
                    <th style="padding:.4rem .5rem;">Ticket</th><th>Code</th><th style="text-align:right;">Odds</th><th style="text-align:right;">Stake</th>
                </tr></thead>
                <tbody>
                @foreach($preview as $row)
                    <tr style="border-top:1px solid var(--border);">
                        <td style="padding:.45rem .5rem;">{{ $row['note'] }}</td>
                        <td style="font-family:monospace;">{{ $row['code'] }}</td>
                        <td style="text-align:right;font-variant-numeric:tabular-nums;">{{ number_format($row['odds'],2) }}</td>
                        <td style="text-align:right;font-variant-numeric:tabular-nums;font-weight:700;">₦{{ number_format($row['stake']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
@endsection
