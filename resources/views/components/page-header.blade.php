@props(['title', 'subtitle' => null, 'kicker' => null, 'gradient' => 'theme', 'icon' => null])

<div class="space-y-3">
    @if(isset($breadcrumb))
        <div class="px-1">
            {{ $breadcrumb }}
        </div>
    @endif

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="relative overflow-hidden {{ $gradient === 'theme' ? 'theme-header' : 'bg-gradient-to-br '.$gradient }} px-5 py-7 text-white sm:px-7 lg:py-8">
            <div class="pointer-events-none absolute -right-20 -top-24 h-64 w-64 rounded-full bg-violet-400/10 blur-3xl"></div>

            <div class="relative flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    @if($kicker)
                        <p class="text-[11px] font-bold uppercase tracking-[.2em] text-sky-200">{{ $kicker }}</p>
                    @endif

                    <div class="mt-2 flex items-start gap-3">
                        @if($icon)
                            <span class="hidden h-9 w-9 shrink-0 place-items-center rounded-lg bg-white/15 text-white ring-1 ring-white/20 sm:grid">{{ $icon }}</span>
                        @endif

                        <div class="min-w-0">
                            <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">{{ $title }}</h1>
                            @if($subtitle)
                                <p class="mt-2 max-w-3xl text-sm leading-6 text-indigo-100 sm:text-base">{{ $subtitle }}</p>
                            @endif
                        </div>
                    </div>
                </div>

                @if(isset($actions))
                    <div class="relative flex shrink-0 flex-wrap gap-2">
                        {{ $actions }}
                    </div>
                @endif
            </div>
        </div>

        @if(isset($slot) && trim($slot) !== '')
            <div class="border-t border-slate-100 bg-white">
                {{ $slot }}
            </div>
        @endif
    </section>
</div>
