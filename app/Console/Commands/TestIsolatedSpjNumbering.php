<?php

namespace App\Console\Commands;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\SpjPackage;
use App\Services\SpjDocumentNumberService;
use App\Services\SpjNumberingOrderService;
use App\Services\SpjNumberingPolicyService;
use App\Services\SpjPackageValidationService;
use App\Support\ActiveSpjContext;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class TestIsolatedSpjNumbering extends Command
{
    protected $signature = 'spj:test-numbering-copy
        {npsn : NPSN sekolah pemilik baseline}
        {--database= : Path absolut SQLite copy yang boleh dimutasi}
        {--year= : Tahun anggaran}
        {--quarter=1 : Triwulan 1 sampai 4}
        {--fund-source= : ID, kode, atau nama sumber dana}
        {--json : Tampilkan report JSON}';

    protected $description = 'Smoke test mutation numbering pada copy SQLite terisolasi; seluruh baseline asli diverifikasi tidak berubah.';

    public function handle(): int
    {
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

            app()->forgetInstance(ActiveSpjContext::class);
            app()->instance(ActiveSpjContext::class, new ActiveSpjContext(
                $school->id,
                $fiscalYear->id,
                $fundSourceId,
                0,
                true,
            ));

            /** @var SpjPackageValidationService $validator */
            $validator = app(SpjPackageValidationService::class);
            /** @var SpjNumberingOrderService $order */
            $order = app(SpjNumberingOrderService::class);
            /** @var SpjNumberingPolicyService $policy */
            $policy = app(SpjNumberingPolicyService::class);
            /** @var SpjDocumentNumberService $numbers */
            $numbers = app(SpjDocumentNumberService::class);

            [$dateFrom, $dateTo] = $this->quarterRange($year, $quarter);
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
                ->where('status', 'READY')
                ->whereHas('transaction', fn ($query) => $query
                    ->where('fiscal_year_id', $fiscalYear->id)
                    ->where('fund_source_id', $fundSourceId)
                    ->whereBetween('transaction_date', [$dateFrom, $dateTo]))
                ->get();

            if ($packages->isEmpty()) {
                throw new RuntimeException('Tidak ada Paket READY pada scope copy yang dipilih.');
            }

            $first = $order->orderedPackagesForDocumentType($packages, 'SPJ')->first();
            if (! $first) {
                throw new RuntimeException('Urutan canonical tidak menghasilkan Paket pertama.');
            }

            $issues = $validator->validateForNumbering($first);
            if ($issues !== []) {
                throw new RuntimeException('Paket pertama gagal preflight: '.collect($issues)->pluck('message')->implode(' '));
            }

            $alreadyNumbered = SpjPackage::query()->whereIn('status', ['NUMBERED', 'FINAL'])->count();
            if ($alreadyNumbered > 0) {
                throw new RuntimeException('Copy sudah memiliki Paket NUMBERED/FINAL. Buat copy baru dari baseline sebelum smoke test.');
            }

            $policy->formatFor($fiscalYear->id, 'SPJ');
            $result = $numbers->assignAutomaticNumbers(
                $first,
                $school->school_code ?: $school->npsn,
                $school->npsn,
                ['SPJ'],
            );

            $first->refresh();
            $document = $first->documents()
                ->where('document_type', 'SPJ')
                ->where('scope_key', 'MAIN')
                ->where('status', 'NUMBERED')
                ->latest('id')
                ->first();
            if (! $document || blank($document->document_number)) {
                throw new RuntimeException('Dokumen SPJ bernomor tidak terbentuk pada copy.');
            }

            $sequence = DB::connection('school')->table('document_number_sequences')
                ->where('fiscal_year_id', $fiscalYear->id)
                ->where('fund_source_id', $fundSourceId)
                ->where('format_name', 'SPJ')
                ->first();
            if (! $sequence || (int) $sequence->last_number !== 1) {
                throw new RuntimeException('Sequence SPJ pertama tidak bernilai 1.');
            }

            $numberedPackages = SpjPackage::query()->where('status', 'NUMBERED')->count();
            if ($numberedPackages !== 1) {
                throw new RuntimeException('Smoke test harus hanya menomori tepat 1 Paket; ditemukan '.$numberedPackages.'.');
            }

            $report = [
                'isolated_copy' => true,
                'school' => ['id' => $school->id, 'npsn' => $school->npsn, 'name' => $school->name],
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
                'first_package' => [
                    'package_id' => $first->id,
                    'transaction_id' => $first->transaction->id,
                    'no_bukti' => $first->transaction->no_bukti,
                    'transaction_date' => optional($first->transaction->transaction_date)->format('Y-m-d'),
                    'document_number' => $document->document_number,
                    'sequence_number' => (int) $document->sequence_number,
                    'package_status' => $first->status,
                ],
                'mutation' => [
                    'created' => (int) $result['created'],
                    'skipped' => (int) $result['skipped'],
                    'numbered_packages' => $numberedPackages,
                    'sequence_last_number' => (int) $sequence->last_number,
                ],
            ];
        } catch (Throwable $exception) {
            $failureMessage = $exception->getMessage();
        } finally {
            DB::purge('school');
            config()->set('database.connections.school', $originalSchoolConfig);
            app()->forgetInstance(ActiveSpjContext::class);
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
            $this->error('Isolated numbering test gagal: '.$failureMessage);
            $this->line('BASELINE HASH UNCHANGED: '.($baselineUnchanged ? 'YES' : 'NO'));

            return self::FAILURE;
        }
        if ($report === null || ! $baselineUnchanged || ! $copyChanged) {
            $this->error('Isolated numbering guarantee gagal: seluruh baseline harus tetap sama dan copy harus berubah.');

            return self::FAILURE;
        }

        $report['baseline_hash_unchanged'] = true;
        $report['copy_hash_changed'] = true;

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('SPJ ISOLATED NUMBERING TEST');
        $this->line('Baseline       : '.$report['baseline_path']);
        if (count($baselinePaths) > 1) {
            $this->line('Protected DBs  : '.count($baselinePaths).' baseline paths');
        }
        $this->line('Copy           : '.$copyPath);
        $this->line('Baseline hash  : UNCHANGED');
        $this->line('Copy hash      : CHANGED');
        $this->line('Scope          : '.$year.' / TW'.$quarter.' / Fund Source '.$report['context']['fund_source_id']);
        $this->newLine();
        $this->table(
            ['Paket', 'Transaction', 'No Bukti', 'Tanggal', 'Nomor SPJ', 'Sequence', 'Status'],
            [[
                $report['first_package']['package_id'],
                $report['first_package']['transaction_id'],
                $report['first_package']['no_bukti'],
                $report['first_package']['transaction_date'],
                $report['first_package']['document_number'],
                $report['first_package']['sequence_number'],
                $report['first_package']['package_status'],
            ]]
        );
        $this->line('Numbered packages : '.$report['mutation']['numbered_packages']);
        $this->line('Sequence SPJ      : '.$report['mutation']['sequence_last_number']);
        $this->info('ISOLATED TEST RESULT: PASS — tepat 1 Paket bernomor pada copy; seluruh baseline asli tidak berubah.');

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
        $value = rtrim($value, '/');

        return DIRECTORY_SEPARATOR === '\\' ? mb_strtolower($value) : $value;
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
