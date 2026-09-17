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

<body class="public-layout min-h-full text-[var(--ui-fg)] {{ request()->routeIs('schools.select', 'years.select') ? 'context-selector-body' : '' }}"
    style="background: var(--ui-surface-soft);">
    <x-toast-notifications />
    <main
        class="public-layout-main mx-auto flex min-h-screen items-center p-4 sm:p-8 {{ $narrow || request()->routeIs('schools.select', 'years.select') ? 'max-w-xl' : 'max-w-3xl' }} {{ str_contains((string) $cardClass, 'auth-login-surface') ? 'auth-login-page' : '' }} {{ request()->routeIs('schools.select', 'years.select') ? 'context-selector-page' : '' }}">
        <section
            class="relative w-full rounded-xl border border-[var(--ui-line)] {{ $cardClass ?? (request()->routeIs('schools.select', 'years.select') ? 'context-selector-surface' : 'bg-[var(--ui-surface-base)]') }} p-5 shadow-xl sm:p-7"
            style="box-shadow: var(--profile-floating-shadow, 0 20px 45px rgb(15 23 42 / .12));">
            @if (request()->routeIs('schools.select', 'years.select'))
                <form method="POST" action="{{ route('logout') }}" class="context-logout absolute right-4 top-4 z-10">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" icon="logout" aria-label="Keluar"
                        title="Keluar" class="!min-h-9 !w-9 !justify-center !p-0" />
                </form>
            @endif
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
    <div class="fixed bottom-4 right-4 z-50">
        <button type="button" data-theme-toggle class="public-theme-toggle" aria-label="Gunakan tema gelap"
            title="Gunakan tema gelap">
            <span data-theme-icon="sun" class="hidden" aria-hidden="true">
                <x-ui.icon name="sun" size="sm" />
            </span>
            <span data-theme-icon="moon" aria-hidden="true">
                <x-ui.icon name="moon" size="sm" />
            </span>
            <span class="sr-only">Ganti tema tampilan</span>
        </button>
    </div>
    @vite('resources/js/app.js')
</body>

</html>
