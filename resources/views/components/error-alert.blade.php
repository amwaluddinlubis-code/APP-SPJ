@props(['message' => null, 'type' => 'error'])
@if($message)
    <div class="mb-5 rounded-xl border {{ $type === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-amber-200 bg-amber-50 text-amber-800' }} px-4 py-3">
        <div class="flex items-start gap-3">
            <span class="text-xl">{{ $type === 'error' ? '⚠️' : '⚡' }}</span>
            <div class="flex-1">
                <p class="font-semibold">{{ $type === 'error' ? 'Terjadi Kesalahan' : 'Perhatian' }}</p>
                <p class="mt-1 text-sm">{{ $message }}</p>
            </div>
            <button type="button" class="rounded-full p-1 hover:bg-white/50" onclick="this.parentElement.parentElement.remove()">
                <span class="sr-only">Tutup</span>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>
@endif
