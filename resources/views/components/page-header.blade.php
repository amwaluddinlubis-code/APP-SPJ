@props(['title', 'subtitle' => null, 'kicker' => null, 'gradient' => 'theme', 'icon' => null])
<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="relative overflow-hidden {{ $gradient === 'theme' ? 'theme-header' : 'bg-gradient-to-br '.$gradient }} px-5 py-7 text-white sm:px-7 lg:py-8">
        <div class="pointer-events-none absolute -right-16 -top-20 h-56 w-56 rounded-full bg-white/10 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-24 left-1/3 h-48 w-48 rounded-full bg-black/10 blur-3xl"></div>

        <div class="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0 max-w-3xl">
                @if($kicker)
                    <p class="inline-flex rounded-full border border-white/15 bg-white/10 px-2.5 py-1 text-[11px] font-bold uppercase tracking-[.18em] text-white/85 shadow-sm backdrop-blur-sm">{{ $kicker }}</p>
                @endif

                <div class="mt-3 flex items-start gap-3">
                    @if($icon)
                        <span class="hidden h-10 w-10 shrink-0 place-items-center rounded-xl border border-white/15 bg-white/10 text-white shadow-sm backdrop-blur-sm sm:grid">{{ $icon }}</span>
                    @endif
                    <div class="min-w-0">
                        <h1 class="text-2xl font-extrabold tracking-tight text-white sm:text-3xl">{{ $title }}</h1>
                        @if($subtitle)
                            <p class="mt-2 max-w-3xl text-sm leading-6 text-white/80 sm:text-base">{{ $subtitle }}</p>
                        @endif
                    </div>
                </div>
            </div>

            @if(isset($actions))
                <div class="flex shrink-0 flex-wrap gap-2 lg:justify-end">{{ $actions }}</div>
            @endif
        </div>
    </div>

    @if(isset($slot) && trim($slot) !== '')
        <div class="border-t border-slate-100 bg-gradient-to-b from-white to-slate-50/60">{{ $slot }}</div>
    @endif
</section>
