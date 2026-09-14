{{-- Modal pratinjau dokumen generik (dipakai workspace paket + tabel laporan).
     Satu-satunya instance modal di halaman SPJ; tombol pemicu memakai atribut
     data-template-preview (URL), data-template-name (judul), dan
     data-close-template-preview. Handler JS terdelegasi penuh di document
     pada resources/views/spj/index.blade.php. --}}
<div id="template-preview-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="template-preview-title">
    <div class="flex h-[min(92vh,1100px)] w-full max-w-[1600px] flex-col overflow-hidden rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-2xl">
        <header class="flex shrink-0 items-center justify-between gap-3 border-b border-[var(--ui-line)] px-4 py-3">
            <div><p class="text-xs font-bold uppercase tracking-wide" style="color: var(--theme-content-accent)">Pratinjau Dokumen</p><h2 id="template-preview-title" class="mt-0.5 font-bold" style="color: var(--ui-fg-strong)">Dokumen SPJ</h2></div>
            <button type="button" data-close-template-preview class="ui-btn ui-btn-secondary min-h-10 px-4">Tutup</button>
        </header>
        <div class="min-h-0 flex-1 overflow-auto bg-[var(--ui-surface-muted)] p-3"><iframe id="template-preview-frame" title="Pratinjau dokumen SPJ" class="h-full min-h-[760px] w-full rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-base)]"></iframe></div>
    </div>
</div>
