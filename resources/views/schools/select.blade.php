<x-layouts.public-tailwind title="Pilih Sekolah · SPJ BOSP">
  <div class="flex items-start justify-between gap-4">
    <div>
      <p class="text-xs font-bold tracking-[.16em] text-indigo-600">LANGKAH 1</p>
      <h1 class="mt-2 text-2xl font-bold text-slate-900">Pilih Sekolah</h1>
      <p class="mt-1 text-base text-slate-500">Tentukan database sekolah yang akan dipakai. Tahun dan sumber dana dipilih pada langkah berikutnya.</p>
    </div>
    <form method="POST" action="{{ route('logout') }}">
      @csrf
      <button class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700">Keluar</button>
    </form>
  </div>

  <div class="mt-6 space-y-3">
    @forelse($schools as $school)
      <form method="POST" action="{{ route('schools.activate') }}" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
        @csrf
        <input type="hidden" name="school_id" value="{{ $school->id }}">
        <div>
          <p class="font-bold text-slate-800">{{ $school->name }}</p>
          <p class="mt-1 text-xs text-slate-500">NPSN {{ $school->npsn }} · Database {{ $school->databaseRecord?->status ?? 'Belum diprovisikan' }}</p>
        </div>
        <button class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-bold text-white">Pilih Sekolah</button>
      </form>
    @empty
      <div class="rounded-lg bg-slate-50 p-5 text-base text-slate-500">Belum ada sekolah.</div>
    @endforelse
  </div>
</x-layouts.public-tailwind>
