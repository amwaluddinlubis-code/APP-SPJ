<x-layouts.public-tailwind title="Setup Awal · SPJ BOSP">
    <div class="flex flex-col gap-2">
        <p class="text-xs font-bold uppercase tracking-[.16em] text-[var(--theme-content-accent)]">Pengaturan pertama</p>
        <h1 class="text-2xl font-bold text-[var(--text-comfort-strong)]">Setup Aplikasi Sekolah</h1>
        <p class="max-w-2xl text-sm leading-6 text-[var(--ui-fg-muted)]">Siapkan profil sekolah dan akun administrator
            untuk mulai mengelola data BOSP dan SPJ.</p>
    </div>

    <form method="POST" action="{{ route('setup.store') }}" class="mt-6 space-y-5">
        @csrf

        <section class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-4 sm:p-5">
            <div class="flex items-start gap-3">
                <span
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[var(--theme-accent-soft)] text-sm font-bold text-[var(--theme-content-accent)]">1</span>
                <div>
                    <h2 class="font-bold text-[var(--ui-fg-strong)]">Profil sekolah</h2>
                    <p class="mt-1 text-xs leading-5 text-[var(--ui-fg-muted)]">Informasi ini menjadi identitas utama
                        workspace sekolah.</p>
                </div>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Kode Sekolah" for="school-code" :required="true" :error="$errors->first('school_code')">
                    <x-ui.input id="school-code" name="school_code" value="{{ old('school_code') }}" maxlength="40"
                        pattern="[A-Za-z0-9._-]+" autocomplete="organization" required />
                </x-ui.field>
                <x-ui.field label="NPSN" for="npsn" :required="true" :error="$errors->first('npsn')">
                    <x-ui.input id="npsn" name="npsn" value="{{ old('npsn') }}" maxlength="16"
                        inputmode="numeric" autocomplete="off" required />
                </x-ui.field>
                <x-ui.field label="Nama Sekolah" for="school-name" :required="true" :error="$errors->first('school_name')">
                    <x-ui.input id="school-name" name="school_name" value="{{ old('school_name') }}"
                        autocomplete="organization" required />
                </x-ui.field>
                <x-ui.field label="Alamat Sekolah" for="school-address" :error="$errors->first('address')">
                    <x-ui.input id="school-address" name="address" value="{{ old('address') }}"
                        autocomplete="street-address" />
                </x-ui.field>
                <x-ui.field label="Desa/Kelurahan" for="school-desa" :error="$errors->first('desa')">
                    <x-ui.input id="school-desa" name="desa" value="{{ old('desa') }}" />
                </x-ui.field>
                <x-ui.field label="Kecamatan" for="school-district" :error="$errors->first('district')">
                    <x-ui.input id="school-district" name="district" value="{{ old('district') }}" />
                </x-ui.field>
                <x-ui.field label="Kabupaten/Kota" for="school-regency" :error="$errors->first('regency')">
                    <x-ui.input id="school-regency" name="regency" value="{{ old('regency') }}" />
                </x-ui.field>
                <x-ui.field label="Provinsi" for="school-province" :error="$errors->first('province')">
                    <x-ui.input id="school-province" name="province" value="{{ old('province') }}" />
                </x-ui.field>
                <x-ui.field label="Tahun Anggaran Awal" for="setup-year" :required="true" :error="$errors->first('year')">
                    <x-ui.input id="setup-year" type="number" name="year" value="{{ old('year', date('Y')) }}"
                        min="2020" max="2100" inputmode="numeric" required />
                </x-ui.field>
            </div>
        </section>

        <section class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-4 sm:p-5">
            <div class="flex items-start gap-3">
                <span
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[var(--theme-accent-soft)] text-sm font-bold text-[var(--theme-content-accent)]">2</span>
                <div>
                    <h2 class="font-bold text-[var(--ui-fg-strong)]">Akun administrator</h2>
                    <p class="mt-1 text-xs leading-5 text-[var(--ui-fg-muted)]">Gunakan akun ini untuk masuk dan
                        mengatur aplikasi.</p>
                </div>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Nama Administrator" for="admin-name" :required="true" :error="$errors->first('admin_name')">
                    <x-ui.input id="admin-name" name="admin_name" value="{{ old('admin_name') }}" autocomplete="name"
                        required />
                </x-ui.field>
                <x-ui.field label="Email" for="admin-email" :required="true" :error="$errors->first('email')">
                    <x-ui.input id="admin-email" type="email" name="email" value="{{ old('email') }}"
                        autocomplete="email" required />
                </x-ui.field>
                <x-ui.field label="Kata Sandi" for="admin-password" hint="Minimal 12 karakter." :required="true"
                    :error="$errors->first('password')">
                    <x-ui.input id="admin-password" type="password" name="password" autocomplete="new-password"
                        required />
                </x-ui.field>
                <x-ui.field label="Ulangi Kata Sandi" for="password-confirmation" :required="true" :error="$errors->first('password_confirmation')">
                    <x-ui.input id="password-confirmation" type="password" name="password_confirmation"
                        autocomplete="new-password" required />
                </x-ui.field>
            </div>
        </section>

        <div
            class="flex flex-col gap-3 border-t border-[var(--ui-line)] pt-5 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs leading-5 text-[var(--ui-fg-muted)]">Setelah disimpan, Anda akan diarahkan untuk memilih
                tahun aktif.</p>
            <x-ui.button type="submit" class="w-full justify-center sm:w-auto">Simpan dan Masuk</x-ui.button>
        </div>
    </form>
</x-layouts.public-tailwind>
