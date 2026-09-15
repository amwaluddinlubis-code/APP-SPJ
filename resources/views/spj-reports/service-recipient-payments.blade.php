<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Daftar Pembayaran Jasa Lainnya</title>
    <style>
        @page { margin: 18px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 8.5px; }
        h1, p { margin: 0; text-align: center; }
        h1 { color: #17365D; font-size: 15px; }
        .subtitle { margin-top: 3px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #7f8c8d; padding: 4px 3px; vertical-align: middle; }
        th { background: #DDEBF7; color: #17365D; text-align: center; }
        .number { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .signature { min-width: 82px; height: 32px; vertical-align: top; }
        tfoot td { font-weight: bold; background: #FFF8E5; }
    </style>
</head>
<body>
    <h1>DAFTAR PEMBAYARAN JASA LAINNYA</h1>
    <p class="subtitle">{{ $school->name }} · Tahun Anggaran {{ $year->year }}</p>
    <table>
        <thead><tr><th>No</th><th>No Bukti / SPJ</th><th>Tanggal</th><th>Penerima Jasa</th><th>Jenis Jasa</th><th>Uraian</th><th>Jumlah</th><th>Hari</th><th>Bruto</th><th>Pajak</th><th>Dibayarkan</th><th>Tanda Tangan</th></tr></thead>
        <tbody>
            @forelse($recipients as $index => $recipient)
                @php($transaction = $recipient->transaction)
                <tr><td class="center">{{ $index + 1 }}</td><td>{{ $transaction->no_bukti }}<br><small>{{ $transaction->spjPackage?->document_number ?: 'Belum bernomor' }}</small></td><td class="center">{{ $transaction->transaction_date?->translatedFormat('d F Y') }}</td><td>{{ $recipient->name }}</td><td>{{ $recipient->service_type }}</td><td>{{ $recipient->service_description }}</td><td class="number">{{ number_format((float) $recipient->quantity, 0, ',', '.') }} {{ $recipient->unit }}</td><td class="number">{{ number_format((float) $recipient->rental_days, 0, ',', '.') }}</td><td class="number">Rp {{ number_format((float) $recipient->amount, 0, ',', '.') }}</td><td class="number">Rp {{ number_format((float) $recipient->tax_amount, 0, ',', '.') }}</td><td class="number">Rp {{ number_format((float) $recipient->net_amount, 0, ',', '.') }}</td><td class="signature">{{ $index + 1 }}. __________________</td></tr>
            @empty
                <tr><td colspan="12" class="center">Belum ada data penerima pembayaran jasa lainnya pada periode ini.</td></tr>
            @endforelse
        </tbody>
        <tfoot><tr><td colspan="8" class="number">TOTAL</td><td class="number">Rp {{ number_format($summary['gross'], 0, ',', '.') }}</td><td class="number">Rp {{ number_format($summary['tax'], 0, ',', '.') }}</td><td class="number">Rp {{ number_format($summary['net'], 0, ',', '.') }}</td><td></td></tr></tfoot>
    </table>
</body>
</html>
