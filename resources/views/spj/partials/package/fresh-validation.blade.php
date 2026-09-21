<section data-spj-refresh="fresh-validation" class="overflow-hidden border-b border-[var(--ui-line)] pb-1">
    <div class="flex flex-col gap-3 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-base font-bold" style="color: var(--ui-fg)">Validasi Paket Fresh</h2>
            <p class="mt-0.5 text-base {{ $validationIssues ? 'text-amber-700' : 'text-emerald-700' }}">
                {{ $validationIssues ? count($validationIssues).' data wajib perlu dilengkapi.' : 'Data dasar paket fresh sudah lengkap.' }}
            </p>
            <p class="mt-1 text-sm" style="color: var(--ui-muted)">Penomoran, preview, dan download dokumen fresh masih menunggu workflow fresh khusus.</p>
        </div>
        <x-ui.status-badge :status="$validationIssues ? 'BELUM_LENGKAP' : 'READY'" :label="$validationIssues ? 'Belum siap' : 'Siap diproses'" />
    </div>
    @if($validationIssues)
        <div class="divide-y divide-amber-100 border-t border-amber-100 bg-amber-50/40">
            @foreach($validationIssues as $issue)
                <div class="px-4 py-2.5 text-base">
                    <span class="font-bold text-amber-800">{{ $issue['label'] }}</span>
                    <span class="ml-1.5 text-amber-700">{{ $issue['message'] }}</span>
                </div>
            @endforeach
        </div>
    @endif
</section>
