@extends('layouts.admin')
@section('title', 'Booking Code')
@section('page-title', 'Booking Code')

@section('content')

<div class="page-hd">
    <span class="page-hd-title">🎟️ Send Booking Code</span>
</div>

@if(session('success'))
<div style="background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.3);border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.82rem;color:#6ee7b7;">
    ✅ {{ session('success') }}
</div>
@endif

<div class="a-card" style="margin-bottom:1.25rem; border-color:rgba(99,102,241,.25);">
    <div class="page-hd" style="margin-bottom:1rem;">
        <span style="font-weight:700; font-size:.9rem; color:#fff;">📤 Send New Code</span>
    </div>

    <form method="POST" action="{{ route('admin.booking-code.send') }}">
        @csrf

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:.75rem; margin-bottom:.75rem;">
            <div>
                <label style="display:block; font-size:.75rem; color:var(--dim); margin-bottom:.3rem;">Platform</label>
                <select name="platform" required
                    style="width:100%; background:rgba(255,255,255,.05); border:1px solid var(--border); border-radius:6px; padding:.5rem .75rem; color:#fff; font-size:.85rem;">
                    <option value="SportyBet" {{ old('platform') === 'SportyBet' ? 'selected' : '' }}>SportyBet</option>
                    <option value="Bet9ja"    {{ old('platform') === 'Bet9ja'    ? 'selected' : '' }}>Bet9ja</option>
                    <option value="1xBet"     {{ old('platform') === '1xBet'     ? 'selected' : '' }}>1xBet</option>
                    <option value="1Win"      {{ old('platform') === '1Win'      ? 'selected' : '' }}>1Win</option>
                    <option value="Betway"    {{ old('platform') === 'Betway'    ? 'selected' : '' }}>Betway</option>
                    <option value="Parimatch" {{ old('platform') === 'Parimatch' ? 'selected' : '' }}>Parimatch</option>
                    <option value="BetKing"   {{ old('platform') === 'BetKing'   ? 'selected' : '' }}>BetKing</option>
                    <option value="NairaBet"  {{ old('platform') === 'NairaBet'  ? 'selected' : '' }}>NairaBet</option>
                </select>
                @error('platform') <span style="color:#fca5a5; font-size:.72rem;">{{ $message }}</span> @enderror
            </div>

            <div>
                <label style="display:block; font-size:.75rem; color:var(--dim); margin-bottom:.3rem;">Booking Code</label>
                <input type="text" name="code" value="{{ old('code') }}" required
                    placeholder="e.g. ABC12345"
                    style="width:100%; background:rgba(255,255,255,.05); border:1px solid var(--border); border-radius:6px; padding:.5rem .75rem; color:#fff; font-size:.85rem; font-family:monospace; letter-spacing:.05em; text-transform:uppercase; box-sizing:border-box;">
                @error('code') <span style="color:#fca5a5; font-size:.72rem;">{{ $message }}</span> @enderror
            </div>
        </div>

        <div style="margin-bottom:1rem;">
            <label style="display:block; font-size:.75rem; color:var(--dim); margin-bottom:.3rem;">Note <span style="opacity:.5;">(optional — what's included, e.g. "3 daily picks + Over 2.5")</span></label>
            <input type="text" name="note" value="{{ old('note') }}"
                placeholder="e.g. Today's 3 daily picks + Over 2.5 Goals"
                style="width:100%; background:rgba(255,255,255,.05); border:1px solid var(--border); border-radius:6px; padding:.5rem .75rem; color:#fff; font-size:.85rem; box-sizing:border-box;">
            @error('note') <span style="color:#fca5a5; font-size:.72rem;">{{ $message }}</span> @enderror
        </div>

        <button type="submit"
            style="background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;border:none;padding:.6rem 1.5rem;border-radius:7px;font-size:.85rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:.4rem;">
            📤 Send to Telegram &amp; Push
        </button>
    </form>
</div>

<div style="background:rgba(99,102,241,.07); border:1px solid rgba(99,102,241,.2); border-radius:8px; padding:.75rem 1rem; margin-bottom:1.25rem; font-size:.78rem; color:#a5b4fc; line-height:1.6;">
    💡 <strong>Affiliate links</strong> are managed in the <a href="{{ route('admin.affiliate-links.index') }}" style="color:#818cf8; text-decoration:underline;">Affiliate Links</a> section.
    When you send a booking code, the affiliate registration link for that platform is automatically appended to the Telegram message.
</div>

<div class="a-card">
    <div class="page-hd" style="margin-bottom:.875rem;">
        <span style="font-weight:700; font-size:.9rem; color:#fff;">📜 History</span>
    </div>

    @if($history->isEmpty())
        <div style="text-align:center; padding:1.75rem; color:var(--dim); font-size:.85rem;">No booking codes sent yet.</div>
    @else
        <div style="overflow-x:auto;">
            <table class="a-table">
                <thead>
                    <tr>
                        <th>Sent</th>
                        <th>Platform</th>
                        <th>Code</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($history as $item)
                    <tr>
                        <td style="color:var(--dim); font-size:.74rem; white-space:nowrap;">
                            {{ $item->created_at->setTimezone('Africa/Lagos')->format('M d, H:i') }}
                        </td>
                        <td>
                            <span style="background:rgba(99,102,241,.15);color:#a5b4fc;border:1px solid rgba(99,102,241,.3);padding:1px 8px;border-radius:999px;font-size:.72rem;font-weight:600;">
                                {{ $item->platform }}
                            </span>
                        </td>
                        <td style="font-family:monospace; font-weight:700; color:#fff; font-size:.9rem; letter-spacing:.05em;">
                            {{ strtoupper($item->code) }}
                        </td>
                        <td style="color:var(--dim); font-size:.78rem;">{{ $item->note ?: '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@endsection
