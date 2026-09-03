<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Paket Pemeriksaan BPK</title>
    <style>
        @page { margin: 24mm 16mm 18mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #0f172a; line-height: 1.45; }
        h1, h2, h3, p { margin: 0; }
        .cover { min-height: 240mm; display: table; width: 100%; }
        .cover-inner { display: table-cell; vertical-align: middle; text-align: center; }
        .eyebrow { font-size: 11px; font-weight: 700; letter-spacing: 1.2px; color: #475569; }
        .title { margin-top: 12px; font-size: 25px; font-weight: 800; color: #0f172a; }
        .subtitle { margin: 8px auto 0; max-width: 430px; color: #475569; font-size: 11px; }
        .school { margin-top: 34px; font-size: 18px; font-weight: 800; color: #1e3a8a; }
        .meta { margin: 18px auto 0; width: 78%; border-collapse: collapse; }
        .meta td { padding: 7px 9px; border: 1px solid #cbd5e1; text-align: left; }
        .meta td:first-child { width: 36%; background: #f8fafc; font-weight: 700; }
        .notice { margin: 28px auto 0; width: 78%; padding: 10px 12px; border: 1px solid #fbbf24; background: #fffbeb; color: #78350f; text-align: left; }
        .page-break { page-break-before: always; }
        .section-title { margin-bottom: 10px; padding-bottom: 7px; border-bottom: 2px solid #1e3a8a; font-size: 15px; font-weight: 800; color: #1e3a8a; }
        .section-note { margin-bottom: 12px; color: #64748b; }
        .summary { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .summary td { width: 25%; border: 1px solid #cbd5e1; padding: 8px; vertical-align: top; }
        .summary .label { color: #64748b; font-size: 8px; text-transform: uppercase; font-weight: 700; }
        .summary .value { margin-top: 3px; font-size: 13px; font-weight: 800; }
        table.data { width: 100%; border-collapse: collapse; margin: 7px 0 14px; }
        table.data th { background: #e2e8f0; color: #334155; border: 1px solid #94a3b8; padding: 5px 6px; text-align: left; font-size: 8px; }
        table.data td { border: 1px solid #cbd5e1; padding: 5px 6px; vertical-align: top; }
        .right { text-align: right; }
        .center { text-align: center; }
        .ok { color: #047857; font-weight: 700; }
        .warn { color: #b91c1c; font-weight: 700; }
        .small { font-size: 8px; color: #64748b; }
        .checklist { width: 100%; border-collapse: collapse; }
        .checklist td { border-bottom: 1px solid #e2e8f0; padding: 7px 5px; }
        .check { width: 26px; text-align: center; font-size: 14px; }
        .footer-note { margin-top: 16px; font-size: 8px; color: #64748b; }
    </style>
</head>
<body>
@php($rupiah = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.'))

<section class="cover">
    <div class="cover-inner">
        <div class="eyebrow">PAKET BANTU PEMERIKSAAN</div>
        <div class="title">PAKET PEMERIKSAAN BPK</div>
        <p class="subtitle">Ringkasan data dan dokumen pendukung pengelolaan BOSP untuk membantu penelusuran transaksi dari perencanaan, pembukuan, pajak, sampai SPJ.</p>
        <div class="school">{{ $school->name }}</div>
        <table class="meta">
            <tr><td>NPSN</td><td>{{ $school->npsn ?: '-' }}</td></tr>
            <tr><td>Tahun Anggaran</td><td>{{ $year->year }}</td></tr>
            <tr><td>Sumber Dana</td><td>{{ $fundSource?->name ?? $year->fund_source }}</td></tr>
            <tr><td>Jumlah Transaksi</td><td>{{ number_format($summary['transactionCount'], 0, ',', '.') }} transaksi</td></tr>
            <tr><td>Nilai Transaksi</td><td>{{ $rupiah($summary['transactions']) }}</td></tr>
            <tr><td>Dibuat</td><td>{{ $generatedAt->translatedFormat('d F Y H:i') }}</td></tr>
        </table>
        <div class="notice"><strong>Catatan:</strong> dokumen ini adalah paket bantu pemeriksaan yang dibuat aplikasi. Dokumen ini bukan formulir resmi BPK dan tidak menggantikan dokumen sumber, bukti fisik, atau permintaan dokumen dari pemeriksa.</div>
    </div>
</section>

<section class="page-break">
    <h2 class="section-title">1. Ringkasan Pemeriksaan</h2>
    <p class="section-note">Bagian ini memberi gambaran cepat tentang anggaran, realisasi, SPJ, pajak, dan hal yang masih perlu diperiksa.</p>
    <table class="summary">
        <tr>
            <td><div class="label">RKAS</div><div class="value">{{ $rupiah($summary['budget']) }}</div></td>
            <td><div class="label">BKU Belanja</div><div class="value">{{ $rupiah($summary['bku']) }}</div></td>
            <td><div class="label">Transaksi</div><div class="value">{{ $rupiah($summary['transactions']) }}</div></td>
            <td><div class="label">Pajak</div><div class="value">{{ $rupiah($summary['tax']) }}</div></td>
        </tr>
        <tr>
            <td><div class="label">Paket SPJ</div><div class="value">{{ $summary['spjPackaged'] }}</div></td>
            <td><div class="label">SPJ Bernomor</div><div class="value">{{ $summary['spjNumbered'] }}</div></td>
            <td><div class="label">Selisih Rekonsiliasi</div><div class="value">{{ $summary['mismatchCount'] }}</div></td>
            <td><div class="label">Perlu Tindakan</div><div class="value">{{ $summary['exceptionCount'] }}</div></td>
        </tr>
    </table>

    <h3 style="margin:14px 0 6px;font-size:12px;">Daftar Pemeriksaan Awal</h3>
    <table class="checklist">
        <tr><td class="check">□</td><td>RKAS sesuai dengan tahun anggaran dan sumber dana aktif.</td></tr>
        <tr><td class="check">□</td><td>BKU dapat ditelusuri ke transaksi dan nomor bukti.</td></tr>
        <tr><td class="check">□</td><td>Nilai transaksi sesuai dengan BKU dan tidak terdapat selisih yang belum dijelaskan.</td></tr>
        <tr><td class="check">□</td><td>Pajak telah dicatat dan bukti setor tersedia jika diwajibkan.</td></tr>
        <tr><td class="check">□</td><td>Paket SPJ dan dokumen pendukung tersedia sesuai jenis belanja.</td></tr>
        <tr><td class="check">□</td><td>Nomor dan tanggal dokumen konsisten serta dapat ditelusuri.</td></tr>
        <tr><td class="check">□</td><td>Barang, jasa, honor, perjalanan, atau pekerjaan telah didukung bukti penerimaan/pelaksanaan yang sesuai.</td></tr>
    </table>

    <p class="footer-note">Daftar di atas merupakan alat bantu internal sekolah untuk kesiapan pemeriksaan.</p>
</section>

<section class="page-break">
    <h2 class="section-title">2. Rekonsiliasi RKAS · BKU · Transaksi · SPJ</h2>
    <p class="section-note">Baris berstatus selain “SESUAI” perlu ditelusuri kembali ke dokumen sumber.</p>
    <table class="data">
        <thead><tr><th>No Bukti</th><th>Tanggal</th><th class="right">BKU</th><th class="right">Transaksi</th><th class="right">Selisih</th><th>SPJ</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($reconciliationRows as $row)
            <tr>
                <td>{{ $row->no_bukti }}</td>
                <td>{{ optional($row->transaction_date)->format('d-m-Y') ?: '-' }}</td>
                <td class="right">{{ $rupiah($row->bku_amount) }}</td>
                <td class="right">{{ $rupiah($row->transaction_amount) }}</td>
                <td class="right {{ abs($row->variance) > .01 ? 'warn' : 'ok' }}">{{ $rupiah($row->variance) }}</td>
                <td>{{ $row->spj_status }}</td>
                <td class="{{ $row->status === 'SESUAI' ? 'ok' : 'warn' }}">{{ $row->status }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="center">Belum ada data rekonsiliasi.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>

<section class="page-break">
    <h2 class="section-title">3. Register Transaksi</h2>
    <table class="data">
        <thead><tr><th>No</th><th>No Bukti</th><th>Tanggal</th><th>Uraian</th><th>Penerima</th><th>Rekening</th><th class="right">Bruto</th><th class="right">Pajak</th><th>SPJ</th></tr></thead>
        <tbody>
        @forelse($register as $index => $transaction)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $transaction->no_bukti ?: '-' }}</td>
                <td>{{ $transaction->transaction_date?->format('d-m-Y') ?: '-' }}</td>
                <td>{{ $transaction->description ?: '-' }}</td>
                <td>{{ $transaction->recipient_name ?: '-' }}</td>
                <td>{{ $transaction->account_code ?: '-' }}</td>
                <td class="right">{{ $rupiah($transaction->gross_amount) }}</td>
                <td class="right">{{ $rupiah($transaction->tax_total) }}</td>
                <td>{{ $transaction->spjPackage?->document_number ?: ($transaction->spjPackage ? 'BELUM BERNOMOR' : 'BELUM ADA') }}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="center">Belum ada transaksi.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>

<section class="page-break">
    <h2 class="section-title">4. Rekap Pajak</h2>
    <table class="data">
        <thead><tr><th>Jenis Pajak</th><th class="right">Jumlah Transaksi</th><th class="right">Nominal</th></tr></thead>
        <tbody>
        @foreach($taxSummary as $row)
            <tr><td>{{ $row->label }}</td><td class="right">{{ $row->count }}</td><td class="right">{{ $rupiah($row->amount) }}</td></tr>
        @endforeach
        </tbody>
    </table>

    <h2 class="section-title" style="margin-top:24px;">5. Kelengkapan SPJ</h2>
    <p class="section-note">Daftar ini membantu sekolah menemukan transaksi yang belum siap diperiksa.</p>
    <table class="data">
        <thead><tr><th>No Bukti</th><th>Tanggal</th><th>Penerima</th><th class="right">Nilai</th><th>Status</th><th>Yang Perlu Diperbaiki</th></tr></thead>
        <tbody>
        @forelse($completenessRows as $row)
            <tr>
                <td>{{ $row->no_bukti ?: '-' }}</td>
                <td>{{ optional($row->transaction_date)->format('d-m-Y') ?: '-' }}</td>
                <td>{{ $row->recipient_name ?: '-' }}</td>
                <td class="right">{{ $rupiah($row->amount) }}</td>
                <td class="{{ $row->status === 'LENGKAP' ? 'ok' : 'warn' }}">{{ $row->status }}</td>
                <td>{{ $row->issues ? implode('; ', $row->issues) : '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="center">Tidak ada data kelengkapan SPJ.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>

<section class="page-break">
    <h2 class="section-title">6. Riwayat Sinkronisasi dan Aktivitas</h2>
    <p class="section-note">Riwayat ini membantu menunjukkan kapan data ARKAS/BKU diperbarui dan aktivitas aplikasi yang tercatat.</p>
    <table class="data">
        <thead><tr><th>Waktu</th><th>Jenis</th><th>Status/Aksi</th><th>Keterangan</th></tr></thead>
        <tbody>
        @forelse($syncRuns as $row)
            <tr><td>{{ $row->started_at }}</td><td>SINKRONISASI {{ $row->source }}</td><td>{{ $row->status }}</td><td>{{ $row->message ?: 'Dibaca '.$row->records_read.' · ditulis '.$row->records_written }}</td></tr>
        @empty
            <tr><td colspan="4" class="center">Belum ada riwayat sinkronisasi.</td></tr>
        @endforelse
        @foreach($auditLogs as $row)
            <tr><td>{{ $row->created_at }}</td><td>{{ $row->entity_type }}</td><td>{{ $row->action }}</td><td>{{ $row->description }}</td></tr>
        @endforeach
        </tbody>
    </table>

    <h3 style="margin:16px 0 6px;font-size:12px;">Batasan Data</h3>
    <ul>
        @foreach($limitations as $limitation)<li>{{ $limitation }}</li>@endforeach
    </ul>

    <p class="footer-note">Paket ini sebaiknya disertai dokumen sumber asli/digital seperti RKAS, BKU, rekening koran, bukti transfer, kuitansi, faktur/nota, bukti pajak, surat pesanan/SPK, berita acara, daftar honor, dokumen perjalanan dinas, serta dokumen lain sesuai jenis transaksi.</p>
</section>
</body>
</html>
