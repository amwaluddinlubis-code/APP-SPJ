<style>
    @page {
        size: A4 {{ $orientation }};
        margin: 12mm;
    }

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        background: #eef1f4;
        color: #111827;
        font-family: DejaVu Sans, Arial, sans-serif;
        font-size: 10px;
        line-height: 1.35;
    }

    .print-toolbar {
        position: sticky;
        top: 0;
        z-index: 10;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
        justify-content: space-between;
        padding: 10px 14px;
        border-bottom: 1px solid #d1d5db;
        background: #ffffff;
    }

    .print-toolbar a,
    .print-toolbar button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 34px;
        padding: 7px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 7px;
        background: #ffffff;
        color: #0f172a;
        font: inherit;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
    }

    .print-toolbar .primary {
        border-color: #1d4ed8;
        background: #1d4ed8;
        color: #ffffff;
    }

    .document-sheet {
        width: 100%;
        max-width: {{ $orientation === 'landscape' ? '273mm' : '186mm' }};
        min-height: {{ $orientation === 'landscape' ? '186mm' : '273mm' }};
        margin: 14px auto;
        padding: 10mm;
        background: #ffffff;
        box-shadow: 0 1px 8px rgba(15, 23, 42, .12);
    }

    .doc-header {
        border-bottom: 2px solid #111827;
        padding-bottom: 8px;
        text-align: center;
    }

    .doc-header .school {
        font-size: 14px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .doc-header .address {
        margin-top: 2px;
        font-size: 9px;
    }

    .doc-title {
        margin: 14px 0 3px;
        font-size: 15px;
        font-weight: 700;
        text-align: center;
        text-transform: uppercase;
    }

    .doc-subtitle {
        margin-bottom: 12px;
        text-align: center;
        font-size: 10px;
    }

    .meta-table,
    .report-table,
    .summary-table,
    .signature-table {
        width: 100%;
        border-collapse: collapse;
    }

    .meta-table td {
        padding: 2px 4px;
        vertical-align: top;
    }

    .meta-table td:first-child {
        width: 120px;
        font-weight: 700;
    }

    .summary-table {
        margin: 12px 0;
    }

    .summary-table th,
    .summary-table td {
        border: 1px solid #6b7280;
        padding: 6px 7px;
    }

    .summary-table th {
        background: #f3f4f6;
        text-align: left;
    }

    .summary-table td.money {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .report-table {
        margin-top: 12px;
        table-layout: auto;
    }

    .report-table th,
    .report-table td {
        border: 1px solid #6b7280;
        padding: 4px 5px;
        vertical-align: top;
    }

    .report-table th {
        background: #e5e7eb;
        text-align: center;
        font-size: 8.5px;
    }

    .report-table td {
        font-size: 8.5px;
    }

    .report-table .money,
    .report-table .integer {
        text-align: right;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }

    .report-table .empty-row {
        padding: 16px;
        text-align: center;
        color: #6b7280;
    }

    .statement {
        margin: 14px 0;
        font-size: 11px;
        line-height: 1.6;
        text-align: justify;
    }

    .statement p {
        margin: 0 0 9px;
    }

    .signature-table {
        margin-top: 26px;
        page-break-inside: avoid;
    }

    .signature-table td {
        width: 50%;
        padding: 0 14px;
        text-align: center;
        vertical-align: top;
    }

    .signature-space {
        height: 54px;
    }

    .signature-name {
        font-weight: 700;
        text-decoration: underline;
    }

    .note {
        margin-top: 14px;
        padding-top: 7px;
        border-top: 1px solid #d1d5db;
        color: #4b5563;
        font-size: 8px;
    }

    .page-break-avoid {
        page-break-inside: avoid;
    }

    @media print {
        body {
            background: #ffffff;
        }

        .print-toolbar {
            display: none !important;
        }

        .document-sheet {
            max-width: none;
            min-height: 0;
            margin: 0;
            padding: 0;
            box-shadow: none;
        }

        thead {
            display: table-header-group;
        }

        tr {
            page-break-inside: avoid;
        }
    }
</style>
