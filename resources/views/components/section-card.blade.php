@props([
    'title',
    'description' => null,
    'padding' => true,
])

<section {{ $attributes->class(['overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm']) }}>
    <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
            <h2 class="font-bold text-slate-800">{{ $title }}</h2>
            @if($description)
                <p class="mt-1 text-sm text-slate-500 sm:text-base">{{ $description }}</p>
            @endif
        </div>

        @if(isset($actions))
            <div class="flex shrink-0 flex-wrap gap-2">
                {{ $actions }}
            </div>
        @endif
    </div>

    <div @class(['p-5 sm:p-6' => $padding])>
        {{ $slot }}
    </div>
</section>
