<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $report['label'] }} - {{ $summary['period_label'] }}</title>
    @include('periodic-reports.partials.document-styles')
</head>
<body>
    <div class="print-toolbar">
        <div>
            <strong>{{ $report['label'] }}</strong>
            <span>· {{ $summary['period_label'] }}</span>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="{{ route('spj.periodic-reports.index', ['paket_laporan' => $summary['scope'], 'periode_laporan' => $summary['period']]) }}">Kembali</a>
            <a href="{{ route('spj.periodic-reports.pdf', ['scope' => $summary['scope'], 'report' => $report['key'], 'periode_laporan' => $summary['period']]) }}" target="_blank" rel="noopener">PDF</a>
            <button type="button" class="primary" onclick="window.print()">Cetak</button>
        </div>
    </div>

    @include('periodic-reports.partials.document')
</body>
</html>
