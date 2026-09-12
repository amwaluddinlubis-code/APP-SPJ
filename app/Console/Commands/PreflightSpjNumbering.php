<?php

namespace App\Console\Commands;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Services\SpjNumberingOrderService;
use App\Services\SpjPackageValidationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class PreflightSpjNumbering extends Command
{
    protected $signature = 'spj:preflight-numbering
        {npsn : NPSN sekolah yang database tenant-nya akan diperiksa}
        {--quarter=1 : Triwulan 1 sampai 4}
        {--year= : Tahun anggaran; bila kosong menggunakan tahun terbaru yang memiliki transaksi}
        {--fund-source= : ID, kode, atau nama sumber dana; wajib bila tahun memiliki lebih dari satu sumber dana}
        {--document-type=SPJ : Jenis dokumen untuk preview urutan canonical}
        {--limit=100 : Maksimum baris urutan/blocker yang ditampilkan}
        {--json : Tampilkan report lengkap dalam JSON}';

    protected $description = 'Preflight read-only penomoran SPJ: validasi paket dan preview urutan canonical tanpa menerbitkan nomor.';

    public function handle(SpjPackageValidationService $validator, SpjNumberingOrderService $order): int
    {
        $npsn = trim((string) $this->argument('npsn'));
        $quarter = (int) $this->option('quarter');
        $yearOption = trim((string) ($this->option('year') ?? ''));
        $requestedYear = $yearOption === '' ? null : (int) $yearOption;
        $fundSourceOption = trim((string) ($this->option('fund-source') ?? ''));
        $documentType = strtoupper(trim((string) ($this->option('document-type') ?: 'SPJ')));
        $limit = max(1, min(500, (int) $this->option('limit')));

        if ($quarter < 1 || $quarter > 4) {
            $this->error('Triwulan harus bernilai 1 sampai 4.');

            return self::FAILURE;
        }
        if ($requestedYear !== null && ($requestedYear < 2000 || $requestedYear > 2100)) {
            $this->error('Tahun anggaran tidak valid.');

            return self::FAILURE;
        }
        if ($documentType === '') {
            $this->error('Jenis dokumen tidak boleh kosong.');

            return self::FAILURE;
        }

        $school = School::query()->with('databaseRecord')->where('npsn', $npsn)->first();
        if (! $school) {
            $this->error('Sekolah dengan NPSN '.$npsn.' tidak ditemukan pada database utama.');

            return self::FAILURE;
        }

        $databasePath = $this->resolveExistingDatabasePath($school);
        if ($databasePath === null) {
            $this->error('File database tenant untuk '.$school->name.' ('.$npsn.') tidak ditemukan. Preflight tidak membuat database baru.');

            return self::FAILURE;
        }

        $hashBefore = hash_file('sha256', $databasePath);
        if ($hashBefore === false) {
            $this->error('Hash awal database tenant tidak dapat dibaca.');

            return self::FAILURE;
        }

        $originalSchoolConfig = config('database.connections.school');
        $report = null;
        $failureMessage = null;

        try {
            config()->set('database.connections.school.database', $databasePath);
            config()->set('database.connections.school.journal_mode', null);
            config()->set('database.connections.school.synchronous', null);
            DB::purge('school');

            $connection = DB::connection('school');
            $connection->statement('PRAGMA query_only = ON');
            $connection->statement('PRAGMA foreign_keys = ON');
            $queryOnly = (int) ($connection->selectOne('PRAGMA query_only')->query_only ?? 0) === 1;
            if (! $queryOnly) {
                throw new RuntimeException('Koneksi preflight gagal masuk mode SQLite query_only.');
            }

            $requiredTables = ['fiscal_years', 'fund_sources', 'transactions', 'transaction_items', 'spj_packages'];
            $tableNames = collect($connection->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"))
                ->pluck('name')
                ->map('strval');
            foreach ($requiredTables as $requiredTable) {
                if (! $tableNames->contains($requiredTable)) {
                    throw new RuntimeException('Schema tenant belum lengkap. Tabel wajib tidak ditemukan: '.$requiredTable);
                }
            }

            $year = $requestedYear ?? $this->latestYearWithTransactions();
            if ($year === null) {
                throw new RuntimeException('Tidak ada tahun anggaran yang memiliki transaksi pada database sekolah.');
            }

            $fundSourceId = $this->resolveFundSourceId($year, $fundSourceOption);
            $fiscalYear = FiscalYear::query()
                ->where('year', $year)
                ->where('fund_source_id', $fundSourceId)
                ->first();
            if (! $fiscalYear) {
                throw new RuntimeException("Fiscal year {$year} untuk fund source {$fundSourceId} tidak ditemukan.");
            }

            [$dateFrom, $dateTo] = $this->quarterRange($year, $quarter);
            $transactionScope = fn ($query) => $query
                ->where('fiscal_year_id', $fiscalYear->id)
                ->where('fund_source_id', $fundSourceId)
                ->whereBetween('transaction_date', [$dateFrom, $dateTo]);

            $transactions = Transaction::query()
                ->with(['spjPackage', 'items'])
                ->where($transactionScope)
                ->has('items')
                ->get();

            $notReady = $transactions
                ->filter(fn (Transaction $transaction): bool => ! $transaction->spjPackage || $transaction->spjPackage->status === 'DRAFT')
                ->map(fn (Transaction $transaction): array => [
                    'transaction_id' => $transaction->id,
                    'no_bukti' => $transaction->no_bukti,
                    'transaction_date' => optional($transaction->transaction_date)->format('Y-m-d'),
                    'package_id' => $transaction->spjPackage?->id,
                    'package_status' => $transaction->spjPackage?->status ?? 'NONE',
                    'message' => $transaction->spjPackage ? 'Paket masih DRAFT.' : 'Transaksi belum memiliki Paket SPJ.',
                ])
                ->values();

            // Turunkan scope paket langsung dari transaksi yang sudah dibatasi oleh
            // year + fund source + quarter. Ini menghindari scope kedua yang dapat
            // berbeda dari daftar transaksi yang sedang dipreflight.
            $readyPackageIds = $transactions
                ->map(fn (Transaction $transaction): ?int => in_array($transaction->spjPackage?->status, ['READY', 'NUMBERED'], true)
                    ? (int) $transaction->spjPackage->id
                    : null)
                ->filter()
                ->values();

            $packages = SpjPackage::query()
                ->with([
                    'documents',
                    'transaction.items',
                    'transaction.goods',
                    'transaction.goodsReceipts',
                    'transaction.workOrder',
                    'transaction.honors',
                    'transaction.travels',
                    'transaction.payments',
                    'transaction.workers',
                    'transaction.participants',
                    'transaction.serviceRecipients',
                    'transaction.spjPackage',
                ])
                ->whereKey($readyPackageIds->all())
                ->get();

            $scopeMismatch = abs($readyPackageIds->count() - $packages->count());

            $invalidPackages = $packages
                ->map(function (SpjPackage $package) use ($validator): ?array {
                    $issues = $validator->validateForNumbering($package);
                    if ($issues === []) {
                        return null;
                    }

                    return [
                        'package_id' => $package->id,
                        'transaction_id' => $package->transaction->id,
                        'no_bukti' => $package->transaction->no_bukti,
                        'status' => $package->status,
                        'issues' => $issues,
                    ];
                })
                ->filter()
                ->values();

            $orderedPackages = $order->orderedPackagesForDocumentType($packages, $documentType);
            $ordering = $orderedPackages->values()->map(function (SpjPackage $package, int $index) use ($order, $documentType): array {
                return [
                    'position' => $index + 1,
                    'package_id' => $package->id,
                    'transaction_id' => $package->transaction->id,
                    'no_bukti' => $package->transaction->no_bukti,
                    'transaction_date' => optional($package->transaction->transaction_date)->format('Y-m-d'),
                    'event_date' => optional($order->documentEventDate($package, $documentType))->format('Y-m-d'),
                    'source_order_key' => $order->sourceOrderKey($package->transaction),
                    'status' => $package->status,
                ];
            });

            $report = [
                'read_only' => true,
                'query_only' => true,
                'school' => [
                    'id' => $school->id,
                    'npsn' => $school->npsn,
                    'name' => $school->name,
                ],
                'database_path' => $databasePath,
                'context' => [
                    'year' => $year,
                    'quarter' => $quarter,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'fund_source_id' => $fundSourceId,
                    'document_type' => $documentType,
                ],
                'summary' => [
                    'transactions_with_items' => $transactions->count(),
                    'packages_ready_or_numbered' => $packages->count(),
                    'not_ready' => $notReady->count(),
                    'invalid_packages' => $invalidPackages->count(),
                    'ordered_packages' => $ordering->count(),
                    'scope_mismatch' => $scopeMismatch,
                ],
                'not_ready' => $notReady->all(),
                'invalid_packages' => $invalidPackages->all(),
                'ordering' => $ordering->all(),
            ];
        } catch (Throwable $exception) {
            $failureMessage = $exception->getMessage();
        } finally {
            DB::purge('school');
            config()->set('database.connections.school', $originalSchoolConfig);
        }

        clearstatcache(true, $databasePath);
        $hashAfter = hash_file('sha256', $databasePath);
        $hashUnchanged = $hashAfter !== false && hash_equals($hashBefore, $hashAfter);

        if ($failureMessage !== null) {
            $this->error('Preflight gagal: '.$failureMessage);
            $this->line('DATABASE HASH UNCHANGED: '.($hashUnchanged ? 'YES' : 'NO'));

            return self::FAILURE;
        }
        if ($report === null) {
            $this->error('Preflight gagal tanpa report.');

            return self::FAILURE;
        }

        $report['database_hash_unchanged'] = $hashUnchanged;
        if (! $hashUnchanged) {
            $this->error('READ-ONLY GUARANTEE GAGAL: hash database berubah selama preflight.');

            return self::FAILURE;
        }

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $this->isClean($report) ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->info('SPJ NUMBERING PREFLIGHT — READ ONLY');
        $this->line('Sekolah        : '.$school->name.' ('.$school->npsn.')');
        $this->line('Database       : '.$databasePath);
        $this->line('Mode SQLite    : query_only=ON');
        $this->line('Hash unchanged : YES');
        $this->line('Tahun/Triwulan : '.$report['context']['year'].' / TW'.$report['context']['quarter'].' ('.$report['context']['date_from'].' s.d. '.$report['context']['date_to'].')');
        $this->line('Sumber Dana    : '.$report['context']['fund_source_id']);
        $this->line('Jenis Dokumen  : '.$documentType);
        $this->newLine();

        $summary = $report['summary'];
        $this->table(
            ['Tx ber-item', 'Paket READY/NUMBERED', 'Belum READY', 'Paket invalid', 'Urutan', 'Scope mismatch'],
            [[
                $summary['transactions_with_items'],
                $summary['packages_ready_or_numbered'],
                $summary['not_ready'],
                $summary['invalid_packages'],
                $summary['ordered_packages'],
                $summary['scope_mismatch'],
            ]]
        );

        if ($report['not_ready'] !== []) {
            $this->warn('Transaksi belum READY (maks. '.$limit.')');
            $this->table(
                ['Transaction', 'No Bukti', 'Tanggal', 'Paket', 'Status', 'Keterangan'],
                collect($report['not_ready'])->take($limit)->map(fn (array $row): array => [
                    $row['transaction_id'], $row['no_bukti'], $row['transaction_date'], $row['package_id'] ?? '-', $row['package_status'], $row['message'],
                ])->all()
            );
        }

        if ($report['invalid_packages'] !== []) {
            $this->warn('Blocker validasi penomoran (maks. '.$limit.')');
            $this->table(
                ['Paket', 'Transaction', 'No Bukti', 'Status', 'Issue'],
                collect($report['invalid_packages'])->take($limit)->map(fn (array $row): array => [
                    $row['package_id'],
                    $row['transaction_id'],
                    $row['no_bukti'],
                    $row['status'],
                    collect($row['issues'])->map(fn (array $issue): string => $issue['label'].': '.$issue['message'])->implode(' | '),
                ])->all()
            );
        }

        $this->info('Preview urutan canonical '.$documentType.' (maks. '.$limit.')');
        $this->table(
            ['#', 'Paket', 'Transaction', 'No Bukti', 'Tanggal Tx', 'Event Date', 'Status'],
            collect($report['ordering'])->take($limit)->map(fn (array $row): array => [
                $row['position'], $row['package_id'], $row['transaction_id'], $row['no_bukti'], $row['transaction_date'], $row['event_date'], $row['status'],
            ])->all()
        );

        $this->newLine();
        $this->line('READ-ONLY GUARANTEE: tidak ada activate/provision/migrate, QuarterNumberingRun, format/sequence, atau nomor dokumen yang dibuat.');
        $this->line('Database tenant dipaksa query_only=ON dan SHA-256 diverifikasi tidak berubah.');

        if (! $this->isClean($report)) {
            $this->error('PREFLIGHT RESULT: BLOCKED — perbaiki transaksi/paket yang ditampilkan sebelum penomoran nyata.');

            return self::FAILURE;
        }

        $this->info('PREFLIGHT RESULT: PASS — paket valid dan urutan canonical siap ditinjau. Belum ada nomor yang diterbitkan.');

        return self::SUCCESS;
    }

    private function latestYearWithTransactions(): ?int
    {
        $year = DB::connection('school')->table('transactions as t')
            ->join('fiscal_years as fy', 'fy.id', '=', 't.fiscal_year_id')
            ->max('fy.year');

        return $year === null ? null : (int) $year;
    }

    private function resolveFundSourceId(int $year, string $fundSource): int
    {
        if ($fundSource !== '') {
            if (ctype_digit($fundSource)) {
                $id = (int) $fundSource;
                if (! FundSource::query()->whereKey($id)->exists()) {
                    throw new RuntimeException('Fund source ID '.$id.' tidak ditemukan.');
                }

                return $id;
            }

            $resolved = FundSource::query()
                ->whereRaw('LOWER(code) = ?', [mb_strtolower($fundSource)])
                ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($fundSource)])
                ->first();
            if (! $resolved) {
                throw new RuntimeException('Fund source '.$fundSource.' tidak ditemukan.');
            }

            return (int) $resolved->id;
        }

        $ids = FiscalYear::query()
            ->where('year', $year)
            ->whereNotNull('fund_source_id')
            ->pluck('fund_source_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($ids->count() !== 1) {
            throw new RuntimeException('Tahun '.$year.' memiliki '.count($ids).' sumber dana. Gunakan --fund-source untuk memilih scope secara eksplisit.');
        }

        return (int) $ids->first();
    }

    /** @return array{0:string,1:string} */
    private function quarterRange(int $year, int $quarter): array
    {
        $startMonth = (($quarter - 1) * 3) + 1;
        $from = Carbon::create($year, $startMonth, 1)->startOfMonth();
        $to = $from->copy()->addMonths(2)->endOfMonth();

        return [$from->toDateString(), $to->toDateString()];
    }

    /** @param array<string, mixed> $report */
    private function isClean(array $report): bool
    {
        $summary = $report['summary'] ?? [];

        return ($report['query_only'] ?? false) === true
            && ($report['database_hash_unchanged'] ?? false) === true
            && (int) ($summary['not_ready'] ?? 1) === 0
            && (int) ($summary['invalid_packages'] ?? 1) === 0
            && (int) ($summary['scope_mismatch'] ?? 1) === 0
            && (int) ($summary['ordered_packages'] ?? -1) === (int) ($summary['packages_ready_or_numbered'] ?? -2);
    }

    private function resolveExistingDatabasePath(School $school): ?string
    {
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
