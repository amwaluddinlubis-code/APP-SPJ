<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pratinjau {{ $template->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        // Tautan pratinjau lama masih memakai target="_blank".
        // Alihkan pratinjau ke tab asal lalu tutup tab sementara agar operator
        // tetap bekerja pada halaman browser yang sama.
        if (window.opener && !window.opener.closed) {
            window.opener.location.assign(window.location.href);
            window.close();
        }
    </script>
</head>
<body class="min-h-screen bg-[var(--ui-surface-muted)] text-slate-900">
    <header class="sticky top-0 z-10 border-b border-[var(--ui-line)] bg-white/95 px-4 py-3 shadow-sm backdrop-blur sm:px-6">
        <div class="mx-auto flex max-w-[1600px] flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs font-bold tracking-[.14em] text-indigo-600">PRATINJAU TEMPLATE</p>
                <h1 class="text-base font-bold text-slate-900">{{ $template->name }}</h1>
                <p class="text-xs text-slate-500">{{ $package->document_number ?: 'Nomor SPJ belum ditetapkan' }} · {{ $package->transaction->no_bukti }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('spj.index', ['tab' => 'paket', 'package_id' => $package->id]) }}" class="rounded-md border border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">← Kembali ke Paket</a>
                @if(!empty($previewPdfReady) && !empty($previewPdfUrl))
                    <button type="button" id="spj-print-btn" disabled class="rounded-md bg-indigo-700 px-3 py-2 text-sm font-bold text-white opacity-50 hover:bg-indigo-800">Cetak langsung</button>
                    <a href="{{ $previewPdfUrl }}" target="_blank" rel="noopener" class="rounded-md bg-slate-800 px-3 py-2 text-sm font-bold text-white hover:bg-slate-950">Buka &amp; Cetak PDF</a>
                @endif
            </div>
        </div>
        @if($validationIssues)
            <div class="mx-auto mt-3 max-w-[1600px] rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">Pratinjau menampilkan data saat ini; {{ count($validationIssues) }} isian wajib masih belum lengkap.</div>
        @endif
        @if(!empty($previewPdfReady) && !empty($previewPdfUrl))
            <div class="mx-auto mt-3 max-w-[1600px] rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">Halaman ini adalah cetakan resmi SPJ — yang tercetak sama dengan berkas unduhan. Tombol unduh hanya untuk arsip.</div>
        @endif
        @if(empty($package->document_number))
            <div class="mx-auto mt-3 max-w-[1600px] rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">Nomor SPJ belum terbit — hasil cetak dari halaman ini belum menjadi SPJ asli. Terbitkan nomor terlebih dahulu.</div>
        @endif
    </header>
    <main class="mx-auto max-w-[1600px] p-4 sm:p-6">
        @if(!empty($previewPdfReady) && !empty($previewPdfUrl))
            <div class="overflow-hidden rounded-lg border border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] shadow-sm">
                <p id="spj-pdf-loading" class="p-6 text-sm text-slate-500">Memuat pratinjau PDF…</p>
                <p id="spj-pdf-error" class="hidden p-6 text-sm text-amber-800">Pratinjau otomatis gagal dimuat. <a href="{{ $previewPdfUrl }}" target="_blank" rel="noopener" class="font-bold underline">Buka PDF di tab baru</a>.</p>
                <embed id="spj-pdf-embed" title="Pratinjau PDF {{ $template->name }}" type="application/pdf" class="hidden min-h-[1000px] w-full">
                <iframe id="spj-print-frame" class="hidden" title="Bingkai cetak"></iframe>
            </div>
            <script>
                // PDF dimuat via fetch → blob URL, bukan navigasi langsung.
                // Download manager (mis. IDM) umumnya hanya mencegat navigasi/unduhan,
                // sehingga pratinjau tetap tampil di halaman tanpa ter-download.
                // Tombol "Cetak langsung" mencetak blob yang sama via bingkai
                // tersembunyi: yang tercetak adalah SPJ asli.
                (function () {
                    var url = @json($previewPdfUrl);
                    var objectUrl = null;
                    var printBtn = document.getElementById('spj-print-btn');
                    printBtn.addEventListener('click', function () {
                        if (!objectUrl) {
                            return;
                        }
                        var frame = document.getElementById('spj-print-frame');
                        frame.onload = function () {
                            frame.contentWindow.focus();
                            frame.contentWindow.print();
                        };
                        frame.setAttribute('src', objectUrl);
                    });
                    fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(function (response) {
                            if (!response.ok) {
                                throw new Error('HTTP ' + response.status);
                            }
                            return response.blob();
                        })
                        .then(function (blob) {
                            objectUrl = URL.createObjectURL(blob);
                            var embed = document.getElementById('spj-pdf-embed');
                            embed.setAttribute('src', objectUrl);
                            embed.classList.remove('hidden');
                            document.getElementById('spj-pdf-loading').classList.add('hidden');
                            printBtn.disabled = false;
                            printBtn.classList.remove('opacity-50');
                        })
                        .catch(function () {
                            document.getElementById('spj-pdf-loading').classList.add('hidden');
                            document.getElementById('spj-pdf-error').classList.remove('hidden');
                        });
                })();
            </script>
        @elseif(!empty($previewHtml))
            <div class="overflow-auto rounded-lg border border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] p-3 shadow-sm">
                <p class="mb-2 text-xs text-slate-500">Tampilan pendekatan (HTML). Untuk hasil cetak yang sama dengan arsip, gunakan tombol “Buka &amp; Cetak PDF” bila tersedia.</p>
                <iframe title="Pratinjau {{ $template->name }}" class="min-h-[1000px] w-full border-0" srcdoc="{{ $previewHtml }}"></iframe>
            </div>
        @else
            <section class="mx-auto max-w-2xl rounded-xl border border-amber-200 bg-[var(--ui-surface-base)] p-6 shadow-sm">
                <h2 class="text-lg font-bold text-slate-900">Pratinjau PDF belum tersedia untuk dokumen ini</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">Pratinjau PDF untuk template Word membutuhkan LibreOffice di server. Hubungi administrator bila pesan ini muncul di server produksi. Unduh dokumen asli (Excel/Word) tetap tersedia dan tidak terpengaruh.</p>
            </section>
        @endif
    </main>
</body>
</html>
