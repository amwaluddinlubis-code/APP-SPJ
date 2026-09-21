<section data-spj-refresh="fresh-documents" class="overflow-hidden pt-1">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3.5" style="border-color: var(--ui-line)">
        <div><h2 class="text-base font-bold" style="color: var(--ui-fg)">Dokumen &amp; Template Fresh</h2><p class="mt-0.5 text-xs" style="color: var(--ui-fg-muted)">PDF paket disusun dari template aktif yang sesuai dengan kategori transaksi.</p></div>
        @unless($validationIssues)
            <div class="flex items-center gap-2">
                <button type="button" data-template-preview="{{ route('spj.fresh-preview-package', $package->id) }}" data-template-name="Pratinjau Paket Fresh" title="Pratinjau Paket Fresh" class="ui-btn ui-btn-secondary min-h-10 min-w-10 justify-center px-3 py-2"><x-ui-icon name="preview" class="h-5 w-5" /><span class="sr-only">Pratinjau Paket Fresh</span></button>
                <form method="POST" action="{{ route('spj.fresh-download-package-excel', $package->id) }}">@csrf<button type="submit" title="Arsip Excel Paket Fresh" class="ui-btn ui-btn-secondary min-h-10 min-w-10 justify-center px-3 py-2"><x-ui-icon name="excel" class="h-5 w-5" /><span class="sr-only">Arsip Excel Paket Fresh</span></button></form>
                <form method="POST" action="{{ route('spj.fresh-download', $package->id) }}" target="_blank">@csrf<button type="submit" title="Arsip Paket PDF Fresh" class="ui-btn ui-btn-primary min-h-10 min-w-10 justify-center px-3 py-2"><x-ui-icon name="pdf" class="h-5 w-5" /><span class="sr-only">Arsip Paket PDF Fresh</span></button></form>
            </div>
        @endunless
    </div>
    @if($templates->isNotEmpty())
        <section class="border-t border-[var(--ui-line)]" aria-label="Siap dipratinjau">
            <header class="flex flex-wrap items-center justify-between gap-3 bg-slate-50/70 px-4 py-3">
                <div><h3 class="font-semibold" style="color: var(--ui-fg)">Siap dipratinjau</h3><p class="mt-0.5 text-xs" style="color: var(--ui-fg-muted)">Data paket lengkap. Preview atau arsipkan dokumen yang diperlukan.</p></div>
                <x-ui.status-badge status="PASS" :label="$templates->count().' dokumen'" />
            </header>
            <div class="grid gap-px bg-[var(--ui-line)] md:grid-cols-2">
                @foreach($templates as $template)
                    <div class="flex items-center justify-between gap-3 bg-[var(--ui-surface-base)] px-4 py-3">
                        <div><p class="font-semibold" style="color: var(--ui-fg)">{{ $template->name }}</p><p class="mt-0.5 font-mono text-[11px]" style="color: var(--theme-content-accent)">{{ $template->document_type }} · {{ strtoupper($template->format) }}</p></div>
                        <div class="flex shrink-0 items-center gap-2">
                            <button type="button" data-template-preview="{{ route('spj.fresh-preview-package', $package->id) }}" data-template-name="{{ $template->name }}" title="Pratinjau {{ $template->name }}" class="ui-btn ui-btn-secondary min-h-10 min-w-10 justify-center px-3 py-2"><x-ui-icon name="preview" class="h-5 w-5" /><span class="sr-only">Pratinjau</span></button>
                            <form method="POST" action="{{ route('spj.fresh-download-package-excel', $package->id) }}">@csrf<button type="submit" title="Arsip Excel {{ $template->name }}" class="ui-btn ui-btn-secondary min-h-10 min-w-10 justify-center px-3 py-2"><x-ui-icon name="excel" class="h-5 w-5" /><span class="sr-only">Arsip Excel</span></button></form>
                            <form method="POST" action="{{ route('spj.fresh-download', $package->id) }}" target="_blank">@csrf<button type="submit" title="Arsip PDF {{ $template->name }}" class="ui-btn ui-btn-primary min-h-10 min-w-10 justify-center px-3 py-2"><x-ui-icon name="pdf" class="h-5 w-5" /><span class="sr-only">Arsip PDF</span></button></form>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @else
        <div class="px-4 py-6 text-center text-sm" style="color: var(--ui-fg-muted)">Belum ada template Excel aktif yang sesuai dengan kategori paket fresh.</div>
    @endif
</section>
