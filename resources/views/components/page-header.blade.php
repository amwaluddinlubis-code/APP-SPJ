@props([
    'title',
    'subtitle' => null,
    'description' => null,
    'kicker' => null,
    'gradient' => 'theme',
    'icon' => null,
])

@php($headerDescription = $subtitle ?: $description)

<section {{ $attributes->class(['page-header-shell overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm']) }}>
    <div class="page-header-main relative overflow-hidden {{ $gradient === 'theme' ? 'theme-header' : 'bg-gradient-to-br '.$gradient }} px-5 py-7 text-white sm:px-7 lg:py-8">
        <div class="pointer-events-none absolute -right-16 -top-20 h-56 w-56 rounded-full bg-white/10 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-24 left-1/3 h-48 w-48 rounded-full bg-black/10 blur-3xl"></div>

        <div class="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0 max-w-3xl">
                @if($kicker)
                    <p class="page-header-kicker text-[11px] font-bold uppercase tracking-[.2em] text-sky-200">{{ $kicker }}</p>
                @endif

                <div @class(['flex items-start gap-3', 'mt-2' => $kicker])>
                    @if($icon)
                        <span class="hidden h-10 w-10 shrink-0 place-items-center rounded-xl border border-white/15 bg-white/10 text-white shadow-sm backdrop-blur-sm sm:grid">{{ $icon }}</span>
                    @endif
                    <div class="min-w-0">
                        <h1 class="page-header-title text-2xl font-bold tracking-tight text-white sm:text-3xl">{{ $title }}</h1>
                        @if($headerDescription)
                            <p class="page-header-description mt-2 max-w-3xl text-sm leading-6 text-indigo-100 sm:text-base">{{ $headerDescription }}</p>
                        @endif
                    </div>
                </div>
            </div>

            @if(isset($actions))
                <div class="page-header-actions flex shrink-0 flex-wrap gap-2 lg:justify-end">{{ $actions }}</div>
            @endif
        </div>
    </div>

    @if(isset($slot) && trim($slot) !== '')
        <div class="page-header-summary">{{ $slot }}</div>
    @endif
</section>
