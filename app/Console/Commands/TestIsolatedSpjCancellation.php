<?php

namespace App\Console\Commands;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\SpjDocument;
use App\Models\SpjPackage;
use App\Services\SpjDocumentLifecycleService;
use App\Services\SpjDocumentNumberService;
use App\Services\SpjNumberingOrderService;
use App\Services\SpjPackageValidationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class TestIsolatedSpjCancellation extends Command
{
    protected $signature = 'spj:test-cancel-copy
        {npsn : NPSN sekolah pemilik baseline}
        {--database= : Path absolut SQLite copy hasil smoke numbering pertama}
        {--year= : Tahun anggaran}
        {--quarter=1 : Triwulan 1 sampai 4}
        {--fund-source= : ID, kode, atau nama sumber dana}
        {--json : Tampilkan report JSON}';

    protected $description = 'Smoke test individual cancel pada copy SQLite: nomor batal tetap reserved dan sequence berikutnya maju.';

    public function handle(
        SpjDocumentLifecycleService $lifecycle,
        SpjDocumentNumberService $numbers,
        SpjNumberingOrderService $order,
        SpjPackageValidationService $validator,
    ): int {
        $npsn = trim((string) $this->argument('npsn'));
        $copyPath = $this->absolutePath(trim((string) $this->option('database')));
        $year = (int) $this->option('year');
        $quarter = (int) $this->option('quarter');
        $fundSourceOption = trim((string) ($this->option('fund-source') ?? ''));

        if ($copyPath === '' || ! is_file($copyPath)) {
            $this->error('Path --database wajib menunjuk file SQLite copy yang sudah ada.');

            return self::FAILURE;
        }
        if ($year < 2000 || $year > 2100) {
            $this->error('Tahun anggaran tidak valid.');

            return self::FAILURE;
        }
        if ($quarter < 1 || $quarter > 4) {
            $this->error('Triwulan harus bernilai 1 sampai 4.');

            return self::FAILURE;
        }

        $school = School::query()->with('databaseRecord')->where('npsn', $npsn)->first();
        if (! $school) {
            $this->error('Sekolah dengan NPSN '.$npsn.' tidak ditemukan.');

            return self::FAILURE;
        }

        $baselinePaths = $this->resolveBaselinePaths($school);
        if ($baselinePaths === []) {
            $this->error('Baseline database sekolah tidak ditemukan.');

            return self::FAILURE;
        }
        foreach ($baselinePaths as $baselinePath) {
            if ($this->samePath($copyPath, $baselinePath)) {
                $this->error('DITOLAK: --database menunjuk salah satu baseline asli. Gunakan file copy terisolasi.');

                return self::FAILURE;
            }
        }

        $baselineHashesBefore = $this->hashPaths($baselinePaths);
        $copyHashBefore = hash_file('sha256', $copyPath);
        if ($baselineHashesBefore === null || $copyHashBefore === false) {
            $this->error('Hash database tidak dapat dibaca.');

            return self::FAILURE;
        }

        $originalSchoolConfig = config('database.connections.school');
        $report = null;
        $failureMessage = null;

        try {
            config()->set('database.connections.school.database', $copyPath);
            config()->set('database.connections.school.journal_mode', null);
            config()->set('database.connections.school.synchronous', null);
            DB::purge('school');
            DB::connection('school')->statement('PRAGMA foreign_keys=ON');

            $fundSourceId = $this->resolveFundSourceId($year, $fundSourceOption);
            $fiscalYear = FiscalYear::query()
                ->where('year', $year)
                ->where('fund_source_id', $fundSourceId)
                ->first();
            if (! $fiscalYear) {
                throw new RuntimeException("Fiscal year {$year} untuk fund source {$fundSourceId} tidak ditemukan pada copy.");
            }

            [$dateFrom, $dateTo] = $this->quarterRange($year, $quarter);
            $scope = fn ($query) => $query
                ->where('fiscal_year_id', $fiscalYear->id)
                ->where('fund_source_id', $fundSourceId)
                ->whereBetween('transaction_date', [$dateFrom, $dateTo]);

            $activeDocuments = SpjDocument::query()
                ->with('package.transaction')
                ->where('document_type', 'SPJ')
                ->where('scope_key', 'MAIN')
                ->where('status', 'NUMBERED')
                ->whereHas('package.transaction', $scope)
                ->orderBy('sequence_number')
                ->get();

            if ($activeDocuments->count() !== 1) {
                throw new RuntimeException('Copy harus memiliki tepat 1 dokumen SPJ NUMBERED dari smoke test pertama; ditemukan '.$activeDocuments->count().'.');
            }

            $cancelledDocument = $activeDocuments->first();
            if (! $cancelledDocument || (int) $cancelledDocument->sequence_number !== 1) {
                throw new RuntimeException('Dokumen aktif pertama harus memiliki sequence 1 sebelum uji cancel.');
            }

            $cancelledNumber = (string) $cancelledDocument->document_number;
            $cancelledPackageId = (int) $cancelledDocument->spj_package_id;
            $cancelledNoBukti = (string) $cancelledDocument->package->transaction->no_bukti;

            $lifecycle->cancel($cancelledDocument, 0, 'Isolated runtime QA: individual cancel');
            $cancelledDocument->refresh();
            $cancelledPackage = SpjPackage::query()->findOrFail($cancelledPackageId);

            if ($cancelledDocument->status !== 'CANCELLED'
                || (int) $cancelledDocument->sequence_number !== 1
                || (string) $cancelledDocument->document_number !== $cancelledNumber) {
                throw new RuntimeException('Nomor individual cancel tidak dipertahankan sebagai histori permanen sequence 1.');
            }
            if ($cancelledPackage->status !== 'CANCELLED' || $cancelledPackage->document_number !== null) {
                throw new RuntimeException('Paket yang dibatalkan tidak masuk status CANCELLED dengan nomor paket dikosongkan.');
            }

            $sequenceAfterCancel = (int) DB::connection('school')->table('document_number_sequences')
                ->where('fiscal_year_id', $fiscalYear->id)
                ->where('fund_source_id', $fundSourceId)
                ->where('format_name', 'SPJ')
                ->value('last_number');
            if ($sequenceAfterCancel !== 1) {
                throw new RuntimeException('Individual cancel tidak boleh menurunkan sequence SPJ; nilai yang ditemukan '.$sequenceAfterCancel.'.');
            }

            $readyPackages = SpjPackage::query()
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
                ->where('status', 'READY')
                ->whereHas('transaction', $scope)
                ->get();
            $nextPackage = $order->orderedPackagesForDocumentType($readyPackages, 'SPJ')->first();
            if (! $nextPackage) {
                throw new RuntimeException('Tidak ada Paket READY berikutnya untuk membuktikan sequence setelah cancel.');
            }

            $issues = $validator->validateForNumbering($nextPackage);
            if ($issues !== []) {
                throw new RuntimeException('Paket berikutnya gagal preflight: '.collect($issues)->pluck('message')->implode(' '));
            }

            $result = $numbers->assignAutomaticNumbers(
                $nextPackage,
                $school->school_code ?: $school->npsn,
                $school->npsn,
                ['SPJ'],
            );
            $nextPackage->refresh();
            $nextDocument = $nextPackage->documents()
                ->where('document_type', 'SPJ')
                ->where('scope_key', 'MAIN')
                ->where('status', 'NUMBERED')
                ->latest('id')
                ->first();
            if (! $nextDocument || (int) $nextDocument->sequence_number !== 2) {
                throw new RuntimeException('Dokumen berikutnya harus mendapat sequence 2 setelah sequence 1 dibatalkan.');
            }

            $sequenceAfterRenumber = (int) DB::connection('school')->table('document_number_sequences')
                ->where('fiscal_year_id', $fiscalYear->id)
                ->where('fund_source_id', $fundSourceId)
                ->where('format_name', 'SPJ')
                ->value('last_number');
            if ($sequenceAfterRenumber !== 2) {
                throw new RuntimeException('Sequence SPJ setelah numbering berikutnya harus 2; ditemukan '.$sequenceAfterRenumber.'.');
            }

            $cancelledStillExists = SpjDocument::query()
                ->whereKey($cancelledDocument->id)
                ->where('status', 'CANCELLED')
                ->where('sequence_number', 1)
                ->where('document_number', $cancelledNumber)
                ->exists();
            if (! $cancelledStillExists) {
                throw new RuntimeException('Histori nomor CANCELLED sequence 1 hilang setelah nomor berikutnya diterbitkan.');
            }

            $report = [
                'isolated_copy' => true,
                'baseline_path' => $baselinePaths[0],
                'baseline_paths' => $baselinePaths,
                'copy_path' => $copyPath,
                'context' => [
                    'year' => $year,
                    'quarter' => $quarter,
                    'fund_source_id' => $fundSourceId,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                ],
                'cancelled' => [
                    'package_id' => $cancelledPackageId,
                    'no_bukti' => $cancelledNoBukti,
                    'document_number' => $cancelledNumber,
                    'sequence_number' => 1,
                    'document_status' => $cancelledDocument->status,
                    'package_status' => $cancelledPackage->status,
                ],
                'next' => [
                    'package_id' => $nextPackage->id,
                    'no_bukti' => $nextPackage->transaction->no_bukti,
                    'document_number' => $nextDocument->document_number,
                    'sequence_number' => (int) $nextDocument->sequence_number,
                    'document_status' => $nextDocument->status,
                    'package_status' => $nextPackage->status,
                ],
                'mutation' => [
                    'created' => (int) $result['created'],
                    'skipped' => (int) $result['skipped'],
                    'sequence_after_cancel' => $sequenceAfterCancel,
                    'sequence_after_next_number' => $sequenceAfterRenumber,
                ],
            ];
        } catch (Throwable $exception) {
            $failureMessage = $exception->getMessage();
        } finally {
            DB::purge('school');
            config()->set('database.connections.school', $originalSchoolConfig);
        }

        foreach ($baselinePaths as $baselinePath) {
            clearstatcache(true, $baselinePath);
        }
        clearstatcache(true, $copyPath);
        $baselineHashesAfter = $this->hashPaths($baselinePaths);
        $copyHashAfter = hash_file('sha256', $copyPath);
        $baselineUnchanged = $baselineHashesAfter !== null
            && $this->sameHashes($baselineHashesBefore, $baselineHashesAfter);
        $copyChanged = $copyHashAfter !== false && ! hash_equals($copyHashBefore, $copyHashAfter);

        if ($failureMessage !== null) {
            $this->error('Isolated cancel test gagal: '.$failureMessage);
            $this->line('BASELINE HASH UNCHANGED: '.($baselineUnchanged ? 'YES' : 'NO'));

            return self::FAILURE;
        }
        if ($report === null || ! $baselineUnchanged || ! $copyChanged) {
            $this->error('Isolated cancel guarantee gagal: seluruh baseline harus tetap sama dan copy harus berubah.');

            return self::FAILURE;
        }

        $report['baseline_hash_unchanged'] = true;
        $report['copy_hash_changed'] = true;

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('SPJ ISOLATED CANCEL + RESERVE TEST');
        $this->line('Baseline hash  : UNCHANGED');
        $this->line('Copy hash      : CHANGED');
        $this->line('Scope          : '.$year.' / TW'.$quarter.' / Fund Source '.$report['context']['fund_source_id']);
        $this->newLine();
        $this->table(
            ['State', 'Paket', 'No Bukti', 'Nomor SPJ', 'Sequence', 'Doc Status', 'Paket Status'],
            [
                ['CANCELLED', $report['cancelled']['package_id'], $report['cancelled']['no_bukti'], $report['cancelled']['document_number'], 1, $report['cancelled']['document_status'], $report['cancelled']['package_status']],
                ['NEXT', $report['next']['package_id'], $report['next']['no_bukti'], $report['next']['document_number'], 2, $report['next']['document_status'], $report['next']['package_status']],
            ]
        );
        $this->line('Sequence after cancel : '.$report['mutation']['sequence_after_cancel']);
        $this->line('Sequence after next   : '.$report['mutation']['sequence_after_next_number']);
        $this->info('ISOLATED CANCEL RESULT: PASS — nomor 1 tetap reserved sebagai CANCELLED; nomor berikutnya memakai sequence 2; baseline tidak berubah.');

        return self::SUCCESS;
    }

    private function resolveFundSourceId(int $year, string $fundSource): int
    {
        if ($fundSource !== '') {
            if (ctype_digit($fundSource)) {
                $id = (int) $fundSource;
                if (! FundSource::query()->whereKey($id)->exists()) {
                    throw new RuntimeException('Fund source ID '.$id.' tidak ditemukan pada copy.');
                }

                return $id;
            }

            $resolved = FundSource::query()
                ->whereRaw('LOWER(code) = ?', [mb_strtolower($fundSource)])
                ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($fundSource)])
                ->first();
            if (! $resolved) {
                throw new RuntimeException('Fund source '.$fundSource.' tidak ditemukan pada copy.');
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
            throw new RuntimeException('Gunakan --fund-source karena tahun '.$year.' tidak memiliki tepat satu sumber dana.');
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

    /** @return list<string> */
    private function resolveBaselinePaths(School $school): array
    {
        $managedPath = rtrim((string) config('spj.data_path'), '/\\')
            .DIRECTORY_SEPARATOR.'school-databases'
            .DIRECTORY_SEPARATOR.preg_replace('/[^A-Za-z0-9_-]/', '_', $school->npsn)
            .DIRECTORY_SEPARATOR.'spj.sqlite';
        $paths = [$managedPath, (string) ($school->databaseRecord?->database_path ?? '')];
        $resolved = [];

        foreach (array_filter($paths) as $path) {
            $candidate = $this->absolutePath($path);
            if (! is_file($candidate)) {
                continue;
            }
            if (collect($resolved)->contains(fn (string $existing): bool => $this->samePath($candidate, $existing))) {
                continue;
            }
            $resolved[] = $candidate;
        }

        return $resolved;
    }

    /** @param list<string> $paths
     *  @return array<string,string>|null
     */
    private function hashPaths(array $paths): ?array
    {
        $hashes = [];
        foreach ($paths as $path) {
            $hash = hash_file('sha256', $path);
            if ($hash === false) {
                return null;
            }
            $hashes[$this->normalizedPath($path)] = $hash;
        }

        return $hashes;
    }

    /** @param array<string,string> $before
     *  @param array<string,string> $after
     */
    private function sameHashes(array $before, array $after): bool
    {
        if (array_keys($before) !== array_keys($after)) {
            return false;
        }
        foreach ($before as $path => $hash) {
            if (! isset($after[$path]) || ! hash_equals($hash, $after[$path])) {
                return false;
            }
        }

        return true;
    }

    private function samePath(string $a, string $b): bool
    {
        return $this->normalizedPath($a) === $this->normalizedPath($b);
    }

    private function normalizedPath(string $path): string
    {
        $real = realpath($path);
        $value = $real !== false ? $real : $this->absolutePath($path);
        $value = str_replace('\\', '/', $value);

        return strtolower(rtrim($value, '/'));
    }

    private function absolutePath(string $path): string
    {
        if ($path === '') {
            return '';
        }
        if (str_starts_with($path, '/') || preg_match('~^[A-Za-z]:[\\\\/]~', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }
}
