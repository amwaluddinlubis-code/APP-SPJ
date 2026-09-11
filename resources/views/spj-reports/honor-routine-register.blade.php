<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Daftar Penerimaan Honor</title>
    <style>
        @page { margin: 18px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 8.5px; }
        h1, p { margin: 0; text-align: center; }
        h1 { color: #17365D; font-size: 15px; }
        .subtitle { margin-top: 3px; }
        .note { margin-top: 7px; font-size: 7.5px; text-align: left; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; page-break-inside: avoid; }
        th, td { border: 1px solid #7f8c8d; padding: 4px 3px; vertical-align: middle; }
        th { background: #DDEBF7; color: #17365D; text-align: center; }
        .number { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .signature { min-width: 82px; height: 32px; vertical-align: top; }
        tfoot td { font-weight: bold; background: #FFF8E5; }
        small { font-size: 7px; }
    </style>
</head>

<body>
    <h1>DAFTAR PENERIMAAN HONOR RUTIN</h1>
    <p class="subtitle">{{ $school->name }} · Tahun Anggaran {{ $year->year }}</p>
    <p class="note">
        Honor dengan penerima, jenis/jabatan, tarif, dan nilai bruto yang sama digabung sebagai honor rutin lintas Paket SPJ.
        Nomor bukti dan nomor Paket SPJ sumber tetap dicantumkan untuk audit/penelusuran.
    </p>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Penerima</th>
                <th>Jabatan/Jenis Honor</th>
                <th>Periode</th>
                <th>Bulan/Kali</th>
                <th>Tarif</th>
                <th>Bruto</th>
                <th>PPh 21</th>
                <th>Dibayarkan</th>
                <th>Referensi Paket SPJ</th>
                <th>Tanda Tangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $index => $row)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['position'] }}</td>
                    <td>{{ $row['period'] ?: '-' }}</td>
                    <td class="number">{{ number_format((float) $row['honor_units'], 2, ',', '.') }}</td>
                    <td class="number">Rp {{ number_format((float) $row['rate_per_unit'], 0, ',', '.') }}</td>
                    <td class="number">Rp {{ number_format((float) $row['gross'], 0, ',', '.') }}</td>
                    <td class="number">Rp {{ number_format((float) $row['tax'], 0, ',', '.') }}</td>
                    <td class="number">Rp {{ number_format((float) $row['net'], 0, ',', '.') }}</td>
                    <td><small>{{ $row['package_references'] ?: '-' }}</small></td>
                    <td class="signature">{{ $index + 1 }}. __________________</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="center">Belum ada data pembayaran honor pada periode ini.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" class="number">TOTAL</td>
                <td class="number">Rp {{ number_format($summary['gross'], 0, ',', '.') }}</td>
                <td class="number">Rp {{ number_format($summary['tax'], 0, ',', '.') }}</td>
                <td class="number">Rp {{ number_format($summary['net'], 0, ',', '.') }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>
</body>

</html>
