<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Services\SpjQuarterAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AuditSpjQuarter extends Command
{
    protected $signature = 'spj:audit-quarter
        {npsn : NPSN sekolah yang database tenant-nya akan diaudit}
        {--quarter=1 : Triwulan 1 sampai 4}
        {--year= : Tahun anggaran; bila kosong auditor memilih tahun terbaru yang memiliki transaksi}
        {--fund-source= : ID, kode, atau nama sumber dana}
        {--json : Tampilkan hasil lengkap dalam JSON}
        {--output= : Simpan snapshot JSON audit ke file tanpa mengubah database tenant}
        {--limit=30 : Maksimum anomaly yang ditampilkan pada output manusia}';

    protected $description = 'Audit read-only database SPJ sekolah per triwulan tanpa memodifikasi data tenant.';

    public function handle(SpjQuarterAuditService $auditor): int
    {
        $npsn = trim((string) $this->argument('npsn'));
        $quarter = (int) $this->option('quarter');
        $yearOption = trim((string) ($this->option('year') ?? ''));
        $year = $yearOption === '' ? null : (int) $yearOption;
        $fundSourceOption = trim((string) ($this->option('fund-source') ?? ''));
        $fundSource = $fundSourceOption === '' ? null : $fundSourceOption;

        if ($quarter < 1 || $quarter > 4) {
            $this->error('Triwulan harus bernilai 1 sampai 4.');

            return self::FAILURE;
        }
        if ($year !== null && ($year < 2000 || $year > 2100)) {
            $this->error('Tahun anggaran tidak valid.');

            return self::FAILURE;
        }

        $school = School::query()->with('databaseRecord')->where('npsn', $npsn)->first();
        if (! $school) {
            $this->error('Sekolah dengan NPSN '.$npsn.' tidak ditemukan pada database utama.');

            return self::FAILURE;
        }

        $databasePath = $this->resolveExistingDatabasePath($school);
        if ($databasePath === null) {
            $this->error('File database tenant untuk '.$school->name.' ('.$npsn.') tidak ditemukan. Auditor tidak membuat database baru.');

            return self::FAILURE;
        }

        try {
            $report = $auditor->audit($databasePath, $quarter, $year, $fundSource);
        } catch (\Throwable $exception) {
            $this->error('Audit gagal: '.$exception->getMessage());

            return self::FAILURE;
        }

        $report['school'] = [
            'id' => $school->id,
            'npsn' => $school->npsn,
            'name' => $school->name,
        ];

        $encodedReport = (string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $outputOption = trim((string) ($this->option('output') ?? ''));
        $snapshotPath = null;
        if ($outputOption !== '') {
            try {
                $snapshotPath = $this->writeSnapshot($outputOption, $encodedReport);
            } catch (\Throwable $exception) {
                $this->error('Snapshot audit tidak dapat disimpan: '.$exception->getMessage());

                return self::FAILURE;
            }
        }

        if ((bool) $this->option('json')) {
            $this->line($encodedReport);

            return $report['integrity']['status'] === 'PASS' && $report['query_only'] ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->info('SPJ QUARTER AUDIT — READ ONLY');
        $this->line('Sekolah        : '.$school->name.' ('.$school->npsn.')');
        $this->line('Database       : '.$databasePath);
        if ($snapshotPath !== null) {
            $this->line('Snapshot JSON  : '.$snapshotPath);
        }
        $this->line('Mode SQLite    : '.($report['query_only'] ? 'query_only=ON' : 'query_only=OFF'));
        $this->line('Tahun/Triwulan : '.$report['context']['year'].' / TW'.$report['context']['quarter'].' ('.$report['context']['date_from'].' s.d. '.$report['context']['date_to'].')');
        $this->line('Sumber Dana    : '.($report['context']['fund_source_id'] ?? 'SEMUA'));
        $this->line('Integrity      : '.$report['integrity']['status'].'; FK violations='.$report['integrity']['foreign_key_violations']);
        $this->line('Migration      : '.($report['integrity']['migration_count'] ?? '-').' / latest '.($report['integrity']['latest_migration'] ?? '-'));
        $this->newLine();

        $summary = $report['summary'];
        $this->table(
            ['Transaksi', 'Paket', 'Kategori kosong', 'Item SPJ kosong', 'Mismatch finansial', 'Critical', 'Warning', 'Info'],
            [[
                $summary['transactions'], $summary['packages'], $summary['unassigned_category'],
                $summary['blank_item_descriptions'], $summary['financial_mismatches'],
                $summary['critical'], $summary['warnings'], $summary['info'],
            ]]
        );

        $this->info('Coverage enam kategori');
        $this->table(
            ['Kategori', 'Tx', 'Paket', 'NONE', 'DRAFT', 'READY', 'NUMBERED', 'FINAL', 'CANCELLED', 'Kandidat bersih'],
            array_map(fn (array $row): array => [
                $row['category'], $row['transactions'], $row['packages'], $row['none'], $row['draft'],
                $row['ready'], $row['numbered'], $row['final'], $row['cancelled'], $row['candidate_count'],
            ], $report['categories'])
        );

        $this->info('Kandidat P0-01 E2E');
        $candidateRows = [];
        foreach (SpjQuarterAuditService::CATEGORIES as $category) {
            $candidate = $report['candidates'][$category] ?? null;
            $candidateRows[] = $candidate
                ? [
                    $category,
                    $candidate['transaction_id'],
                    $candidate['no_bukti'],
                    $candidate['transaction_date'],
                    $candidate['package_status'],
                    $candidate['issues'] === [] ? 'BERSIH' : implode(', ', $candidate['issues']),
                ]
                : [$category, '-', '-', '-', '-', 'TIDAK ADA DATA'];
        }
        $this->table(['Kategori', 'Transaction', 'No Bukti', 'Tanggal', 'Status Paket', 'Issue kandidat'], $candidateRows);

        $limit = max(1, min(200, (int) $this->option('limit')));
        if ($report['anomalies'] !== []) {
            $this->warn('Anomaly (maks. '.$limit.' dari '.count($report['anomalies']).')');
            $this->table(
                ['Severity', 'Code', 'Transaction', 'Package', 'Keterangan'],
                array_map(fn (array $row): array => [
                    $row['severity'], $row['code'], $row['transaction_id'] ?? '-', $row['package_id'] ?? '-', $row['message'],
                ], array_slice($report['anomalies'], 0, $limit))
            );
        } else {
            $this->info('Tidak ada anomaly yang terdeteksi oleh auditor.');
        }

        $this->newLine();
        $this->line('READ-ONLY GUARANTEE: command tidak memanggil SchoolDatabaseManager::activate/provision/migrate dan koneksi SQLite dipaksa PRAGMA query_only=ON.');
        $this->line('Audit data anomaly tidak mengubah database. Perbaikan data harus dilakukan melalui workflow aplikasi, bukan command ini.');

        if ($report['integrity']['status'] !== 'PASS' || ! $report['query_only']) {
            $this->error('AUDIT RESULT: FAIL — integritas/query-only tidak memenuhi syarat.');

            return self::FAILURE;
        }

        if ($summary['critical'] > 0 || $summary['warnings'] > 0) {
            $this->warn('AUDIT RESULT: REVIEW — database terbaca aman, tetapi ada anomaly yang perlu ditinjau sebelum P0-01 E2E.');
        } else {
            $this->info('AUDIT RESULT: CLEAN — tidak ada anomaly pada pemeriksaan yang tersedia.');
        }

        return self::SUCCESS;
    }

    private function writeSnapshot(string $path, string $content): string
    {
        $path = $this->absolutePath($path);
        File::ensureDirectoryExists(dirname($path));
        if (File::put($path, $content.PHP_EOL) === false) {
            throw new \RuntimeException('Gagal menulis file '.$path);
        }

        return $path;
    }

    private function resolveExistingDatabasePath(School $school): ?string
    {
        // Mirror SchoolDatabaseManager::activate() without writing metadata:
        // when the canonical managed path exists, runtime would prefer it over a stale recorded path.
        $managedPath = rtrim((string) config('spj.data_path'), '/\\')
            .DIRECTORY_SEPARATOR.'school-databases'
            .DIRECTORY_SEPARATOR.preg_replace('/[^A-Za-z0-9_-]/', '_', $school->npsn)
            .DIRECTORY_SEPARATOR.'spj.sqlite';

        $paths = [$managedPath];
        if (filled($school->databaseRecord?->database_path)) {
            $paths[] = (string) $school->databaseRecord->database_path;
        }

        foreach (array_unique($paths) as $path) {
            $candidate = $this->absolutePath($path);
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function absolutePath(string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('~^[A-Za-z]:[\\\\/]~', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }
}
