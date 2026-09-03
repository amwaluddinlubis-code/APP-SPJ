<x-layouts.public-tailwind title="Pilih Tahun dan Sumber Dana">
  <div class="flex items-start justify-between gap-4">
    <div>
      <p class="text-xs font-bold tracking-[.16em] text-indigo-600">LANGKAH 2</p>
      <h1 class="mt-2 text-2xl font-bold text-slate-900">Pilih Tahun dan Sumber Dana</h1>
      <p class="mt-1 text-base text-slate-500">{{ $school->name }} · NPSN {{ $school->npsn }}. Data akan dibatasi berdasarkan pilihan ini.</p>
    </div>
    <div class="flex flex-wrap justify-end gap-2">
      <a href="{{ route('schools.select') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700">Ganti Sekolah</a>
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700">Keluar</button>
      </form>
    </div>
  </div>

  @unless($hasFundSourceContext)
    <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">Database sekolah belum siap untuk konteks sumber dana. Buka <a href="{{ route('database-manager.index') }}" class="font-bold underline">Manajemen Database</a> lalu jalankan migrasi pada sekolah aktif.</div>
  @endunless

  <div class="mt-6 space-y-3">
    @forelse($years as $year)
      <form method="POST" action="{{ route('years.activate') }}" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
        @csrf
        <input type="hidden" name="fiscal_year_id" value="{{ $year->id }}">
        <div>
          <p class="font-bold text-slate-800">Tahun {{ $year->year }}</p>
          <p class="mt-1 text-xs text-slate-500">{{ $year->fundSource?->code }} · {{ $year->fundSource?->name ?? $year->fund_source }} · NPSN {{ $school->npsn }}</p>
        </div>
        <button class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-bold text-white" @disabled(!$hasFundSourceContext)>Gunakan</button>
      </form>
    @empty
      <div class="rounded-lg bg-slate-50 p-5 text-base text-slate-500">
        <p class="font-semibold text-slate-700">Belum ada kombinasi tahun dan sumber dana.</p>
        <p class="mt-2 text-sm">Jalankan sinkronisasi ARKAS untuk mengimpor data tahun dan sumber dana dari database ARKAS.</p>
        <form method="POST" action="{{ route('years.synchronize') }}" data-confirm="Sinkronisasi akan mengimpor data tahun dan sumber dana dari ARKAS. Lanjutkan?" class="mt-4">
          @csrf
          <input type="hidden" name="confirm_sync" value="1">
          <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700">Sinkronisasi ARKAS Sekarang</button>
        </form>
      </div>
    @endforelse
  </div>
</x-layouts.public-tailwind>
