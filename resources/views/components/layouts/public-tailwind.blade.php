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
            document.documentElement.classList.toggle('dark', theme !== 'light');
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
                <select id="public-theme-select" class="rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-xs font-bold text-white"><option value="light">Terang</option><option value="dark">Gelap</option><option value="slate">Slate</option><option value="blue">Blue</option><option value="indigo">Indigo</option><option value="violet">Violet</option><option value="cyan">Cyan</option><option value="emerald">Emerald</option><option value="amber">Amber</option><option value="rose">Rose</option><option value="fuchsia">Fuchsia</option></select>
            </div>
            <div class="p-5 sm:p-7">
                @if($errors->any())<div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800"><b>Data belum dapat diproses.</b><ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                {{ $slot }}
            </div>
        </section>
    </main>
    <script>
        (() => {
            const select = document.getElementById('public-theme-select');
            const root = document.documentElement;
            const palettes = ['gray', 'zinc', 'neutral', 'stone', 'red', 'orange', 'yellow', 'lime', 'green', 'teal', 'sky', 'purple', 'pink'];
            palettes.forEach((palette) => { if (!select?.querySelector(`option[value="${palette}"]`)) { const option = document.createElement('option'); option.value = palette; option.textContent = palette[0].toUpperCase() + palette.slice(1); select?.append(option); } });
            if (select) select.value = root.dataset.theme || 'light';
            select?.addEventListener('change', () => { root.dataset.theme = select.value; root.classList.toggle('dark', select.value !== 'light'); localStorage.setItem('spj-theme', select.value); });
        })();
    </script>
    @vite('resources/js/app.js')
</body>
</html>
