@php
    $rupiah = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
    $terbilang = 'Nilai pembayaran sesuai rincian pada dokumen ini.';
    $spjItems = $transaction->goods->isNotEmpty() ? $transaction->goods : $transaction->items;
    $itemDescription = fn ($item) => $item->name ?: $item->item_description ?: $item->description;
    $receiptRecipient = $transaction->effective_receipt_recipient_name;
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 18mm 16mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; color:#111827; font-size:10px; line-height:1.45; }
        .kop { border-bottom:3px double #111827; padding-bottom:8px; margin-bottom:14px; min-height:60px; }
        .kop img { width:100%; max-height:100px; object-fit:contain; object-position:left center; }
        .kop h1 { font-size:14px; margin:0; } .kop p { margin:2px 0; color:#4b5563; }
        h2 { text-align:center; font-size:14px; margin:10px 0 4px; } h3 { font-size:11px; margin:14px 0 6px; }
        table { width:100%; border-collapse:collapse; } th,td { border:1px solid #374151; padding:5px 6px; vertical-align:top; }
        th { background:#e5e7eb; text-align:left; } .plain td { border:0; padding:3px 0; }
        .right { text-align:right; } .center { text-align:center; } .muted { color:#6b7280; }
        .page-break { page-break-before:always; } .sign td { height:78px; border:0; text-align:center; vertical-align:bottom; }
        .check { font-size:11px; } .ok { color:#047857; font-weight:bold; } .no { color:#b45309; font-weight:bold; }
    </style>
</head>
<body>
    <div class="kop">
        @if($letterhead)<img src="{{ $letterhead }}" alt="Kop Surat">@else
            <h1>{{ strtoupper($school->name) }}</h1>
            <p>{{ $school->address }} · NPSN {{ $school->npsn }}</p>
        @endif
    </div>

    <h2>KUITANSI PEMBAYARAN DANA BOSP</h2>
    <p class="center muted">Bukti penerimaan pembayaran oleh penerima atau penyedia</p>
    <table class="plain"><tr><td width="22%">Nomor Dokumen</td><td width="32%"><strong>{{ $package->document_number }}</strong></td><td width="18%">Tanggal</td><td>{{ $transaction->transaction_date?->translatedFormat('d F Y') }}</td></tr><tr><td>ID Transaksi/BKU</td><td>{{ $transaction->no_bukti }}</td><td>Sumber/Tahun</td><td>{{ $year->fund_source }} / {{ $year->year }} / {{ $package->quarter_code }}</td></tr></table>
    <br>
    <table><tr><th width="30%">Kode Kegiatan / Nama Kegiatan</th><td><strong>{{ $transaction->activity_code }}</strong><br>{{ $transaction->activity_name }}</td></tr><tr><th>Kode Rekening / Nama Rekening</th><td><strong>{{ $transaction->account_code }}</strong><br>{{ $transaction->account_name }}</td></tr></table>
    <br>
    <table><tr><th width="30%">Sudah terima dari</th><td>Bendahara Dana {{ $year->fund_source }} {{ $school->name }}</td></tr><tr><th>Penerima Kuitansi</th><td>{{ $receiptRecipient }}</td></tr><tr><th>Penerima BKU/ARKAS</th><td>{{ $transaction->recipient_name }}</td></tr><tr><th>Nilai transaksi bruto</th><td>{{ $rupiah($transaction->gross_amount) }}</td></tr><tr><th>Untuk pembayaran</th><td>{{ $transaction->payment_description ?: $transaction->description }}</td></tr><tr><th>Cara bayar/referensi</th><td>{{ $transaction->payment_method }}{{ $transaction->payment_reference ? ' / '.$transaction->payment_reference : '' }}</td></tr></table>
    <h3>RINCIAN NILAI</h3>
    <table><tr><th>Nilai transaksi bruto</th><td class="right">{{ $rupiah($transaction->gross_amount) }}</td></tr><tr><th>PPN</th><td class="right">{{ $rupiah($transaction->ppn) }}</td></tr><tr><th>PPh Pasal 21/22/23/4(2)</th><td class="right">{{ $rupiah($transaction->pph21 + $transaction->pph22 + $transaction->pph23 + $transaction->pph4) }}</td></tr><tr><th>Pajak Daerah (SSPD)</th><td class="right">{{ $rupiah($transaction->sspd) }}</td></tr><tr><th>Total potongan pajak</th><td class="right">{{ $rupiah($transaction->tax_total) }}</td></tr><tr><th>Jumlah dibayarkan/diterima</th><td class="right"><strong>{{ $rupiah($transaction->net_amount) }}</strong></td></tr></table>
    <table class="sign"><tr><td>Mengetahui<br>Kepala Satuan Pendidikan<br><br><br><strong>{{ $profile->principal_name ?? '................................' }}</strong><br>{{ $profile->principal_nip ?? '' }}</td><td>Lunas dibayar<br>Bendahara Dana {{ $year->fund_source }}<br><br><br><strong>{{ $profile->treasurer_name ?? '................................' }}</strong><br>{{ $profile->treasurer_nip ?? '' }}</td><td>Yang menerima<br>{{ $transaction->signatory_role ?: 'Penerima Kuitansi' }}<br><br><br><strong>{{ $transaction->signatory_name ?: $receiptRecipient }}</strong></td></tr></table>

    <div class="page-break"></div>
    <div class="kop">@if($letterhead)<img src="{{ $letterhead }}" alt="Kop Surat">@else<h1>{{ strtoupper($school->name) }}</h1><p>NPSN {{ $school->npsn }}</p>@endif</div>
    <h2>RINCIAN BELANJA</h2><p class="center muted">{{ $package->document_number }} · {{ $transaction->no_bukti }}</p>
    <table><thead><tr><th class="center" width="5%">No</th><th>Uraian Barang/Jasa</th><th class="center" width="9%">Vol.</th><th width="11%">Satuan</th><th class="right" width="17%">Harga</th><th class="right" width="18%">Jumlah</th></tr></thead><tbody>@foreach($transaction->items as $index => $item)<tr><td class="center">{{ $index + 1 }}</td><td>{{ $item->item_description  ?: $item->description }}</td><td class="center">{{ $item->quantity }}</td><td>{{ $item->unit ?: '—' }}</td><td class="right">{{ $rupiah($item->unit_price) }}</td><td class="right">{{ $rupiah($item->amount) }}</td></tr>@endforeach<tr><th colspan="5" class="right">TOTAL</th><th class="right">{{ $rupiah($transaction->gross_amount) }}</th></tr></tbody></table>

    <div class="page-break"></div>
    <div class="kop">@if($letterhead)<img src="{{ $letterhead }}" alt="Kop Surat">@else<h1>{{ strtoupper($school->name) }}</h1>@endif</div>
    <h2>REKAPITULASI PAJAK</h2><p class="center muted">Paket {{ $package->document_number }} · Bukti {{ $transaction->no_bukti }}</p>
    <table><thead><tr><th>Jenis Pajak</th><th class="right">Nilai</th><th>Keterangan</th></tr></thead><tbody><tr><td>PPN</td><td class="right">{{ $rupiah($transaction->ppn) }}</td><td>Jika dipungut/dipotong</td></tr><tr><td>PPh Pasal 21</td><td class="right">{{ $rupiah($transaction->pph21) }}</td><td>Sesuai transaksi</td></tr><tr><td>PPh Pasal 22</td><td class="right">{{ $rupiah($transaction->pph22) }}</td><td>Sesuai transaksi</td></tr><tr><td>PPh Pasal 23</td><td class="right">{{ $rupiah($transaction->pph23) }}</td><td>Sesuai transaksi</td></tr><tr><td>PPh Pasal 4(2)</td><td class="right">{{ $rupiah($transaction->pph4) }}</td><td>Sesuai transaksi</td></tr><tr><td>Pajak Daerah / SSPD</td><td class="right">{{ $rupiah($transaction->sspd) }}</td><td>Jika dipungut</td></tr><tr><th>Total Pajak</th><th class="right">{{ $rupiah($transaction->tax_total) }}</th><th></th></tr></tbody></table>

    <div class="page-break"></div>
    <div class="kop">@if($letterhead)<img src="{{ $letterhead }}" alt="Kop Surat">@else<h1>{{ strtoupper($school->name) }}</h1>@endif</div>
    <h2>CHECKLIST KELENGKAPAN SPJ</h2><p class="center muted">{{ $package->document_number }}</p>
    <table class="check"><thead><tr><th width="6%" class="center">No</th><th>Dokumen / Persyaratan</th><th width="18%" class="center">Status</th><th width="28%">Keterangan</th></tr></thead><tbody>@foreach([['Kuitansi pembayaran', true], ['Rincian belanja', $transaction->items->isNotEmpty()], ['Rekap pajak', true], ['Penerima kuitansi', filled($receiptRecipient)], ['Kegiatan dan rekening', filled($transaction->activity_code) && filled($transaction->account_code)], ['Cara bayar', filled($transaction->payment_method)], ['Nomor dokumen SPJ', filled($package->document_number)]] as $index => [$label,$ready])<tr><td class="center">{{ $index+1 }}</td><td>{{ $label }}</td><td class="center {{ $ready ? 'ok' : 'no' }}">{{ $ready ? 'LENGKAP' : 'BELUM' }}</td><td>{{ $ready ? 'Tersedia pada paket dokumen.' : 'Lengkapi pada data transaksi.' }}</td></tr>@endforeach</tbody></table>

    @if($transaction->workers->isNotEmpty())
        <div class="page-break"></div>
        <div class="kop">@if($letterhead)<img src="{{ $letterhead }}" alt="Kop Surat">@else<h1>{{ strtoupper($school->name) }}</h1>@endif</div>
        <h2>{{ in_array(strtoupper((string) $transaction->spj_category), ['JASA_HONORARIUM', 'HONOR_PEGAWAI'], true) ? 'LAMPIRAN PEMBAYARAN HONOR PEGAWAI' : 'LAMPIRAN PEMBAYARAN UPAH' }}</h2><p class="center muted">{{ $package->document_number }} · {{ $transaction->no_bukti }}</p>
        <table><thead><tr><th class="center">No</th><th>Nama Pekerja</th><th>Uraian Pekerjaan</th><th class="center">Hari</th><th class="right">Tarif/Hari</th><th class="right">Jumlah</th><th class="center">Penerima Kuitansi</th></tr></thead><tbody>@foreach($transaction->workers as $index=>$worker)<tr><td class="center">{{ $index+1 }}</td><td>{{ $worker->name }}</td><td>{{ $worker->job_description }}</td><td class="center">{{ $worker->work_days }}</td><td class="right">{{ $rupiah($worker->daily_rate) }}</td><td class="right">{{ $rupiah($worker->amount) }}</td><td class="center">{{ $worker->is_receipt_recipient ? 'YA' : 'TIDAK' }}</td></tr>@endforeach<tr><th colspan="5" class="right">TOTAL UPAH</th><th class="right">{{ $rupiah($transaction->workers->sum('amount')) }}</th><th></th></tr></tbody></table>
    @endif

    @if(filled($transaction->payment_reference) || filled($transaction->invoice_number))
        <div class="page-break"></div>
        <div class="kop">@if($letterhead)<img src="{{ $letterhead }}" alt="Kop Surat">@else<h1>{{ strtoupper($school->name) }}</h1>@endif</div>
        <h2>INVOICE / PESANAN</h2><p class="center muted">Dasar transaksi {{ $transaction->no_bukti }}</p>
        <table class="plain"><tr><td width="25%">Nomor Pesanan/Referensi</td><td>{{ $transaction->payment_reference }}</td></tr><tr><td>Nomor Invoice</td><td>{{ $transaction->invoice_number }}</td></tr><tr><td>Tanggal Invoice</td><td>{{ $transaction->invoice_date?->translatedFormat('d F Y') ?: $transaction->transaction_date?->translatedFormat('d F Y') }}</td></tr><tr><td>Penyedia</td><td>{{ $transaction->recipient_name }}</td></tr></table>
        <table><thead><tr><th>No</th><th>Uraian</th><th class="right">Jumlah</th></tr></thead><tbody>@foreach($transaction->items as $index => $item)<tr><td class="center">{{ $index+1 }}</td><td>{{ $item->item_description ?: $item->description }}</td><td class="right">{{ $rupiah($item->amount) }}</td></tr>@endforeach<tr><th colspan="2" class="right">TOTAL</th><th class="right">{{ $rupiah($transaction->gross_amount) }}</th></tr></tbody></table>
    @endif

    @if(in_array(strtoupper((string) $transaction->spj_category), ['UPAH', 'JASA_HONORARIUM', 'HONOR_PEGAWAI'], true))
        <div class="page-break"></div>
        <div class="kop">@if($letterhead)<img src="{{ $letterhead }}" alt="Kop Surat">@else<h1>{{ strtoupper($school->name) }}</h1>@endif</div>
        <h2>DAFTAR HADIR PEKERJA</h2><p class="center muted">{{ $package->document_number }} · {{ $transaction->work_description ?: $transaction->payment_description ?: $transaction->description }}</p>
        <table class="plain"><tr><td width="26%">Lokasi pekerjaan</td><td>{{ $transaction->work_location ?: '-' }}</td><td width="20%">Periode</td><td>{{ $transaction->work_started_at?->translatedFormat('d M Y') ?: '-' }} s.d. {{ $transaction->work_completed_at?->translatedFormat('d M Y') ?: '-' }}</td></tr></table>
        <table><thead><tr><th class="center" width="6%">No</th><th>Nama Pekerja</th><th>Uraian Pekerjaan</th><th class="center" width="12%">Hari Kerja</th><th class="center" width="20%">Tanda Tangan</th></tr></thead><tbody>@forelse($transaction->workers as $index => $worker)<tr><td class="center">{{ $index + 1 }}</td><td>{{ $worker->name }}</td><td>{{ $worker->job_description }}</td><td class="center">{{ $worker->work_days }}</td><td style="height:38px"></td></tr>@empty<tr><td colspan="5" class="center">Data pekerja belum diisi.</td></tr>@endforelse</tbody></table>
    @endif

    @if(strtoupper((string) $transaction->spj_category) === 'PEMELIHARAAN')
        <div class="page-break"></div>
        <div class="kop">@if($letterhead)<img src="{{ $letterhead }}" alt="Kop Surat">@else<h1>{{ strtoupper($school->name) }}</h1>@endif</div>
        <h2>RENCANA ANGGARAN BIAYA (RAB) PEMELIHARAAN</h2><p class="center muted">{{ $transaction->work_description ?: $transaction->payment_description ?: $transaction->description }}</p>
        <table class="plain"><tr><td width="25%">Lokasi pekerjaan</td><td>{{ $transaction->work_location ?: '-' }}</td></tr><tr><td>Waktu pelaksanaan</td><td>{{ $transaction->work_started_at?->translatedFormat('d M Y') ?: '-' }} s.d. {{ $transaction->work_completed_at?->translatedFormat('d M Y') ?: '-' }}</td></tr></table>
        <table><thead><tr><th class="center">No</th><th>Uraian Bahan / Jasa</th><th class="center">Vol.</th><th class="right">Jumlah</th></tr></thead><tbody>@foreach($transaction->items as $index => $item)<tr><td class="center">{{ $index + 1 }}</td><td>{{ $item->item_description ?: $item->description }}</td><td class="center">{{ $item->quantity }} {{ $item->unit ?: '—' }}</td><td class="right">{{ $rupiah($item->amount) }}</td></tr>@endforeach@foreach($transaction->workers as $index => $worker)<tr><td class="center">{{ $transaction->items->count() + $index + 1 }}</td><td>Upah {{ $worker->job_description ?: $worker->name }}</td><td class="center">{{ $worker->work_days }} hari</td><td class="right">{{ $rupiah($worker->amount) }}</td></tr>@endforeach<tr><th colspan="3" class="right">TOTAL RAB</th><th class="right">{{ $rupiah($transaction->gross_amount) }}</th></tr></tbody></table>

        @if(filled($transaction->spk_number))
            <div class="page-break"></div><div class="kop">@if($letterhead)<img src="{{ $letterhead }}" alt="Kop Surat">@else<h1>{{ strtoupper($school->name) }}</h1>@endif</div>
            <h2>SURAT PERINTAH KERJA (SPK)</h2><p class="center">Nomor: {{ $transaction->spk_number }}</p>
            <p>Dengan ini ditugaskan kepada <strong>{{ $transaction->signatory_name ?: $receiptRecipient }}</strong> untuk melaksanakan pekerjaan <strong>{{ $transaction->work_description ?: $transaction->payment_description ?: $transaction->description }}</strong> di {{ $transaction->work_location ?: 'lokasi yang ditetapkan' }}, pada periode {{ $transaction->work_started_at?->translatedFormat('d M Y') ?: '-' }} s.d. {{ $transaction->work_completed_at?->translatedFormat('d M Y') ?: '-' }}.</p>
        @endif

        <div class="page-break"></div><div class="kop">@if($letterhead)<img src="{{ $letterhead }}" alt="Kop Surat">@else<h1>{{ strtoupper($school->name) }}</h1>@endif</div>
        <h2>BERITA ACARA PEMERIKSAAN PEKERJAAN</h2><p>Telah dilakukan pemeriksaan atas pekerjaan <strong>{{ $transaction->work_description ?: $transaction->payment_description ?: $transaction->description }}</strong> pada {{ $transaction->work_location ?: 'lokasi pekerjaan' }}. Pekerjaan dinyatakan sesuai dengan rincian dan RAB paket {{ $package->document_number }}.</p>
        <table class="sign"><tr><td>Mengetahui<br>Kepala Satuan Pendidikan<br><br><br><strong>{{ $profile->principal_name ?? '................................' }}</strong></td><td>Pelaksana / Penerima Pekerjaan<br><br><br><strong>{{ $transaction->signatory_name ?: $receiptRecipient }}</strong></td></tr></table>

        <div class="page-break"></div><div class="kop">@if($letterhead)<img src="{{ $letterhead }}" alt="Kop Surat">@else<h1>{{ strtoupper($school->name) }}</h1>@endif</div>
        <h2>BERITA ACARA SERAH TERIMA PEKERJAAN</h2><p>Pekerjaan <strong>{{ $transaction->work_description ?: $transaction->payment_description ?: $transaction->description }}</strong> telah diserahterimakan dalam keadaan baik dan dapat digunakan sebagaimana mestinya.</p>
        <table class="sign"><tr><td>Pihak Pertama<br>Bendahara Dana {{ $year->fund_source }}<br><br><br><strong>{{ $profile->treasurer_name ?? '................................' }}</strong></td><td>Pihak Kedua<br>Pelaksana / Penerima Pekerjaan<br><br><br><strong>{{ $transaction->signatory_name ?: $receiptRecipient }}</strong></td></tr></table>
    @endif

    @if(in_array(strtoupper((string) $transaction->spj_category), ['BARANG', 'BELANJA_MODAL'], true))
        <div class="page-break"></div><div class="kop">@if($letterhead)<img src="{{ $letterhead }}" alt="Kop Surat">@else<h1>{{ strtoupper($school->name) }}</h1>@endif</div>
        <h2>BERITA ACARA PEMERIKSAAN BARANG</h2><p>Barang pada paket {{ $package->document_number }} dengan nomor bukti {{ $transaction->no_bukti }} telah diperiksa dan dinyatakan sesuai rincian belanja.</p>
        <table><thead><tr><th>No</th><th>Uraian Barang</th><th class="center">Jumlah</th><th class="center">Kondisi</th></tr></thead><tbody>@foreach($transaction->items as $index => $item)<tr><td class="center">{{ $index + 1 }}</td><td>{{ $item->item_description ?: $item->description }}</td><td class="center">{{ $item->quantity }} {{ $item->unit ?: '—' }}</td><td class="center">Baik</td></tr>@endforeach</tbody></table>
        <table class="sign"><tr><td>Pemeriksa<br><br><br><strong>{{ $profile->principal_name ?? '................................' }}</strong></td><td>Penyedia<br><br><br><strong>{{ $transaction->recipient_name }}</strong></td></tr></table>

        <div class="page-break"></div><div class="kop">@if($letterhead)<img src="{{ $letterhead }}" alt="Kop Surat">@else<h1>{{ strtoupper($school->name) }}</h1>@endif</div>
        <h2>BERITA ACARA SERAH TERIMA BARANG</h2><p>Barang sebagaimana tercantum pada rincian belanja paket {{ $package->document_number }} telah diterima dalam keadaan baik.</p>
        <table class="sign"><tr><td>Yang Menyerahkan<br><br><br><strong>{{ $transaction->recipient_name }}</strong></td><td>Yang Menerima<br><br><br><strong>{{ $profile->principal_name ?? '................................' }}</strong></td></tr></table>
    @endif
</body>
</html>
