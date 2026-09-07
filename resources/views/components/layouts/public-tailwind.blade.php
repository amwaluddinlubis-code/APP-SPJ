<!doctype html>
<html lang="id" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name', 'SPJ BOSP Web') }}</title>
    <script>
        (() => {
            const saved = localStorage.getItem('spj-theme');
            const theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.dataset.theme = theme;
            document.documentElement.classList.toggle('dark', saved ? saved === 'dark' : theme === 'dark');
        })();
    </script>
    @vite('resources/css/app.css')
</head>

<body class="public-layout min-h-full bg-[var(--ui-surface-soft)] text-[var(--ui-fg)]">
    <x-toast-notifications />
    <main class="public-layout-main mx-auto flex min-h-screen max-w-3xl items-center p-4 sm:p-8">
        <section
            class="w-full overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-xl shadow-slate-200/40">
            <div
                class="flex items-center justify-between gap-3 bg-gradient-to-r from-slate-950 via-indigo-900 to-sky-800 px-5 py-4 text-white">
                <div class="flex min-w-0 items-center gap-3">
                    <select id="public-theme-select" data-theme-selector
                        class="max-w-[9.5rem] rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-xs font-bold text-white"
                        aria-label="Tema tampilan"></select>
                    <a href="{{ route('login') }}" class="truncate font-bold">SPJ BOSP Web</a>
                </div>
            </div>
            <div class="p-5 sm:p-7">
                @if ($errors->any())
                    <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800"><b>Data
                            belum dapat diproses.</b>
                        <ul class="mt-1 list-disc pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                {{ $slot }}
            </div>
        </section>
    </main>
    @vite('resources/js/app.js')
</body>

</html>
