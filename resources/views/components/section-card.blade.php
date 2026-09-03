@props([
    'title',
    'description' => null,
    'padding' => true,
])

<section {{ $attributes->class(['group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition duration-200']) }}>
    <div class="relative flex flex-col gap-3 border-b border-slate-100 bg-gradient-to-r from-slate-50 via-white to-slate-50/70 px-5 py-4 pl-6 sm:px-6 sm:pl-7 lg:flex-row lg:items-center lg:justify-between">
        <span class="absolute bottom-3 left-2.5 top-3 w-1 rounded-full theme-bg opacity-80"></span>

        <div class="min-w-0">
            <h2 class="text-base font-extrabold tracking-tight text-slate-900">{{ $title }}</h2>
            @if($description)
                <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-500">{{ $description }}</p>
            @endif
        </div>

        @if(isset($actions))
            <div class="flex shrink-0 flex-wrap gap-2">
                {{ $actions }}
            </div>
        @endif
    </div>

    <div @class(['p-5 sm:p-6' => $padding, 'bg-white'])>
        {{ $slot }}
    </div>
</section>
