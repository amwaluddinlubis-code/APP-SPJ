@props(['size' => 'md'])
<div class="flex items-center justify-center">
    @php
        $spinnerSize = match ($size) {
            'sm' => 'h-4 w-4',
            'lg' => 'h-8 w-8',
            default => 'h-6 w-6',
        };
    @endphp
    <div class="animate-spin rounded-full border-2 border-slate-200 border-t-indigo-600 {{ $spinnerSize }}" role="status" aria-label="Loading">
        <span class="sr-only">Loading...</span>
    </div>
</div>
