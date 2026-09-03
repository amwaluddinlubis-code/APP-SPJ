<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Daftar Pembayaran Honor</title>
    <style>
        @page { margin: 24px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 10px; }
        h1, p { margin: 0; text-align: center; }
        h1 { font-size: 16px; }
        .subtitle { margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #374151; padding: 6px 5px; vertical-align: middle; }
        th { background: #e0e7ff; text-align: center; }
        .number { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .signature { height: 42px; min-width: 90px; vertical-align: top; }
        tfoot td { font-weight: bold; background: #f3f4f6; }
    </style>
</head>
<body>
    <h1>DAFTAR PEMBAYARAN HONOR PEGAWAI</h1>
    <p class="subtitle">{{ $school->name }} · Tahun Anggaran {{ $year->year }}</p>
    <table>
        <thead><tr><th>No</th><th>BPU / Nomor SPJ</th><th>Tanggal</th><th>Nama Penerima</th><th>Jabatan/Jenis Honor</th><th>Bulan/Kali</th><th>Tarif</th><th>Bruto</th><th>PPh 21</th><th>Dibayarkan</th><th>Tanda Tangan</th></tr></thead>
        <tbody>
            @forelse($honors as $index => $honor)
                @php($transaction = $honor->item->transaction)
                <tr><td class="center">{{ $index + 1 }}</td><td>{{ $transaction->no_bukti }}<br><small>{{ $transaction->spjPackage?->document_number ?: 'Belum bernomor' }}</small></td><td class="center">{{ $transaction->transaction_date?->translatedFormat('d F Y') }}</td><td>{{ $honor->name }}</td><td>{{ $honor->position }}</td><td class="number">{{ number_format((float) $honor->honor_months, 2, ',', '.') }}</td><td class="number">Rp {{ number_format((float) $honor->rate_per_unit, 0, ',', '.') }}</td><td class="number">Rp {{ number_format((float) $honor->gross_amount, 0, ',', '.') }}</td><td class="number">Rp {{ number_format((float) $honor->tax_amount, 0, ',', '.') }}</td><td class="number">Rp {{ number_format((float) $honor->net_amount, 0, ',', '.') }}</td><td class="signature">{{ $index + 1 }}. __________________</td></tr>
            @empty
                <tr><td colspan="11" class="center">Belum ada data pembayaran honor pada periode ini.</td></tr>
            @endforelse
        </tbody>
        <tfoot><tr><td colspan="7" class="number">TOTAL</td><td class="number">Rp {{ number_format($summary['gross'], 0, ',', '.') }}</td><td class="number">Rp {{ number_format($summary['pph21'], 0, ',', '.') }}</td><td class="number">Rp {{ number_format($summary['net'], 0, ',', '.') }}</td><td></td></tr></tfoot>
    </table>
</body>
</html>
