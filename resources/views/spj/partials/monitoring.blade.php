<div x-show="tab === 'monitoring'" x-transition>
    <div class="border-b border-amber-100 bg-amber-50/40 px-5 py-4 sm:px-6">
        <div>
            <h2 class="font-bold text-amber-900">Monitoring Dokumen Belum Lengkap</h2>
            <p class="mt-1 text-base text-amber-800">Transaksi ber-rincian tapi paket belum siap atau belum bernomor · <span class="font-bold">{{ $pendingPaginator?->total() ?? 0 }} transaksi</span></p>
        </div>

        @if(auth()->user()?->isAdministrator())
            <form method="POST" action="{{ route('spj.quarter-numbering') }}" class="mt-4 flex flex-wrap items-end gap-3 rounded-lg border border-indigo-200 bg-[var(--ui-surface-base)] p-3" data-confirm="Rekonsiliasi nomor triwulan ini? Transaksi yang sudah memiliki nomor aktif akan dilewati dan slot nomor yang dibatalkan dapat dipakai dokumen berikutnya dalam domain serta periode yang sama.">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-600">TRIWULAN SIAP DINOMORI</label>
                    <select name="quarter" class="mt-1 rounded-md border-[var(--ui-line-strong)] text-base">
                        @foreach(range(1,4) as $quarter)
                            <option value="{{ $quarter }}">Triwulan {{ $quarter }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="rounded-md bg-indigo-600 px-4 py-2 text-base font-bold text-white hover:bg-indigo-700">Tetapkan nomor triwulan</button>
                <p class="basis-full text-xs text-slate-500">Nomor aktif dipertahankan. Slot nomor batal dipakai kembali menurut urutan terkecil oleh dokumen berikutnya dalam jenis dan periode penomoran yang sama.</p>
                <p class="basis-full text-xs text-slate-500">Setiap jenis dokumen diurutkan menurut tanggal peristiwanya. Nomor yang sudah terbit akan dilewati.</p>
            </form>

            <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                @foreach(range(1,4) as $quarter)
                    @php($period = ($periodClosures ?? collect())->get($quarter))
                    <div class="rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-3">
                        <div class="flex items-center justify-between">
                            <b>Triwulan {{ $quarter }}</b>
                            <span class="rounded-full bg-[var(--ui-surface-muted)] px-2 py-1 text-xs font-bold">{{ $period?->status ?? 'OPEN' }}</span>
                        </div>
                        @if($period?->status === 'NUMBERED')
                            <form method="POST" action="{{ route('spj.quarter-close') }}" class="mt-2">
                                @csrf
                                <input type="hidden" name="quarter" value="{{ $quarter }}">
                                <button class="w-full rounded-md bg-slate-800 px-3 py-1.5 text-xs font-bold text-white">Tutup triwulan</button>
                            </form>
                        @endif
                        @if($period?->status === 'CLOSED')
                            <form method="POST" action="{{ route('spj.quarter-reopen', $period->id) }}" class="mt-2 space-y-2">
                                @csrf
                                <input name="reason" required placeholder="Alasan pembukaan" class="w-full rounded-md border-[var(--ui-line-strong)] text-xs">
                                <button class="w-full rounded-md bg-amber-600 px-3 py-1.5 text-xs font-bold text-white">Buka kembali</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="overflow-x-auto p-5">
        <table class="min-w-full divide-y divide-amber-100 text-base">
            <thead class="bg-amber-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold text-amber-800">BUKTI</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-amber-800">URAIAN</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-amber-800">STATUS</th>
                    <th class="px-4 py-3 text-right text-xs font-bold text-amber-800">AKSI</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-amber-100">
                @forelse($pendingPaginator ?? [] as $transaction)
                    @php($wasCancelled = $transaction->spjPackage?->documents?->contains('status', 'CANCELLED') ?? false)
                    <tr class="transition {{ $wasCancelled ? 'bg-rose-50/60 hover:bg-rose-50' : 'hover:bg-amber-50/60' }}">
                        <td class="px-4 py-3 font-mono font-bold {{ $wasCancelled ? 'text-rose-800' : 'text-amber-900' }}">{{ $transaction->no_bukti }}</td>
                        <td class="max-w-sm truncate px-4 py-3">{{ $transaction->description }}</td>
                        <td class="px-4 py-3"><span class="rounded-full border px-2 py-0.5 text-xs font-bold {{ $wasCancelled ? 'border-rose-200 bg-rose-100 text-rose-800' : ($transaction->spjPackage ? 'border-amber-200 bg-amber-100 text-amber-700' : 'border-[var(--ui-line)] bg-[var(--ui-surface-muted)] text-slate-500') }}">{{ $wasCancelled ? 'Dibatalkan — menunggu nomor baru' : ($transaction->spjPackage ? 'Draft — nomor belum ditetapkan' : 'Paket belum disiapkan') }}</span></td>
                        <td class="px-4 py-3 text-right">
                            @if($transaction->spjPackage)
                                <a href="{{ route('spj.index', ['tab' => 'paket', 'package_id' => $transaction->spjPackage->id]) }}" class="font-bold text-indigo-700 hover:underline">{{ $wasCancelled ? 'Periksa paket →' : 'Lengkapi paket →' }}</a>
                            @else
                                <a href="{{ route('spj.index', ['tab' => 'persiapan', 'state' => 'unprepared']) }}" class="font-bold text-indigo-700 hover:underline">Buka persiapan →</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-10 text-center text-emerald-700">Tidak ada transaksi ber-rincian yang tertunda.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
