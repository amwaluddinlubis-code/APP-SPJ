<x-layouts.tailwind-app>
    <div class="space-y-6">
        <x-page-header
            title="Uji Halaman Sebagai User"
            subtitle="Periksa tampilan dan akses operator tanpa logout lalu login ulang. Mode ini khusus pengujian."
            kicker="Administrator"
        >
            <x-slot:actions>
                @if(session('impersonator_user_id'))
                    <form method="POST" action="{{ route('impersonation.stop') }}">@csrf<button class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-bold text-white shadow hover:bg-amber-600">Kembali sebagai Admin</button></form>
                @endif
            </x-slot:actions>
            <div class="grid divide-y divide-slate-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                <x-stat-item label="Total User" :value="number_format($users->count(), 0, ',', '.')" hint="Akun yang dapat ditinjau" />
                <x-stat-item label="User Non-Admin" :value="number_format($users->reject(fn($user) => $user->isAdministrator())->count(), 0, ',', '.')" hint="Kandidat mode uji" value-class="text-indigo-700" />
                <x-stat-item label="Mode Uji" :value="session('impersonator_user_id') ? 'Aktif' : 'Tidak aktif'" :hint="session('impersonator_user_id') ? 'Sedang impersonate user' : 'Masih sebagai administrator'" :value-class="session('impersonator_user_id') ? 'text-amber-700' : 'text-emerald-700'" />
            </div>
        </x-page-header>

        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-bold">Aturan aman impersonate</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>Hanya administrator asli yang dapat memulai mode uji.</li>
                <li>Akun administrator lain tidak dapat di-impersonate.</li>
                <li>Saat masuk sebagai operator, akses mengikuti hak user tersebut.</li>
                <li>Gunakan tombol “Kembali sebagai Admin” untuk keluar dari mode uji.</li>
            </ul>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold text-slate-800">Daftar User</h2><p class="mt-1 text-sm text-slate-500">Pilih user operator yang ingin diuji.</p></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50"><tr><th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">User</th><th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Role</th><th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Sekolah</th><th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($users as $user)
                            <tr class="hover:bg-indigo-50/40">
                                <td class="px-5 py-4"><p class="font-bold text-slate-800">{{ $user->name }}</p><p class="mt-0.5 text-xs text-slate-500">{{ $user->email }}</p></td>
                                <td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $user->isAdministrator() ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-100 text-slate-700' }}">{{ $user->role }}</span></td>
                                <td class="px-4 py-4"><p class="font-semibold text-slate-700">{{ $user->school?->name ?: 'Belum terhubung sekolah' }}</p>@if($user->school?->npsn)<p class="mt-0.5 text-xs text-slate-500">NPSN {{ $user->school->npsn }}</p>@endif</td>
                                <td class="px-5 py-4 text-right">
                                    @if($user->is(auth()->user()))
                                        <span class="text-xs font-semibold text-slate-400">Akun aktif</span>
                                    @elseif($user->isAdministrator())
                                        <span class="text-xs font-semibold text-slate-400">Admin tidak bisa diuji</span>
                                    @else
                                        <form method="POST" action="{{ route('impersonation.start', $user->id) }}" data-confirm="Masuk sebagai {{ $user->name }} untuk menguji akses dan tampilan user ini?">@csrf<button class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-bold text-white shadow hover:bg-indigo-700">Uji sebagai user ini</button></form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-12 text-center text-slate-500">Belum ada user.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.tailwind-app>
