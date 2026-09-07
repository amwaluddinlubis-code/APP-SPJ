<div data-spj-refresh="numbering">
                            <h2 class="text-base font-bold text-slate-800">Penomoran Dokumen</h2>
                            <p class="mt-1 text-xs text-slate-500">Nomor mengikuti format aktif dan tanggal peristiwa. Nomor yang sudah diterbitkan tidak akan ditimpa.</p>
                            <div class="mt-3 grid gap-3 sm:grid-cols-3"><div class="rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3"><p class="text-[11px] font-bold uppercase text-slate-500">Kategori</p><p class="mt-1 font-bold text-slate-800">{{ $spjTypeLabel($packageCategory) }}</p></div><div class="rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3"><p class="text-[11px] font-bold uppercase text-slate-500">Tanggal sumber</p><p class="mt-1 font-bold text-slate-800">{{ $transaction->transaction_date?->translatedFormat('d F Y') }}</p></div><div class="rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3"><p class="text-[11px] font-bold uppercase text-slate-500">Cakupan nomor</p><p class="mt-1 font-bold text-indigo-700">{{ $isHonorPackage ? 'SPJ utama · dipakai pada kuitansi honor' : ($isGoodsPackage ? 'SPJ, Pesanan, BAP, BAST' : 'SPJ dan dokumen kategori') }}</p></div></div>
                            @if($hasActiveSpjNumber)
                                <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                                    <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-600">Sudah ditetapkan</p>
                                    <p class="mt-1 font-mono text-base font-bold text-emerald-800 break-all">{{ $activeSpjDocument->document_number }}</p>
                                </div>
                            @elseif($package->status === 'CANCELLED')
                                <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-4">
                                    <p class="text-[11px] font-bold uppercase tracking-wide text-rose-600">Nomor dibatalkan</p>
                                    <p class="mt-1 break-all font-mono text-base font-bold text-rose-800 line-through">{{ $cancelledSpjDocument?->document_number ?: $package->document_number }}</p>
                                    @if($cancelledSpjDocument?->cancellation_reason)<p class="mt-2 text-xs text-rose-700">Alasan: {{ $cancelledSpjDocument->cancellation_reason }}</p>@endif
                                    <form class="mt-3" method="POST" action="{{ route('spj.assign-number', $package->id) }}" data-confirm="Terbitkan ulang nomor SPJ tanpa mengubah data paket? Sistem akan menggunakan slot nomor tersedia sesuai urutan aktif.">
                                        @csrf
                                        <button class="rounded-md bg-violet-600 px-4 py-2 text-sm font-bold text-white shadow hover:bg-violet-700">Terbitkan ulang nomor</button>
                                        <p class="mt-1 text-xs text-rose-700">Gunakan ini jika isian sudah benar. Untuk koreksi data, buka paket terlebih dahulu.</p>
                                    </form>
                                </div>
                            @else
                                <div class="mt-4 rounded-lg border border-dashed border-[var(--ui-line-strong)] bg-[var(--ui-surface-soft)] p-6 text-center">
                                    <p class="text-base font-medium text-slate-700">Belum bernomor</p>
                                    <p class="mt-1 text-xs text-slate-500">Klik tombol di bawah untuk menerbitkan nomor SPJ sesuai urutan BKU.</p>
                                    <form class="mt-4" method="POST" action="{{ route('spj.assign-number', $package->id) }}">@csrf<button class="rounded-md bg-violet-600 px-4 py-2 text-base font-bold text-white shadow hover:bg-violet-700">Terbitkan nomor SPJ</button><p class="mt-2 text-xs text-slate-500">Nomor SPJ mengikuti tanggal transaksi dan urutan BKU. Nomor pesanan, BAP, dan BAST diterbitkan terpisah menurut tanggal dokumennya.</p></form>
                                </div>
                            @endif
                            @if($package->documents->isNotEmpty())
                                <div class="mt-4 divide-y divide-[var(--ui-line)] rounded-lg border border-[var(--ui-line)]">
                                    @foreach($package->documents as $document)
                                        <div class="p-3"><div class="flex flex-wrap items-center justify-between gap-2"><div><b>{{ $document->document_type }}</b><p class="font-mono text-xs text-slate-600">{{ $document->document_number }}</p></div><span class="rounded-full bg-[var(--ui-surface-muted)] px-2 py-1 text-xs font-bold">{{ $document->status }}@if($document->is_late_entry) · SUSULAN @endif</span></div>
                                            @if(in_array($document->status, ['NUMBERED', 'FINAL']))
                                                <div class="mt-2 flex flex-wrap gap-2">
                                                    @if($document->status === 'NUMBERED')<form method="POST" action="{{ route('spj.documents.finalize', $document->id) }}">@csrf<button class="rounded bg-emerald-600 px-2 py-1 text-xs font-bold text-white">Finalkan</button></form>@endif
                                                    <form method="POST" action="{{ route('spj.documents.replace', $document->id) }}" class="flex gap-1" data-confirm="Nomor lama {{ $document->document_number }} akan dibatalkan permanen dan sistem menerbitkan nomor baru. Lanjutkan?">@csrf<input name="reason" required minlength="5" placeholder="Alasan penomoran ulang" class="rounded border-[var(--ui-line-strong)] text-xs"><button class="rounded bg-amber-600 px-2 py-1 text-xs font-bold text-white hover:bg-amber-700">Nomori ulang</button></form>
                                                    @if(auth()->user()->isAdministrator())
                                                        <form method="POST" action="{{ route('spj.documents.cancel', $document->id) }}" class="flex gap-1" data-confirm="Batalkan nomor {{ $document->document_number }}? Slot nomor dapat dipakai kembali sesuai aturan urutan.">@csrf<input name="reason" required minlength="5" placeholder="Alasan pembatalan" class="rounded border-rose-300 text-xs"><button class="rounded bg-rose-600 px-2 py-1 text-xs font-bold text-white hover:bg-rose-700">Batalkan nomor</button></form>
                                                    @endif
                                                </div>
                                            @elseif($document->status === 'CANCELLED')
                                                <div class="mt-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800"><b>Nomor dibatalkan.</b> {{ $document->cancellation_reason }}</div>
                                                @if($document->document_type === 'SPJ' && $document->scope_key === 'MAIN' && $package->status === 'DRAFT')
                                                    <form method="POST" action="{{ route('spj.assign-number', $package->id) }}" class="mt-2" data-confirm="Terbitkan nomor SPJ baru sebagai pengganti nomor yang dibatalkan?">
                                                        @csrf
                                                        <button class="rounded bg-violet-600 px-3 py-2 text-xs font-bold text-white shadow-sm hover:bg-violet-700">Terbitkan nomor pengganti</button>
                                                        <p class="mt-1 text-xs text-slate-500">Nomor lama tetap tersimpan dalam riwayat dan tidak digunakan kembali.</p>
                                                    </form>
                                                @endif
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            @if($package->status === 'CANCELLED' && auth()->user()->isAdministrator())
                                <form method="POST" action="{{ route('spj.unlock', $package->id) }}" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3">@csrf<label class="text-xs font-bold text-amber-900">Buka paket untuk koreksi input</label><div class="mt-2 flex gap-2"><input name="reason" required minlength="5" placeholder="Alasan pembukaan paket" class="min-w-0 flex-1 rounded border-amber-300 text-sm"><button class="rounded bg-amber-600 px-3 py-2 text-xs font-bold text-white hover:bg-amber-700">Buka paket</button></div></form>
                            @endif
                            @unless($isHonorPackage)<fieldset @disabled(!$package->isEditable()) class="mt-5 disabled:cursor-not-allowed disabled:opacity-60"><div class="grid gap-4 {{ $isGoodsPackage ? 'lg:grid-cols-2' : '' }}">
                                @if($isGoodsPackage)<section class="rounded-lg border border-[var(--ui-line)] p-4">
                                    <h3 class="font-bold text-slate-800">Pembayaran bertahap</h3>
                                    <p class="mt-1 text-xs text-slate-500">Total bruto tidak boleh melampaui nilai transaksi.</p>
                                    <div class="mt-3 space-y-2">@forelse($transaction->payments as $payment)<div class="flex justify-between text-sm"><span>{{ $payment->payment_date?->translatedFormat('d F Y') }} · {{ $payment->scope_key }} @if($payment->is_late_entry)<b class="text-amber-700">Susulan</b>@endif</span><b>{{ $rupiah($payment->gross_amount) }}</b></div>@empty<p class="text-xs text-slate-400">Belum ada pembayaran.</p>@endforelse</div>
                                    <form method="POST" action="{{ route('spj.payments.store', $transaction->id) }}" class="mt-3 grid gap-2 sm:grid-cols-2">@csrf
                                        <input type="date" name="payment_date" required class="rounded-md border-[var(--ui-line-strong)] text-sm"><input type="number" name="gross_amount" min="1" required placeholder="Nilai bruto" class="rounded-md border-[var(--ui-line-strong)] text-sm">
                                        <input type="number" name="tax_amount" min="0" value="0" placeholder="Pajak" class="rounded-md border-[var(--ui-line-strong)] text-sm"><input name="payment_reference" placeholder="Referensi pembayaran" class="rounded-md border-[var(--ui-line-strong)] text-sm">
                                        <button class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-bold text-white sm:col-span-2">Tambah pembayaran</button>
                                    </form>
                                </section>
                                <section class="rounded-lg border border-[var(--ui-line)] p-4">
                                    <h3 class="font-bold text-slate-800">Penerimaan barang bertahap</h3>
                                    <p class="mt-1 text-xs text-slate-500">Jumlah kumulatif tidak boleh melebihi jumlah pesanan.</p>
                                    <div class="mt-3 space-y-2">@forelse($transaction->goodsReceipts as $receipt)<div class="text-sm"><b>{{ $receipt->scope_key }}</b> · {{ $receipt->receipt_date?->translatedFormat('d F Y') }} @if($receipt->is_late_entry)<b class="text-amber-700">Susulan</b>@endif</div>@empty<p class="text-xs text-slate-400">Belum ada penerimaan bertahap.</p>@endforelse</div>
                                    <form method="POST" action="{{ route('spj.receipts.store', $transaction->id) }}" class="mt-3 grid gap-2 sm:grid-cols-2">@csrf
                                        <input type="date" name="receipt_date" required class="rounded-md border-[var(--ui-line-strong)] text-sm">
                                        <select name="items[0][transaction_item_id]" required class="rounded-md border-[var(--ui-line-strong)] text-sm"><option value="">Pilih barang</option>@foreach($transaction->items as $item)<option value="{{ $item->id }}">{{ $item->item_description ?: $item->description }}</option>@endforeach</select>
                                        <input type="number" step="0.0001" min="0.0001" name="items[0][quantity_received]" required placeholder="Jumlah diterima" class="rounded-md border-[var(--ui-line-strong)] text-sm"><input type="number" min="0" name="items[0][amount_received]" placeholder="Nilai penerimaan" class="rounded-md border-[var(--ui-line-strong)] text-sm">
                                        <button class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-bold text-white sm:col-span-2">Tambah penerimaan</button>
                                    </form>
                                </section>@endif
                            </div></fieldset>@endunless
</div>
