<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReconcileArkasData extends Command
{
    protected $signature = 'arkas:reconcile
        {--json : Tampilkan laporan dalam JSON}
        {--year= : Batasi audit pada fiscal_year_id tertentu}';

    protected $description = 'Audit read-only integritas dan duplikasi data sinkronisasi ARKAS.';

    /** @var array<string, array{table:string, columns:array<int,string>, label:string}> */
    private const UNIQUE_SCOPES = [
        'fiscal_years' => ['table' => 'fiscal_years', 'columns' => ['year', 'fund_source'], 'label' => 'Konteks tahun anggaran'],
        'account_references' => ['table' => 'account_references', 'columns' => ['fiscal_year_id', 'account_code'], 'label' => 'Referensi rekening'],
        'activity_references' => ['table' => 'activity_references', 'columns' => ['fiscal_year_id', 'activity_code'], 'label' => 'Referensi kegiatan'],
        'arkas_periods' => ['table' => 'arkas_periods', 'columns' => ['source_period_id'], 'label' => 'Referensi periode'],
        'rkas' => ['table' => 'arkas_rkas_items', 'columns' => ['fiscal_year_id', 'source_rapbs_id'], 'label' => 'RKAS'],
        'bku' => ['table' => 'arkas_bku_rows', 'columns' => ['fiscal_year_id', 'source_kas_id'], 'label' => 'BKU'],
        'rkas_periods' => ['table' => 'arkas_rkas_periods', 'columns' => ['fiscal_year_id', 'fund_source_id', 'source_rapbs_id', 'source_period_id'], 'label' => 'Periode RKAS'],
        'transactions_no_bukti' => ['table' => 'transactions', 'columns' => ['fiscal_year_id', 'no_bukti'], 'label' => 'Nomor bukti transaksi'],
        'transactions_source_key' => ['table' => 'transactions', 'columns' => ['fiscal_year_id', 'source_key'], 'label' => 'Identitas sumber transaksi'],
        'transaction_items' => ['table' => 'transaction_items', 'columns' => ['transaction_id', 'source_item_id'], 'label' => 'Item transaksi'],
        'employees' => ['table' => 'employees', 'columns' => ['source_type', 'source_key'], 'label' => 'Pegawai sumber'],
        'business_partners' => ['table' => 'business_partners', 'columns' => ['name', 'npwp'], 'label' => 'Rekanan'],
        'import_rows' => ['table' => 'arkas_import_rows', 'columns' => ['profile_id', 'fiscal_year_id', 'source_key'], 'label' => 'Baris importer'],
    ];

    public function handle(): int
    {
        $db = DB::connection('school');
        $yearId = trim((string) ($this->option('year') ?? ''));
        $report = ['status' => 'PASS', 'scope' => $yearId === '' ? 'all' : $yearId, 'duplicates' => [], 'orphans' => 0];

        foreach (self::UNIQUE_SCOPES as $name => $scope) {
            if (! Schema::connection('school')->hasTable($scope['table'])) {
                continue;
            }

            $query = $db->table($scope['table']);
            if ($yearId !== '' && in_array('fiscal_year_id', $scope['columns'], true)) {
                $query->where('fiscal_year_id', (int) $yearId);
            }
            $groups = $query->select($scope['columns'])
                ->selectRaw('COUNT(*) AS duplicate_count')
                ->groupBy($scope['columns'])
                ->having('duplicate_count', '>', 1)
                ->get();

            if ($groups->isNotEmpty()) {
                $report['status'] = 'FAIL';
                $report['duplicates'][$name] = ['label' => $scope['label'], 'groups' => $groups->map(fn (object $row): array => (array) $row)->values()->all()];
            }
        }

        if (Schema::connection('school')->hasTable('transaction_items') && Schema::connection('school')->hasTable('transactions')) {
            $report['orphans'] = $db->table('transaction_items as ti')->leftJoin('transactions as t', 't.id', '=', 'ti.transaction_id')->whereNull('t.id')->count();
            if ($report['orphans'] > 0) {
                $report['status'] = 'FAIL';
            }
        }

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->info('REKONSILIASI DATA ARKAS (READ ONLY)');
            $this->line('Status: '.$report['status']);
            $this->line('Grup duplikat: '.array_sum(array_map(fn (array $item): int => count($item['groups']), $report['duplicates'])));
            $this->line('Item transaksi yatim: '.$report['orphans']);
            $report['status'] === 'PASS'
                ? $this->info('Tidak ditemukan data sinkronisasi ganda atau item transaksi yatim.')
                : $this->error('Ditemukan anomali. Tinjau sebelum sinkronisasi ulang.');
        }

        return $report['status'] === 'PASS' ? self::SUCCESS : self::FAILURE;
    }
}
