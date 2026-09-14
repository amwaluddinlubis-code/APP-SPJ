<x-layouts.public-tailwind title="Masuk · SPJ BOSP" narrow cardClass="auth-login-surface">
    <div class="auth-login-card mx-auto w-full max-w-md px-1 py-1 sm:px-2"
        style="background: #ffffff !important;">
        <div data-login-logo class="auth-login-brand text-center">
            {{-- Simpan file logo lengkap pada public/images/logo-app-spj.png agar tampil di sini. --}}
            <img src="{{ asset('images/logo-app-spj.png') }}" alt="Logo aplikasi SPJ BOSP"
                class="auth-login-logo mx-auto h-auto w-auto object-contain"
                onerror="this.remove();document.querySelector('[data-login-logo-fallback]').classList.remove('hidden');">
            <div data-login-logo-fallback class="hidden">
                <span class="mx-auto flex h-20 w-20 items-center justify-center rounded-2xl text-xl font-black text-white"
                    style="background: var(--theme-action-bg, var(--theme-accent));">SPJ</span>
                <p class="mt-3 text-2xl font-black tracking-tight text-[var(--login-fg-strong)]">SPJ BOSP</p>
            </div>
        </div>
        <div class="auth-login-heading text-center">
            <h1 class="text-2xl font-bold text-[var(--login-fg-strong)]" style="color: #0f172a !important;">Masuk ke SPJ BOSP</h1>
            <p class="mt-2 text-sm leading-6 text-[var(--login-fg-muted)]" style="color: #475569 !important;">Gunakan akun sekolah yang telah terdaftar.</p>
        </div>

        <form method="POST" action="{{ route('login.store') }}" class="auth-login-form mt-7 space-y-5">
            @csrf
            <x-ui.field label="Email" for="login-email" :required="true" :error="$errors->first('email')">
                <div class="relative">
                    <span
                        class="auth-login-icon pointer-events-none absolute inset-y-0 left-0 flex w-11 items-center justify-center"
                        aria-hidden="true">
                        <x-ui.icon name="mail" size="sm" />
                    </span>
                    <x-ui.input id="login-email" type="email" name="email" value="{{ old('email') }}" required
                        autofocus autocomplete="email" />
                </div>
            </x-ui.field>

            <x-ui.field label="Kata Sandi" for="login-password" :required="true" :error="$errors->first('password')">
                <div class="relative" x-data="{ show: false }">
                    <span
                        class="auth-login-icon pointer-events-none absolute inset-y-0 left-0 flex w-11 items-center justify-center"
                        aria-hidden="true">
                        <x-ui.icon name="lock" size="sm" />
                    </span>
                    <x-ui.input id="login-password" type="password" name="password" required
                        autocomplete="current-password" ::type="show ? 'text' : 'password'" />
                    <button type="button" @click="show = !show"
                        :aria-label="show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                        :title="show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                        class="auth-login-password-toggle absolute inset-y-0 right-0 flex w-12 items-center justify-center transition">
                        <span x-show="!show"><x-ui.icon name="eye-off" size="sm" /></span>
                        <span x-show="show" x-cloak><x-ui.icon name="eye" size="sm" /></span>
                    </button>
                </div>
            </x-ui.field>

            <div class="flex items-center justify-between gap-3">
                <label class="auth-login-remember flex cursor-pointer items-center gap-2 text-sm">
                    <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded">
                    Ingat saya
                </label>
                {{-- Belum ada route reset kata sandi; tautan otomatis aktif bila route password.request tersedia. --}}
                <a href="{{ Route::has('password.request') ? route('password.request') : '#' }}"
                    class="auth-login-forgot text-sm font-medium hover:underline" style="color: #2563eb !important;">Lupa kata sandi?</a>
            </div>

            <x-ui.button type="submit" class="auth-login-submit w-full">Masuk</x-ui.button>
        </form>
    </div>
</x-layouts.public-tailwind>
