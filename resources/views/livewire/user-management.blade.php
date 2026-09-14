<div class="space-y-6">
    <x-page-header title="Pengguna & Hak Akses" subtitle="Kelola akun, hak akses, dan sekolah pengguna secara langsung." kicker="Pengaturan pengguna">
        <div class="grid divide-y divide-[var(--ui-line)] sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-4">
            <x-stat-item label="Total pengguna" :value="$users->count()" hint="Sesuai pencarian" />
            <x-stat-item label="Administrator" :value="$users->where('role', \App\Models\User::ROLE_ADMIN)->count()" hint="Akses penuh" />
            <x-stat-item label="Operator" :value="$users->where('role', \App\Models\User::ROLE_OPERATOR)->count()" hint="Mengelola SPJ" />
            <x-stat-item label="Pemeriksa" :value="$users->where('role', \App\Models\User::ROLE_VIEWER)->count()" hint="Hanya melihat" />
        </div>
    </x-page-header>

    @if (session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>@endif
    @error('form')<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">{{ $message }}</div>@enderror

    <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_300px]">
        <x-ui.form-section title="Tambah pengguna" description="Buat akun baru tanpa meninggalkan halaman.">
            <form wire:submit="createUser" class="space-y-5">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.field label="Nama lengkap" :error="$errors->first('name')" required><x-ui.input wire:model="name" required /></x-ui.field>
                    <x-ui.field label="Email" :error="$errors->first('email')" required><x-ui.input type="email" wire:model="email" required /></x-ui.field>
                    <x-ui.field label="Hak akses" required><x-ui.select wire:model="role" required>@foreach($roles as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</x-ui.select></x-ui.field>
                    <x-ui.field label="Sekolah"><x-ui.select wire:model="schoolId"><option value="">Lintas sekolah / belum ditetapkan</option>@foreach($schools as $school)<option value="{{ $school->id }}">{{ $school->name }}</option>@endforeach</x-ui.select></x-ui.field>
                    <x-ui.field label="Kata sandi" :error="$errors->first('password')" required><x-ui.input type="password" wire:model="password" required /></x-ui.field>
                    <x-ui.field label="Ulangi kata sandi" required><x-ui.input type="password" wire:model="passwordConfirmation" required /></x-ui.field>
                </div>
                <div class="ui-form-actions"><x-ui.button type="submit">Tambah pengguna</x-ui.button></div>
            </form>
        </x-ui.form-section>
        <aside class="rounded-2xl border border-indigo-100 bg-indigo-50/70 p-5"><p class="text-xs font-bold uppercase tracking-wide text-indigo-500">TALL aktif</p><p class="mt-3 text-sm leading-6 text-indigo-950">Form, validasi, pencarian, dan aksi akun diproses reaktif oleh Livewire.</p></aside>
    </section>

    <section class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
        <div class="flex flex-col gap-3 border-b border-[var(--ui-line)] px-5 py-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Akun terdaftar</p><h2 class="mt-1 text-lg font-bold text-slate-900">Daftar pengguna</h2></div><x-ui.input wire:model.live.debounce.300ms="search" placeholder="Cari nama atau email..." /></div>
        <div class="divide-y divide-[var(--ui-line)]">
            @forelse($users as $user)
                <article class="p-5" wire:key="user-{{ $user->id }}" x-data="{ name: @js($user->name), email: @js($user->email), role: @js($user->role), schoolId: @js($user->school_id), password: '', passwordConfirmation: '' }">
                    <div class="grid gap-5 xl:grid-cols-[220px_minmax(0,1fr)_auto] xl:items-start"><div><p class="font-bold text-slate-900">{{ $user->name }}</p><p class="mt-1 break-all text-xs text-slate-500">{{ $user->email }}</p>@if($user->is(auth()->user()))<span class="mt-2 inline-flex rounded-full bg-emerald-50 px-2 py-1 text-[11px] font-bold text-emerald-700">Akun Anda</span>@endif</div>
                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"><x-ui.field label="Nama"><x-ui.input x-model="name" /></x-ui.field><x-ui.field label="Email"><x-ui.input type="email" x-model="email" /></x-ui.field><x-ui.field label="Hak akses"><x-ui.select x-model="role">@foreach($roles as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</x-ui.select></x-ui.field><x-ui.field label="Sekolah"><x-ui.select x-model="schoolId"><option value="">Lintas sekolah / belum ditetapkan</option>@foreach($schools as $school)<option value="{{ $school->id }}">{{ $school->name }}</option>@endforeach</x-ui.select></x-ui.field><x-ui.field label="Kata sandi baru"><x-ui.input type="password" x-model="password" /></x-ui.field><x-ui.field label="Ulangi kata sandi"><x-ui.input type="password" x-model="passwordConfirmation" /></x-ui.field></div>
                        <div class="flex gap-2 xl:flex-col"><x-ui.button type="button" x-on:click="$wire.updateUser({{ $user->id }}, name, email, role, schoolId ? Number(schoolId) : null, password, passwordConfirmation)">Simpan</x-ui.button>@if(!$user->is(auth()->user()))<x-ui.button type="button" variant="danger" x-on:click="if (confirm('Hapus pengguna {{ addslashes($user->name) }}?')) $wire.deleteUser({{ $user->id }})">Hapus</x-ui.button>@endif</div>
                    </div>
                </article>
            @empty<div class="px-5 py-12 text-center text-sm text-slate-500">Belum ada pengguna yang cocok.</div>@endforelse
        </div>
    </section>
</div>
