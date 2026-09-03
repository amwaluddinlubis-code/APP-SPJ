@props(['title', 'subtitle' => null, 'kicker' => null, 'gradient' => 'theme', 'icon' => null])
<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="relative overflow-hidden {{ $gradient === 'theme' ? 'theme-header' : 'bg-gradient-to-br '.$gradient }} px-5 py-7 text-white sm:px-7 lg:py-8">
        <div class="pointer-events-none absolute -right-20 -top-24 h-64 w-64 rounded-full bg-violet-400/10 blur-3xl"></div>
        @if(isset($breadcrumb))<div class="relative mb-4">{{ $breadcrumb }}</div>@endif
        @if($kicker)<p class="relative text-[11px] font-bold uppercase tracking-[.2em] text-sky-200">{{ $kicker }}</p>@endif
        <div class="relative mt-2 flex items-start gap-3">
            @if($icon)<span class="hidden sm:grid h-9 w-9 place-items-center rounded-lg bg-white/15 text-white ring-1 ring-white/20">{{ $icon }}</span>@endif
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">{{ $title }}</h1>
                @if($subtitle)<p class="mt-2 max-w-3xl text-sm leading-6 text-indigo-100 sm:text-base">{{ $subtitle }}</p>@endif
            </div>
        </div>
        @if(isset($actions))<div class="relative mt-5 flex flex-wrap gap-2">{{ $actions }}</div>@endif
    </div>
    @if(isset($slot) && trim($slot) !== '')<div class="border-t border-slate-100 bg-white px-5 py-4 sm:px-7">{{ $slot }}</div>@endif
</section>
