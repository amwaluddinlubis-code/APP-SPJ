<x-layouts.tailwind-app>
    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow">
            <div class="relative overflow-hidden bg-gradient-to-br from-slate-950 via-indigo-950 to-violet-900 px-5 py-7 text-white sm:px-7 lg:py-8">
                <p class="text-xs font-bold uppercase tracking-[.16em] text-sky-200">ADMINISTRATOR</p>
                <h1 class="mt-2 text-2xl font-bold sm:text-3xl">Manajemen User & Role</h1>
                <p class="mt-1 max-w-3xl text-sm text-indigo-100">Kelola akun pengguna, role, dan sekolah asal user. Role menentukan menu dan aksi yang dapat dibuka user.</p>
            </div>
        </section>

        @if($errors->any())
            <section class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <p class="font-bold">Periksa kembali isian berikut:</p>
                <ul class="mt-2 list-disc pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="rounded-2xl border border-indigo-100 bg-white p-5 shadow">
            <h2 class="font-bold text-slate-800">Tambah User</h2>
            <p class="mt-1 text-sm text-slate-500">Buat akun untuk operator, viewer/pemeriksa, atau administrator tambahan.</p>
            <form method="POST" action="{{ route('users.store') }}" class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                @csrf
                <div class="xl:col-span-2"><label class="text-xs font-bold text-slate-600">Nama</label><input name="name" value="{{ old('name') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required></div>
                <div class="xl:col-span-2"><label class="text-xs font-bold text-slate-600">Email</label><input type="email" name="email" value="{{ old('email') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required></div>
                <div><label class="text-xs font-bold text-slate-600">Role</label><select name="role" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" required>@foreach($roles as $value => $label)<option value="{{ $value }}" @selected(old('role', \App\Models\User::ROLE_OPERATOR) === $value)>{{ $label }}</option>@endforeach</select></div>
                <div><label class="text-xs font-bold text-slate-600">Sekolah</label><select name="school_id" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"><option value="">Lintas sekolah / belum ditetapkan</option>@foreach($schools as $school)<option value="{{ $school->id }}" @selected((string) old('school_id') === (string) $school->id)>{{ $school->name }}</option>@endforeach</select></div>
                <div class="xl:col-span-2"><label class="text-xs font-bold text-slate-600">Password</label><input type="password" name="password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required></div>
                <div class="xl:col-span-2"><label class="text-xs font-bold text-slate-600">Ulangi Password</label><input type="password" name="password_confirmation" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required></div>
                <div class="flex items-end xl:col-span-2"><button class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-bold text-white shadow hover:bg-indigo-700">Buat User</button></div>
            </form>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="font-bold text-slate-800">Daftar User</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $users->count() }} akun terdaftar.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">User</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Role & Sekolah</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Reset Password</th>
                            <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($users as $user)
                            <tr class="align-top hover:bg-indigo-50/40">
                                <td class="px-5 py-4">
                                    <form id="user-form-{{ $user->id }}" method="POST" action="{{ route('users.update', $user->id) }}" class="grid gap-2">
                                        @csrf
                                        @method('PUT')
                                        <div><label class="text-xs font-bold text-slate-600">Nama</label><input name="name" value="{{ old("users.{$user->id}.name", $user->name) }}" class="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm" required></div>
                                        <div><label class="text-xs font-bold text-slate-600">Email</label><input type="email" name="email" value="{{ old("users.{$user->id}.email", $user->email) }}" class="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm" required></div>
                                    </form>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="grid gap-2">
                                        <div><label class="text-xs font-bold text-slate-600">Role</label><select form="user-form-{{ $user->id }}" name="role" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-sm">@foreach($roles as $value => $label)<option value="{{ $value }}" @selected($user->role === $value)>{{ $label }}</option>@endforeach</select></div>
                                        <div><label class="text-xs font-bold text-slate-600">Sekolah</label><select form="user-form-{{ $user->id }}" name="school_id" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-sm"><option value="">Lintas sekolah / belum ditetapkan</option>@foreach($schools as $school)<option value="{{ $school->id }}" @selected((int) $user->school_id === (int) $school->id)>{{ $school->name }}</option>@endforeach</select></div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="grid gap-2">
                                        <div><label class="text-xs font-bold text-slate-600">Password Baru</label><input form="user-form-{{ $user->id }}" type="password" name="password" class="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm" placeholder="Kosongkan jika tidak diganti"></div>
                                        <div><label class="text-xs font-bold text-slate-600">Ulangi Password</label><input form="user-form-{{ $user->id }}" type="password" name="password_confirmation" class="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm"></div>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex flex-col items-end gap-2">
                                        <button form="user-form-{{ $user->id }}" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-bold text-white shadow hover:bg-indigo-700">Simpan</button>
                                        @if($user->is(auth()->user()))
                                            <span class="text-xs font-semibold text-slate-400">Akun aktif tidak bisa dihapus</span>
                                        @else
                                            <form method="POST" action="{{ route('users.destroy', $user->id) }}" data-confirm="Hapus user {{ $user->name }}?">
                                                @csrf
                                                @method('DELETE')
                                                <button class="rounded-lg border border-rose-200 bg-white px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-50">Hapus</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
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
