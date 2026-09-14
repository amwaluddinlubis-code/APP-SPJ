@if($error)
    <div class="rounded-xl border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $error }}</div>
@endif
@if(session('success'))
    <div class="rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
@endif
