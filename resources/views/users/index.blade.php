<x-layouts.tailwind-app>
    <div class="space-y-6">
        <x-page-header
            title="Manajemen User & Role"
            subtitle="Kelola akun pengguna, role, dan sekolah asal user. Role menentukan menu dan aksi yang dapat dibuka user."
            kicker="Administrator"
        >
            <div class="grid divide-y divide-slate-100 sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-4">
                <x-stat-item label="Total User" :value="number_format($users->count(), 0, ',', '.')" hint="Akun terdaftar" />
                <x-stat-item label="Administrator" :value="number_format($users->where('role', \App\Models\User::ROLE_ADMIN)->count(), 0, ',', '.')" hint="Akses administrasi" value-class="text-indigo-700" />
                <x-stat-item label="Operator" :value="number_format($users->where('role', \App\Models\User::ROLE_OPERATOR)->count(), 0, ',', '.')" hint="Pengelola operasional" value-class="text-emerald-700" />
                <x-stat-item label="Viewer" :value="number_format($users->where('role', \App\Models\User::ROLE_VIEWER)->count(), 0, ',', '.')" hint="Akses baca" value-class="text-slate-700" />
            </div>
        </x-page-header>

        @if($errors->any())
            <section class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <p class="font-bold">Periksa kembali isian berikut:</p>
                <ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </section>
        @endif

        <x-ui.form-section title="Tambah User" description="Buat akun operator, viewer/pemeriksa, atau administrator tambahan.">
            <form method="POST" action="{{ route('users.store') }}" class="space-y-5">
                @csrf
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <x-ui.field label="Nama" for="new-user-name" :error="$errors->first('name')" required>
                        <x-ui.input id="new-user-name" name="name" :value="old('name')" autocomplete="name" required />
                    </x-ui.field>
                    <x-ui.field label="Email" for="new-user-email" :error="$errors->first('email')" required>
                        <x-ui.input id="new-user-email" type="email" name="email" :value="old('email')" autocomplete="email" required />
                    </x-ui.field>
                    <x-ui.field label="Role" for="new-user-role" hint="Hak akses utama pengguna." required>
                        <x-ui.select id="new-user-role" name="role" required>@foreach($roles as $value => $label)<option value="{{ $value }}" @selected(old('role', \App\Models\User::ROLE_OPERATOR) === $value)>{{ $label }}</option>@endforeach</x-ui.select>
                    </x-ui.field>
                    <x-ui.field label="Sekolah" for="new-user-school" hint="Boleh kosong untuk akun lintas sekolah.">
                        <x-ui.select id="new-user-school" name="school_id"><option value="">Lintas sekolah / belum ditetapkan</option>@foreach($schools as $school)<option value="{{ $school->id }}" @selected((string) old('school_id') === (string) $school->id)>{{ $school->name }}</option>@endforeach</x-ui.select>
                    </x-ui.field>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.field label="Password" for="new-user-password" :error="$errors->first('password')" required>
                        <x-ui.input id="new-user-password" type="password" name="password" autocomplete="new-password" required />
                    </x-ui.field>
                    <x-ui.field label="Ulangi Password" for="new-user-password-confirmation" hint="Masukkan password yang sama sekali lagi." required>
                        <x-ui.input id="new-user-password-confirmation" type="password" name="password_confirmation" autocomplete="new-password" required />
                    </x-ui.field>
                </div>
                <div class="ui-form-actions"><x-ui.button type="submit">Buat User</x-ui.button></div>
            </form>
        </x-ui.form-section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <h2 class="font-bold text-slate-800">Daftar User</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $users->count() }} akun terdaftar. Ubah data langsung pada baris user lalu simpan.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-[980px] w-full divide-y divide-slate-200 text-sm" data-pagination="none">
                    <thead class="bg-slate-50"><tr><th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">User</th><th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Role & Sekolah</th><th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Password</th><th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($users as $user)
                            <tr class="align-top hover:bg-slate-50/70">
                                <td class="px-5 py-4">
                                    <form id="user-form-{{ $user->id }}" method="POST" action="{{ route('users.update', $user->id) }}" class="grid gap-3">@csrf @method('PUT')
                                        <x-ui.field label="Nama"><x-ui.input name="name" :value="old("users.{$user->id}.name", $user->name)" required /></x-ui.field>
                                        <x-ui.field label="Email"><x-ui.input type="email" name="email" :value="old("users.{$user->id}.email", $user->email)" required /></x-ui.field>
                                    </form>
                                </td>
                                <td class="px-4 py-4"><div class="grid gap-3">
                                    <x-ui.field label="Role"><x-ui.select form="user-form-{{ $user->id }}" name="role">@foreach($roles as $value => $label)<option value="{{ $value }}" @selected($user->role === $value)>{{ $label }}</option>@endforeach</x-ui.select></x-ui.field>
                                    <x-ui.field label="Sekolah"><x-ui.select form="user-form-{{ $user->id }}" name="school_id"><option value="">Lintas sekolah / belum ditetapkan</option>@foreach($schools as $school)<option value="{{ $school->id }}" @selected((int) $user->school_id === (int) $school->id)>{{ $school->name }}</option>@endforeach</x-ui.select></x-ui.field>
                                </div></td>
                                <td class="px-4 py-4"><div class="grid gap-3">
                                    <x-ui.field label="Password baru" hint="Kosongkan jika tidak diganti."><x-ui.input form="user-form-{{ $user->id }}" type="password" name="password" autocomplete="new-password" /></x-ui.field>
                                    <x-ui.field label="Ulangi password"><x-ui.input form="user-form-{{ $user->id }}" type="password" name="password_confirmation" autocomplete="new-password" /></x-ui.field>
                                </div></td>
                                <td class="px-5 py-4 text-right"><div class="flex flex-col items-end gap-2">
                                    <x-ui.button type="submit" form="user-form-{{ $user->id }}">Simpan</x-ui.button>
                                    @if($user->is(auth()->user()))
                                        <span class="text-xs font-semibold text-slate-400">Akun aktif tidak bisa dihapus</span>
                                    @else
                                        <form method="POST" action="{{ route('users.destroy', $user->id) }}" data-confirm="Hapus user {{ $user->name }}?">@csrf @method('DELETE')<x-ui.button type="submit" variant="danger">Hapus</x-ui.button></form>
                                    @endif
                                </div></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-12 text-center text-slate-500">Belum ada user.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <h2 class="font-bold">Catatan aktivasi di laptop lain</h2>
            <p class="mt-1">Akun administrator saat ini masih berada di database utama lokal. Tanpa menyalin database, laptop lain tidak otomatis memiliki akun yang sama. Solusi yang disarankan adalah fitur aktivasi berbasis kode undangan atau server pusat lisensi/akun.</p>
        </section>
    </div>
</x-layouts.tailwind-app>
