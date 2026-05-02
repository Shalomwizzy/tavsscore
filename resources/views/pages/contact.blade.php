@extends('layouts.app')
@section('title', 'Contact Us | TavsScore')
@section('meta_description', 'Get in touch with the TavsScore team — send us feedback, questions, or partnership enquiries about our football live scores and predictions platform.')
@section('canonical', route('contact'))

@push('styles')
<style>
    .contact-input, .contact-textarea, .contact-select {
        width: 100%;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: .6rem .875rem;
        color: var(--text);
        font-size: .85rem;
        font-family: inherit;
        outline: none;
        transition: border-color 160ms;
    }
    .contact-input:focus, .contact-textarea:focus, .contact-select:focus {
        border-color: var(--green-border);
        box-shadow: 0 0 0 3px rgba(16,185,129,.08);
    }
    .contact-input::placeholder, .contact-textarea::placeholder { color: var(--text-muted); }
    .contact-textarea { resize: vertical; min-height: 130px; }
    .contact-label { display: block; font-size: .78rem; font-weight: 700; color: var(--text-dim); margin-bottom: .35rem; }
    .contact-field { margin-bottom: 1rem; }
    .contact-select option { background: #131d30; color: var(--text); }
    .form-notice {
        padding: .875rem 1rem;
        border-radius: 8px;
        font-size: .82rem;
        font-weight: 600;
        display: none;
    }
    .form-notice.success { background: var(--green-dim); border: 1px solid var(--green-border); color: #6ee7b7; display: block; }
    .form-notice.error   { background: var(--red-dim);   border: 1px solid rgba(239,68,68,.3);  color: #fca5a5; display: block; }
</style>
@endpush

@section('content')
<div class="wrap" style="max-width:740px; padding-top:2rem; padding-bottom:3rem;">
    <nav style="font-size:.72rem; color:var(--text-dim); margin-bottom:1.5rem;">
        <a href="{{ route('home.index') }}" style="color:var(--text-dim); text-decoration:none;">Home</a>
        <span style="margin:0 .4rem; color:var(--text-muted)">›</span>
        <span>Contact</span>
    </nav>

    <h1 style="font-size:2rem; font-weight:900; color:#fff; letter-spacing:-.03em; margin-bottom:.4rem;">Contact Us</h1>
    <p style="font-size:.88rem; color:var(--text-dim); margin-bottom:2rem;">Have a question, spotted an error, or want to work with us? Drop us a message.</p>

    @if(session('success'))
        <div class="form-notice success" style="margin-bottom:1.25rem;">
            ✅ {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="form-notice error" style="margin-bottom:1.25rem;">
            ❌ Please fix the errors below and try again.
        </div>
    @endif

    <div style="display:grid; grid-template-columns:1fr 280px; gap:1.25rem; align-items:start;">

        {{-- Form --}}
        <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.5rem;">
            <form method="POST" action="{{ route('contact.send') }}">
                @csrf

                {{-- Honeypot fields (hidden from real users, filled by bots) --}}
                <div aria-hidden="true" style="position:absolute; left:-9999px; width:1px; height:1px; overflow:hidden;">
                    <label for="website">Website (leave empty)</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>
                <input type="hidden" name="_loaded_at" value="{{ time() }}">

                <div class="contact-field">
                    <label class="contact-label" for="name">Your Name</label>
                    <input id="name" name="name" type="text" class="contact-input"
                           placeholder="e.g. John Smith"
                           value="{{ old('name') }}" required maxlength="100">
                    @error('name')
                        <span style="font-size:.75rem; color:#fca5a5; margin-top:.3rem; display:block;">{{ $message }}</span>
                    @enderror
                </div>

                <div class="contact-field">
                    <label class="contact-label" for="email">Email Address</label>
                    <input id="email" name="email" type="email" class="contact-input"
                           placeholder="you@example.com"
                           value="{{ old('email') }}" required maxlength="200">
                    @error('email')
                        <span style="font-size:.75rem; color:#fca5a5; margin-top:.3rem; display:block;">{{ $message }}</span>
                    @enderror
                </div>

                <div class="contact-field">
                    <label class="contact-label" for="subject">Subject</label>
                    <select id="subject" name="subject" class="contact-select contact-input">
                        <option value="" disabled {{ old('subject') ? '' : 'selected' }}>Select a topic…</option>
                        <option value="General Enquiry"        {{ old('subject') === 'General Enquiry' ? 'selected' : '' }}>General Enquiry</option>
                        <option value="Bug / Data Error"       {{ old('subject') === 'Bug / Data Error' ? 'selected' : '' }}>Bug / Data Error</option>
                        <option value="Advertising"            {{ old('subject') === 'Advertising' ? 'selected' : '' }}>Advertising</option>
                        <option value="Partnership"            {{ old('subject') === 'Partnership' ? 'selected' : '' }}>Partnership</option>
                        <option value="Content Removal"        {{ old('subject') === 'Content Removal' ? 'selected' : '' }}>Content Removal</option>
                        <option value="Other"                  {{ old('subject') === 'Other' ? 'selected' : '' }}>Other</option>
                    </select>
                    @error('subject')
                        <span style="font-size:.75rem; color:#fca5a5; margin-top:.3rem; display:block;">{{ $message }}</span>
                    @enderror
                </div>

                <div class="contact-field">
                    <label class="contact-label" for="message">Message</label>
                    <textarea id="message" name="message" class="contact-textarea"
                              placeholder="Tell us more…" required maxlength="2000">{{ old('message') }}</textarea>
                    @error('message')
                        <span style="font-size:.75rem; color:#fca5a5; margin-top:.3rem; display:block;">{{ $message }}</span>
                    @enderror
                </div>

                <button type="submit" class="btn-ts btn-green" style="width:100%; justify-content:center;">
                    Send Message
                </button>
            </form>
        </div>

        {{-- Info sidebar --}}
        <div style="display:flex; flex-direction:column; gap:.875rem;">
            <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.25rem;">
                <div style="font-size:.78rem; font-weight:800; color:#fff; margin-bottom:.6rem;">📬 Response Time</div>
                <p style="font-size:.78rem; color:var(--text-dim); line-height:1.7;">We aim to respond to all messages within <strong style="color:var(--text);">1–2 business days</strong>.</p>
            </div>

            <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.25rem;">
                <div style="font-size:.78rem; font-weight:800; color:#fff; margin-bottom:.6rem;">⚽ Data &amp; Scores</div>
                <p style="font-size:.78rem; color:var(--text-dim); line-height:1.7;">Match data is sourced from API-Football. For data errors please include the match name and date in your message.</p>
            </div>

            <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.25rem;">
                <div style="font-size:.78rem; font-weight:800; color:#fff; margin-bottom:.6rem;">📖 Quick Links</div>
                <div style="display:flex; flex-direction:column; gap:.4rem;">
                    <a href="{{ route('about') }}"   style="font-size:.78rem; color:var(--green); text-decoration:none;">→ About TavsScore</a>
                    <a href="{{ route('privacy') }}" style="font-size:.78rem; color:var(--green); text-decoration:none;">→ Privacy Policy</a>
                    <a href="{{ route('terms') }}"   style="font-size:.78rem; color:var(--green); text-decoration:none;">→ Terms of Service</a>
                    <a href="{{ route('blog.index') }}" style="font-size:.78rem; color:var(--green); text-decoration:none;">→ Blog</a>
                </div>
            </div>
        </div>
    </div>

    <style>
        @media (max-width: 640px) {
            .contact-grid { grid-template-columns: 1fr !important; }
        }
    </style>
</div>
@endsection
