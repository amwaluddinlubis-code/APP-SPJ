@php
    $rupiah = fn ($value) => number_format((float) $value, 0, ',', '.');
    $principalName = trim((string) ($profile->principal_name ?? '')) ?: '........................................';
    $principalNip = trim((string) ($profile->principal_nip ?? ''));
    $treasurerName = trim((string) ($profile->treasurer_name ?? '')) ?: '........................................';
    $treasurerNip = trim((string) ($profile->treasurer_nip ?? ''));
@endphp

<article class="document-sheet">
    <header class="doc-header">
        <div class="school">{{ $school->name }}</div>
        <div class="address">
            {{ $school->address ?: '-' }}
            @if($school->district || $school->regency)
                · {{ collect([$school->district, $school->regency, $school->province])->filter()->implode(', ') }}
            @endif
        </div>
        @if($school->npsn)
            <div class="address">NPSN {{ $school->npsn }}</div>
        @endif
    </header>

    <h1 class="doc-title">{{ $report['label'] }}</h1>
    <div class="doc-subtitle">{{ $summary['period_label'] }}</div>

    <table class="meta-table page-break-avoid">
        <tr>
            <td>Tahun Anggaran</td>
            <td>: {{ $year->year }}</td>
            <td>Sumber Dana</td>
            <td>: {{ $fundSource }}</td>
        </tr>
        <tr>
            <td>Periode</td>
            <td>: {{ $summary['date_from'] }} s.d. {{ $summary['date_to'] }}</td>
            <td>Jumlah Transaksi</td>
            <td>: {{ number_format((int) $summary['transaction_count'], 0, ',', '.') }}</td>
        </tr>
    </table>

    @if($presentation === 'statement')
        <div class="statement">
            @foreach($statement as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </div>
    @endif

    <table class="summary-table page-break-avoid">
        <thead>
            <tr>
                <th>Nilai Bruto</th>
                <th>Total Pajak</th>
                <th>Nilai Dibayarkan</th>
                <th>PPN</th>
                <th>PPh 21</th>
                <th>PPh 22</th>
                <th>PPh 23</th>
                <th>PPh 4(2)</th>
                <th>SSPD</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="money">{{ $rupiah($summary['gross']) }}</td>
                <td class="money">{{ $rupiah($summary['tax']) }}</td>
                <td class="money">{{ $rupiah($summary['net']) }}</td>
                <td class="money">{{ $rupiah($summary['ppn']) }}</td>
                <td class="money">{{ $rupiah($summary['pph21']) }}</td>
                <td class="money">{{ $rupiah($summary['pph22']) }}</td>
                <td class="money">{{ $rupiah($summary['pph23']) }}</td>
                <td class="money">{{ $rupiah($summary['pph4']) }}</td>
                <td class="money">{{ $rupiah($summary['sspd']) }}</td>
            </tr>
        </tbody>
    </table>

    @if(count($columns) > 0)
        @if($presentation === 'statement')
            <div style="margin-top:12px;font-weight:700;">Rekap Pendukung</div>
        @endif
        <table class="report-table">
            <thead>
                <tr>
                    @foreach($columns as $column)
                        <th>{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        @foreach($columns as $column)
                            @php
                                $value = $row[$column['key']] ?? null;
                                $type = $column['type'];
                            @endphp
                            <td class="{{ $type }}">
                                @if($type === 'money')
                                    {{ $rupiah($value) }}
                                @elseif($type === 'integer')
                                    {{ number_format((int) $value, 0, ',', '.') }}
                                @else
                                    {{ $value ?: '-' }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ max(1, count($columns)) }}" class="empty-row">Tidak ada transaksi pada periode ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endif

    <table class="signature-table">
        <tr>
            <td>
                Mengetahui,<br>
                Kepala Sekolah
                <div class="signature-space"></div>
                <div class="signature-name">{{ $principalName }}</div>
                @if($principalNip)
                    <div>NIP. {{ $principalNip }}</div>
                @endif
            </td>
            <td>
                Bendahara BOSP
                <div class="signature-space"></div>
                <div class="signature-name">{{ $treasurerName }}</div>
                @if($treasurerNip)
                    <div>NIP. {{ $treasurerNip }}</div>
                @endif
            </td>
        </tr>
    </table>

    <div class="note">
        Dicetak dari APP-SPJ pada {{ $generatedAt->format('d-m-Y H:i') }}. Nilai laporan bersumber dari transaksi pada konteks sekolah, tahun anggaran, dan sumber dana aktif. Dokumen harus dicocokkan dengan bukti fisik/rekening koran dan ketentuan instansi sebelum ditandatangani.
    </div>
</article>
