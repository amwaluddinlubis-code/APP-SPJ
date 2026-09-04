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
<body class="min-h-full bg-slate-50 text-slate-800">
    <x-toast-notifications />
    <main class="mx-auto flex min-h-screen max-w-3xl items-center p-4 sm:p-8">
        <section class="w-full overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-200/40">
            <div class="flex items-center justify-between bg-gradient-to-r from-slate-950 via-indigo-900 to-sky-800 px-5 py-4 text-white">
                <a href="{{ route('login') }}" class="font-bold">SPJ BOSP Web</a>
                <select id="public-theme-select" data-theme-selector class="rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-xs font-bold text-white" aria-label="Tema tampilan"></select>
            </div>
            <div class="p-5 sm:p-7">
                @if($errors->any())<div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800"><b>Data belum dapat diproses.</b><ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                {{ $slot }}
            </div>
        </section>
    </main>
    @vite('resources/js/app.js')
</body>
</html>
