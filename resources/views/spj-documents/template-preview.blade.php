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
<body class="min-h-screen bg-slate-100 text-slate-900">
    <header class="sticky top-0 z-10 border-b border-slate-200 bg-white/95 px-4 py-3 shadow-sm backdrop-blur sm:px-6">
        <div class="mx-auto flex max-w-[1600px] flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs font-bold tracking-[.14em] text-indigo-600">PRATINJAU TEMPLATE</p>
                <h1 class="text-base font-bold text-slate-900">{{ $template->name }}</h1>
                <p class="text-xs text-slate-500">{{ $package->document_number ?: 'Nomor SPJ belum ditetapkan' }} · {{ $package->transaction->no_bukti }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('spj.index', ['tab' => 'paket', 'package_id' => $package->id]) }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">← Kembali ke Paket</a>
                <button type="button" onclick="window.print()" class="rounded-md bg-slate-800 px-3 py-2 text-sm font-bold text-white hover:bg-slate-950">Cetak / Simpan PDF</button>
            </div>
        </div>
        @if($validationIssues)
            <div class="mx-auto mt-3 max-w-[1600px] rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">Pratinjau menampilkan data saat ini; {{ count($validationIssues) }} isian wajib masih belum lengkap.</div>
        @endif
    </header>
    <main class="mx-auto max-w-[1600px] p-4 sm:p-6">
        @if($previewHtml)
            <div class="overflow-auto rounded-lg border border-slate-300 bg-white p-3 shadow-sm">
                <iframe title="Pratinjau {{ $template->name }}" class="min-h-[1000px] w-full border-0" srcdoc="{{ $previewHtml }}"></iframe>
            </div>
        @else
            <section class="mx-auto max-w-2xl rounded-xl border border-amber-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-slate-900">Pratinjau visual belum tersedia untuk template Word</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">Pratinjau HTML saat ini mendukung Excel (.xlsx), karena layout, merge cell, dan kop dapat dipertahankan di browser. Template Word (.docx) akan mendukung pratinjau PDF setelah LibreOffice terpasang.</p>
            </section>
        @endif
    </main>
</body>
</html>
