@extends('layouts.admin')
@section('title', 'Edit Winner — ' . $winner->username)
@section('page-title', 'Edit Winner')

@section('content')
<div style="max-width:720px;">

    <div style="display:flex; align-items:center; gap:.75rem; margin-bottom:1.25rem;">
        <a href="{{ route('admin.winners.index') }}" style="color:var(--dim); font-size:.78rem; text-decoration:none;">← Winners</a>
        <span style="color:var(--dim);">/</span>
        <span style="font-size:.78rem; color:#fff;">{{ $winner->username }}</span>
    </div>

    @if(session('success'))
        <div class="alert alert-green" style="margin-bottom:1rem;">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-red" style="margin-bottom:1rem;">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.winners.update', $winner) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        {{-- Current photos --}}
        @php $photos = $winner->screenshot_urls; @endphp
        <div class="a-card" style="margin-bottom:1.1rem;">
            <div style="font-weight:700; color:#fff; font-size:.82rem; margin-bottom:.85rem;">📷 Current Photos</div>

            @if(empty($photos))
                <p style="font-size:.75rem; color:var(--dim);">No photos on file.</p>
            @else
            <div style="display:flex; flex-wrap:wrap; gap:.6rem; margin-bottom:.75rem;">
                @foreach($photos as $url)
                <div style="position:relative; width:140px;">
                    <img src="{{ $url }}" style="width:140px; height:105px; object-fit:cover; border-radius:8px; border:1px solid var(--border);">
                    <label style="position:absolute; top:.3rem; right:.3rem; cursor:pointer; background:rgba(239,68,68,.85); border-radius:50%; width:24px; height:24px; display:flex; align-items:center; justify-content:center; font-size:.75rem;" title="Mark for removal">
                        <input type="checkbox" name="remove_photos[]" value="{{ $url }}" style="display:none;" class="remove-cb">
                        <span class="remove-x">✕</span>
                    </label>
                    <div class="remove-overlay" style="display:none; position:absolute; inset:0; background:rgba(239,68,68,.45); border-radius:8px; pointer-events:none;"></div>
                </div>
                @endforeach
            </div>
            <p style="font-size:.68rem; color:var(--dim);">Tick ✕ on any photo to delete it when you save.</p>
            @endif

            {{-- Upload new photos --}}
            <div style="margin-top:.85rem;">
                <label style="font-size:.75rem; font-weight:700; color:#fff; display:block; margin-bottom:.35rem;">Add / Replace Photos</label>
                <input type="file" name="screenshots[]" multiple accept="image/*"
                       style="font-size:.78rem; color:var(--text);">
                <div style="font-size:.68rem; color:var(--dim); margin-top:.25rem;">Max 5 files · 5 MB each · JPG, PNG, WEBP</div>
            </div>
        </div>

        {{-- Details --}}
        <div class="a-card" style="margin-bottom:1.1rem;">
            <div style="font-weight:700; color:#fff; font-size:.82rem; margin-bottom:.85rem;">👤 Details</div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:.75rem; margin-bottom:.75rem;">
                <div>
                    <label style="font-size:.72rem; font-weight:700; color:#fff; display:block; margin-bottom:.3rem;">Username *</label>
                    <input type="text" name="username" value="{{ old('username', $winner->username) }}" required maxlength="60"
                           style="width:100%; background:var(--bg); border:1px solid var(--border); color:var(--text); border-radius:7px; padding:.5rem .65rem; font-size:.8rem; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:.72rem; font-weight:700; color:#fff; display:block; margin-bottom:.3rem;">Email</label>
                    <input type="email" name="email" value="{{ old('email', $winner->email) }}" maxlength="120"
                           style="width:100%; background:var(--bg); border:1px solid var(--border); color:var(--text); border-radius:7px; padding:.5rem .65rem; font-size:.8rem; box-sizing:border-box;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:.75rem; margin-bottom:.75rem;">
                <div>
                    <label style="font-size:.72rem; font-weight:700; color:#fff; display:block; margin-bottom:.3rem;">Platform / Bookmaker</label>
                    <input type="text" name="platform" value="{{ old('platform', $winner->platform) }}" maxlength="80"
                           style="width:100%; background:var(--bg); border:1px solid var(--border); color:var(--text); border-radius:7px; padding:.5rem .65rem; font-size:.8rem; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:.72rem; font-weight:700; color:#fff; display:block; margin-bottom:.3rem;">Match Details</label>
                    <input type="text" name="match_details" value="{{ old('match_details', $winner->match_details) }}" maxlength="200"
                           style="width:100%; background:var(--bg); border:1px solid var(--border); color:var(--text); border-radius:7px; padding:.5rem .65rem; font-size:.8rem; box-sizing:border-box;">
                </div>
            </div>

            <div style="margin-bottom:.75rem;">
                <label style="font-size:.72rem; font-weight:700; color:#fff; display:block; margin-bottom:.3rem;">Pick Description</label>
                <input type="text" name="pick_description" value="{{ old('pick_description', $winner->pick_description) }}" maxlength="300"
                       style="width:100%; background:var(--bg); border:1px solid var(--border); color:var(--text); border-radius:7px; padding:.5rem .65rem; font-size:.8rem; box-sizing:border-box;">
            </div>

            <div style="display:grid; grid-template-columns:120px 1fr; gap:.75rem; margin-bottom:.75rem;">
                <div>
                    <label style="font-size:.72rem; font-weight:700; color:#fff; display:block; margin-bottom:.3rem;">Currency</label>
                    <select name="currency" style="width:100%; background:var(--bg); border:1px solid var(--border); color:var(--text); border-radius:7px; padding:.5rem .5rem; font-size:.8rem;">
                        @foreach(['NGN','USD','GBP','EUR','KES','GHS','ZAR'] as $cur)
                            <option value="{{ $cur }}" {{ old('currency', $winner->currency ?? 'NGN') === $cur ? 'selected' : '' }}>{{ $cur }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="font-size:.72rem; font-weight:700; color:#fff; display:block; margin-bottom:.3rem;">Winning Amount</label>
                    <input type="number" name="winning_amount" value="{{ old('winning_amount', $winner->winning_amount) }}" min="0" step="0.01"
                           style="width:100%; background:var(--bg); border:1px solid var(--border); color:var(--text); border-radius:7px; padding:.5rem .65rem; font-size:.8rem; box-sizing:border-box;">
                </div>
            </div>

            <div style="display:flex; align-items:center; gap:.6rem;">
                <input type="hidden" name="is_approved" value="0">
                <input type="checkbox" name="is_approved" id="is_approved" value="1"
                       {{ old('is_approved', $winner->is_approved) ? 'checked' : '' }}
                       style="width:16px; height:16px; cursor:pointer; accent-color:#10b981;">
                <label for="is_approved" style="font-size:.78rem; color:#fff; cursor:pointer; font-weight:600;">Published / Approved</label>
            </div>
        </div>

        <div style="display:flex; gap:.75rem;">
            <button type="submit" class="btn-a btn-green" style="flex:1; justify-content:center; font-size:.85rem; padding:.65rem;">
                💾 Save Changes
            </button>
            <a href="{{ route('admin.winners.index') }}" class="btn-a" style="flex:0 0 auto; font-size:.82rem; padding:.65rem 1.1rem; background:rgba(255,255,255,.06); border:1px solid var(--border); color:var(--text); text-decoration:none;">
                Cancel
            </a>
        </div>
    </form>
</div>

<style>
.remove-cb:checked ~ .remove-x { color: #fff; }
</style>

<script>
document.querySelectorAll('.remove-cb').forEach(function(cb) {
    cb.addEventListener('change', function() {
        var card    = this.closest('div[style*="position:relative"]');
        var overlay = card.querySelector('.remove-overlay');
        overlay.style.display = this.checked ? 'block' : 'none';
    });
});
</script>
@endsection
