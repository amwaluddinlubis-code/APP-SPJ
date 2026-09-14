@props(['title' => null, 'narrow' => false, 'cardClass' => null])
<!doctype html>
<html lang="id" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name', 'SPJ BOSP Web') }}</title>
    <x-theme-init />
    @vite('resources/css/app.css')
</head>

<body class="public-layout min-h-full text-[var(--ui-fg)]"
    style="background: linear-gradient(165deg, color-mix(in srgb, var(--theme-accent-soft) 55%, var(--ui-surface-soft)) 0%, var(--ui-surface-soft) 58%, var(--ui-surface-soft) 100%);">
    <x-toast-notifications />
    <main
        class="public-layout-main mx-auto flex min-h-screen items-center p-4 sm:p-8 {{ $narrow ? 'max-w-xl' : 'max-w-3xl' }} {{ str_contains((string) $cardClass, 'auth-login-surface') ? 'auth-login-page' : '' }} {{ request()->routeIs('schools.select', 'years.select') ? 'context-selector-page' : '' }}">
        <section
            class="w-full rounded-2xl border border-[var(--ui-line)] {{ $cardClass ?? (request()->routeIs('schools.select', 'years.select') ? 'context-selector-surface' : 'bg-[var(--ui-surface-base)]') }} p-5 shadow-xl sm:p-7"
            style="box-shadow: var(--profile-floating-shadow, 0 20px 45px rgb(15 23 42 / .12));">
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
        </section>
    </main>
    <div
        class="fixed bottom-4 right-4 z-50 flex items-center gap-1.5 rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-4 py-2.5 text-sm shadow-lg">
        <label for="public-theme-select" class="flex items-center text-[var(--ui-fg)]">
            <span>Tema&nbsp;(</span>
            <select id="public-theme-select" data-theme-selector aria-label="Tema tampilan"
                class="max-w-[9.5rem] appearance-none bg-transparent font-medium outline-none"></select>
            <span>)</span>
        </label>
        <x-ui.icon name="chevron-down" size="xs" />
    </div>
    @vite('resources/js/app.js')
</body>

</html>
