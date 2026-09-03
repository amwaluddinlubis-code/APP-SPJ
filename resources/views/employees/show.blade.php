<x-layouts.tailwind-app title="Detail Pegawai">
    @php($mask = fn ($value) => $value ? '••••'.substr($value, -4) : '—')
    <div class="space-y-6">
        <x-page-header
            :title="$employee->name"
            :subtitle="$employee->position ?: 'Jabatan belum tercatat'"
            :kicker="$employee->source_type.' · '.($employee->is_active ? 'Aktif' : 'Tidak aktif')"
        >
            <x-slot:actions>
                <a href="{{ route('employees.edit',$employee) }}" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-[var(--theme-700)]">Ubah</a>
                <a href="{{ route('employees.index') }}" class="rounded-lg bg-white/15 px-4 py-2 text-sm font-semibold text-white ring-1 ring-white/20 hover:bg-white/25">Kembali</a>
            </x-slot:actions>
            <div class="grid divide-y divide-slate-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                <x-stat-item label="Sumber Data" :value="$employee->source_type" hint="Asal data pegawai" value-class="text-indigo-700" />
                <x-stat-item label="Status" :value="$employee->is_active ? 'Aktif' : 'Tidak aktif'" hint="Status kepegawaian di aplikasi" :value-class="$employee->is_active ? 'text-emerald-700' : 'text-rose-700'" />
                <x-stat-item label="Honor Tahun Aktif" :value="'Rp '.number_format($honors->sum('net_amount'), 0, ',', '.')" :hint="number_format($honors->count(), 0, ',', '.').' rincian honor'" value-class="text-amber-700" />
            </div>
        </x-page-header>

        <div class="grid gap-5 lg:grid-cols-3">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2"><h2 class="font-bold text-slate-900">Identitas dan kepegawaian</h2><dl class="mt-4 grid gap-4 sm:grid-cols-2">@foreach ([['NIP',$employee->nip],['NUPTK',$employee->nuptk],['NIK',$mask($employee->nik)],['Jenis kelamin',$employee->gender],['Jenis PTK',$employee->staff_type],['Status pegawai',$employee->employment_status],['Jabatan',$employee->position],['NPWP',$mask($employee->npwp)]] as [$label,$value])<div><dt class="text-xs font-semibold uppercase text-slate-500">{{ $label }}</dt><dd class="mt-1 font-medium text-slate-800">{{ $value ?: '—' }}</dd></div>@endforeach</dl></section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="font-bold text-slate-900">Pembayaran</h2><dl class="mt-4 space-y-4"><div><dt class="text-xs font-semibold uppercase text-slate-500">Bank</dt><dd class="mt-1 font-medium">{{ $employee->bank_name ?: '—' }}</dd></div><div><dt class="text-xs font-semibold uppercase text-slate-500">Rekening</dt><dd class="mt-1 font-medium">{{ $mask($employee->bank_account) }}</dd></div><div class="rounded-xl bg-slate-50 p-3 text-xs text-slate-500">Sumber data: {{ $employee->source_type }}. Perubahan manual tetap dapat dipadankan pada sinkronisasi berikutnya.</div></dl></section>
        </div>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-200 p-5"><h2 class="font-bold text-slate-900">Riwayat honor tahun anggaran aktif</h2><p class="mt-1 text-sm text-slate-500">{{ $honors->count() }} rincian · bruto Rp {{ number_format($honors->sum('gross_amount'), 0, ',', '.') }} · pajak Rp {{ number_format($honors->sum('tax_amount'), 0, ',', '.') }} · diterima Rp {{ number_format($honors->sum('net_amount'), 0, ',', '.') }}</p></div><div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-100 text-left text-xs uppercase text-slate-600"><tr><th class="px-4 py-3">Tanggal / Bukti</th><th class="px-4 py-3">Jabatan</th><th class="px-4 py-3 text-right">Bruto</th><th class="px-4 py-3 text-right">PPh 21</th><th class="px-4 py-3 text-right">Diterima</th></tr></thead><tbody class="divide-y divide-slate-200">@forelse($honors as $honor) @php($trx=$honor->item?->transaction)<tr class="odd:bg-white even:bg-slate-50"><td class="px-4 py-3"><a class="font-semibold text-[var(--theme-700)]" href="{{ $trx ? route('transactions.show',$trx->id) : '#' }}">{{ $trx?->no_bukti ?: '—' }}</a><div class="text-xs text-slate-500">{{ $trx?->transaction_date?->translatedFormat('d F Y') ?: '—' }}</div></td><td class="px-4 py-3">{{ $honor->position ?: '—' }}</td><td class="px-4 py-3 text-right">Rp {{ number_format($honor->gross_amount,0,',','.') }}</td><td class="px-4 py-3 text-right">Rp {{ number_format($honor->tax_amount,0,',','.') }}</td><td class="px-4 py-3 text-right font-semibold">Rp {{ number_format($honor->net_amount,0,',','.') }}</td></tr>@empty<tr><td colspan="5" class="px-6 py-12 text-center text-slate-500">Belum digunakan pada honorarium tahun aktif.</td></tr>@endforelse</tbody></table></div></section>
        @if($employee->payload)<details class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><summary class="cursor-pointer font-bold">Semua field asli sumber ({{ count($employee->payload) }})</summary><dl class="mt-4 grid gap-3 md:grid-cols-2">@foreach($employee->payload as $key=>$value)<div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs font-bold uppercase text-slate-500">{{ str_replace('_',' ',$key) }}</dt><dd class="mt-1 break-words text-sm">{{ is_scalar($value)?($value?:'—'):json_encode($value,JSON_UNESCAPED_UNICODE) }}</dd></div>@endforeach</dl></details>@endif
    </div>
</x-layouts.tailwind-app>
