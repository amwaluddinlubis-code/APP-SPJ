<x-layouts.tailwind-app>
    <div class="space-y-6">
        <x-page-header
            title="Pengguna & Hak Akses"
            subtitle="Kelola akun yang dapat masuk ke aplikasi, tentukan hak akses, dan hubungkan pengguna ke sekolah."
            kicker="Pengaturan pengguna"
        >
            <div class="grid divide-y divide-slate-100 sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-4">
                <x-stat-item label="Total pengguna" :value="number_format($users->count(), 0, ',', '.')" hint="Akun terdaftar" />
                <x-stat-item label="Administrator" :value="number_format($users->where('role', \App\Models\User::ROLE_ADMIN)->count(), 0, ',', '.')" hint="Akses penuh" value-class="text-indigo-700" />
                <x-stat-item label="Operator" :value="number_format($users->where('role', \App\Models\User::ROLE_OPERATOR)->count(), 0, ',', '.')" hint="Mengelola SPJ" value-class="text-emerald-700" />
                <x-stat-item label="Pemeriksa" :value="number_format($users->where('role', \App\Models\User::ROLE_VIEWER)->count(), 0, ',', '.')" hint="Hanya melihat data" value-class="text-slate-700" />
            </div>
        </x-page-header>

        @if($errors->any())
            <section class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-800 shadow-sm">
                <p class="font-bold">Ada data yang perlu diperbaiki</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </section>
        @endif

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_300px]">
            <x-ui.form-section title="Tambah pengguna" description="Buat akun baru untuk administrator, operator, atau pemeriksa.">
                <form method="POST" action="{{ route('users.store') }}" class="space-y-5">
                    @csrf
                    <div class="grid gap-4 md:grid-cols-2">
                        <x-ui.field label="Nama lengkap" for="new-user-name" :error="$errors->first('name')" required>
                            <x-ui.input id="new-user-name" name="name" :value="old('name')" autocomplete="name" required />
                        </x-ui.field>
                        <x-ui.field label="Email" for="new-user-email" :error="$errors->first('email')" required>
                            <x-ui.input id="new-user-email" type="email" name="email" :value="old('email')" autocomplete="email" required />
                        </x-ui.field>
                        <x-ui.field label="Hak akses" for="new-user-role" hint="Tentukan apa yang boleh dilakukan pengguna." required>
                            <x-ui.select id="new-user-role" name="role" required>@foreach($roles as $value => $label)<option value="{{ $value }}" @selected(old('role', \App\Models\User::ROLE_OPERATOR) === $value)>{{ $label }}</option>@endforeach</x-ui.select>
                        </x-ui.field>
                        <x-ui.field label="Sekolah" for="new-user-school" hint="Kosongkan hanya untuk akun yang boleh bekerja lintas sekolah.">
                            <x-ui.select id="new-user-school" name="school_id"><option value="">Lintas sekolah / belum ditetapkan</option>@foreach($schools as $school)<option value="{{ $school->id }}" @selected((string) old('school_id') === (string) $school->id)>{{ $school->name }}</option>@endforeach</x-ui.select>
                        </x-ui.field>
                        <x-ui.field label="Kata sandi" for="new-user-password" :error="$errors->first('password')" required>
                            <x-ui.input id="new-user-password" type="password" name="password" autocomplete="new-password" required />
                        </x-ui.field>
                        <x-ui.field label="Ulangi kata sandi" for="new-user-password-confirmation" hint="Masukkan kata sandi yang sama." required>
                            <x-ui.input id="new-user-password-confirmation" type="password" name="password_confirmation" autocomplete="new-password" required />
                        </x-ui.field>
                    </div>
                    <div class="ui-form-actions"><x-ui.button type="submit">Tambah pengguna</x-ui.button></div>
                </form>
            </x-ui.form-section>

            <aside class="space-y-3">
                <div class="rounded-2xl border border-indigo-100 bg-indigo-50/70 p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-indigo-500">Hak akses</p>
                    <div class="mt-4 space-y-4 text-sm">
                        <div><p class="font-bold text-slate-900">Administrator</p><p class="mt-1 text-slate-600">Mengelola aplikasi, pengguna, pengaturan, dan seluruh proses SPJ.</p></div>
                        <div><p class="font-bold text-slate-900">Operator</p><p class="mt-1 text-slate-600">Mengelola transaksi dan menyiapkan dokumen SPJ.</p></div>
                        <div><p class="font-bold text-slate-900">Pemeriksa</p><p class="mt-1 text-slate-600">Melihat data dan hasil pemeriksaan tanpa mengubah transaksi.</p></div>
                    </div>
                </div>
            </aside>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-end sm:justify-between sm:px-6">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Akun terdaftar</p>
                    <h2 class="mt-1 text-lg font-bold text-slate-900">Daftar pengguna</h2>
                    <p class="mt-1 text-sm text-slate-500">Ubah hanya bagian yang diperlukan, lalu pilih Simpan.</p>
                </div>
                <span class="inline-flex w-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $users->count() }} pengguna</span>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse($users as $user)
                    <article class="p-5 transition hover:bg-slate-50/60 sm:p-6">
                        <form id="user-form-{{ $user->id }}" method="POST" action="{{ route('users.update', $user->id) }}">@csrf @method('PUT')</form>
                        <div class="grid gap-5 xl:grid-cols-[220px_minmax(0,1fr)_auto] xl:items-start">
                            <div class="flex gap-3">
                                <div class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-indigo-50 text-sm font-black uppercase text-indigo-700">{{ mb_substr($user->name, 0, 1) }}</div>
                                <div class="min-w-0">
                                    <p class="truncate font-bold text-slate-900">{{ $user->name }}</p>
                                    <p class="mt-0.5 break-all text-xs text-slate-500">{{ $user->email }}</p>
                                    @if($user->is(auth()->user()))<span class="mt-2 inline-flex rounded-full bg-emerald-50 px-2 py-1 text-[11px] font-bold text-emerald-700">Akun Anda</span>@endif
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                <x-ui.field label="Nama"><x-ui.input form="user-form-{{ $user->id }}" name="name" :value="$user->name" required /></x-ui.field>
                                <x-ui.field label="Email"><x-ui.input form="user-form-{{ $user->id }}" type="email" name="email" :value="$user->email" required /></x-ui.field>
                                <x-ui.field label="Hak akses"><x-ui.select form="user-form-{{ $user->id }}" name="role">@foreach($roles as $value => $label)<option value="{{ $value }}" @selected($user->role === $value)>{{ $label }}</option>@endforeach</x-ui.select></x-ui.field>
                                <x-ui.field label="Sekolah"><x-ui.select form="user-form-{{ $user->id }}" name="school_id"><option value="">Lintas sekolah / belum ditetapkan</option>@foreach($schools as $school)<option value="{{ $school->id }}" @selected((int) $user->school_id === (int) $school->id)>{{ $school->name }}</option>@endforeach</x-ui.select></x-ui.field>
                                <x-ui.field label="Kata sandi baru" hint="Kosongkan jika tidak diganti."><x-ui.input form="user-form-{{ $user->id }}" type="password" name="password" autocomplete="new-password" /></x-ui.field>
                                <x-ui.field label="Ulangi kata sandi"><x-ui.input form="user-form-{{ $user->id }}" type="password" name="password_confirmation" autocomplete="new-password" /></x-ui.field>
                            </div>

                            <div class="flex gap-2 xl:flex-col xl:items-stretch">
                                <x-ui.button type="submit" form="user-form-{{ $user->id }}">Simpan</x-ui.button>
                                @if(!$user->is(auth()->user()))
                                    <form method="POST" action="{{ route('users.destroy', $user->id) }}" data-confirm="Hapus pengguna {{ $user->name }}?">@csrf @method('DELETE')<x-ui.button type="submit" variant="danger">Hapus</x-ui.button></form>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="px-5 py-12 text-center text-sm text-slate-500">Belum ada pengguna.</div>
                @endforelse
            </div>
        </section>

        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900 shadow-sm">
            <h2 class="font-bold">Penggunaan di laptop lain</h2>
            <p class="mt-1 leading-6">Akun saat ini tersimpan pada database utama lokal. Jika database utama tidak ikut dipindahkan, akun pada laptop ini tidak otomatis tersedia di laptop lain.</p>
        </section>
    </div>
</x-layouts.tailwind-app>
