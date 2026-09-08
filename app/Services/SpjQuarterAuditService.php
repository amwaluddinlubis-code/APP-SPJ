<?php

namespace App\Services;

use DateTimeImmutable;
use PDO;
use RuntimeException;

class SpjQuarterAuditService
{
    /** @var array<int, string> */
    public const CATEGORIES = [
        'BARANG',
        'KONSUMSI',
        'PEMELIHARAAN',
        'JASA_LAINNYA',
        'SPPD',
        'HONOR_PEGAWAI',
    ];

    /**
     * Audit a tenant SQLite database without mutating it.
     *
     * @return array<string, mixed>
     */
    public function audit(string $databasePath, int $quarter, ?int $year = null, int|string|null $fundSource = null): array
    {
        if ($quarter < 1 || $quarter > 4) {
            throw new RuntimeException('Triwulan harus bernilai 1 sampai 4.');
        }
        if (! is_file($databasePath)) {
            throw new RuntimeException('Database sekolah tidak ditemukan: '.$databasePath);
        }

        $pdo = $this->connectReadOnly($databasePath);
        $tables = $this->tables($pdo);
        foreach (['transactions', 'transaction_items', 'spj_packages', 'fiscal_years'] as $requiredTable) {
            if (! in_array($requiredTable, $tables, true)) {
                throw new RuntimeException('Schema tenant belum lengkap. Tabel wajib tidak ditemukan: '.$requiredTable);
            }
        }

        $fundSourceId = $this->resolveFundSourceId($pdo, $tables, $fundSource);
        $selectedYear = $year ?? $this->latestYearWithTransactions($pdo, $fundSourceId);
        if (! $selectedYear) {
            throw new RuntimeException('Tidak ada tahun anggaran yang dapat diaudit pada database sekolah.');
        }

        [$dateFrom, $dateTo] = $this->quarterRange($selectedYear, $quarter);
        $scope = $this->scopeSql($fundSourceId);
        $scopeParams = [
            ':year' => $selectedYear,
            ':date_from' => $dateFrom,
            ':date_to' => $dateTo,
        ];
        if ($fundSourceId !== null) {
            $scopeParams[':fund_source_id'] = $fundSourceId;
        }

        $integrityRows = $pdo->query('PRAGMA integrity_check')->fetchAll(PDO::FETCH_COLUMN);
        $foreignKeyRows = $pdo->query('PRAGMA foreign_key_check')->fetchAll(PDO::FETCH_ASSOC);
        $queryOnly = (int) $pdo->query('PRAGMA query_only')->fetchColumn() === 1;

        $transactions = $this->fetchAll($pdo, <<<SQL
            SELECT
                t.*,
                p.id AS package_id,
                p.status AS package_status,
                p.document_number AS package_document_number,
                p.numbered_at AS package_numbered_at,
                p.finalized_at AS package_finalized_at,
                p.cancelled_at AS package_cancelled_at
            FROM transactions t
            JOIN fiscal_years fy ON fy.id = t.fiscal_year_id
            LEFT JOIN spj_packages p ON p.transaction_id = t.id
            {$scope['where']}
            ORDER BY t.transaction_date ASC, t.id ASC
        SQL, $scopeParams);

        $itemStats = $this->indexedStats($this->fetchAll($pdo, <<<SQL
            SELECT
                ti.transaction_id,
                COUNT(*) AS item_count,
                SUM(CASE WHEN TRIM(COALESCE(ti.item_description, '')) = '' THEN 1 ELSE 0 END) AS blank_item_description_count,
                COALESCE(SUM(ti.amount), 0) AS item_amount
            FROM transaction_items ti
            JOIN transactions t ON t.id = ti.transaction_id
            JOIN fiscal_years fy ON fy.id = t.fiscal_year_id
            {$scope['where']}
            GROUP BY ti.transaction_id
        SQL, $scopeParams), 'transaction_id');

        $detailStats = $this->detailStats($pdo, $tables, $scope['where'], $scopeParams);
        $anomalies = [];

        if ($integrityRows !== ['ok']) {
            foreach ($integrityRows as $message) {
                $anomalies[] = $this->anomaly('CRITICAL', 'SQLITE_INTEGRITY', null, null, (string) $message);
            }
        }
        foreach ($foreignKeyRows as $row) {
            $anomalies[] = $this->anomaly(
                'CRITICAL',
                'FOREIGN_KEY_VIOLATION',
                null,
                null,
                sprintf('Tabel %s rowid %s mereferensikan parent %s.', $row['table'] ?? '?', $row['rowid'] ?? '?', $row['parent'] ?? '?')
            );
        }

        foreach ($detailStats['missing_tables'] as $table) {
            $anomalies[] = $this->anomaly('WARNING', 'SCHEMA_TABLE_MISSING', null, null, 'Tabel detail belum tersedia: '.$table);
        }

        $categories = [];
        foreach (self::CATEGORIES as $category) {
            $categories[$category] = [
                'category' => $category,
                'transactions' => 0,
                'packages' => 0,
                'none' => 0,
                'draft' => 0,
                'ready' => 0,
                'numbered' => 0,
                'final' => 0,
                'cancelled' => 0,
                'candidate_count' => 0,
            ];
        }

        $candidatePool = [];
        $unassigned = 0;
        $financialMismatchCount = 0;
        $blankItemDescriptionCount = 0;

        foreach ($transactions as $transaction) {
            $transactionAnomalyStart = count($anomalies);
            $transactionId = (int) $transaction['id'];
            $packageId = filled($transaction['package_id'] ?? null) ? (int) $transaction['package_id'] : null;
            $category = $this->normalizeCategory($transaction['spj_category'] ?? null);
            $items = $itemStats[$transactionId] ?? [
                'item_count' => 0,
                'blank_item_description_count' => 0,
                'item_amount' => 0,
            ];

            $gross = (float) ($transaction['gross_amount'] ?? 0);
            $tax = (float) ($transaction['tax_total'] ?? 0);
            $net = (float) ($transaction['net_amount'] ?? 0);
            $financialMismatch = abs(($gross - $tax) - $net) > 0.02;
            if ($financialMismatch) {
                $financialMismatchCount++;
                $anomalies[] = $this->anomaly(
                    'WARNING',
                    'GROSS_TAX_NET_MISMATCH',
                    $transactionId,
                    $packageId,
                    sprintf('Bruto %.2f - pajak %.2f tidak sama dengan netto %.2f.', $gross, $tax, $net)
                );
            }

            $componentTax = (float) ($transaction['ppn'] ?? 0)
                + (float) ($transaction['pph21'] ?? 0)
                + (float) ($transaction['pph22'] ?? 0)
                + (float) ($transaction['pph23'] ?? 0)
                + (float) ($transaction['pph4'] ?? 0)
                + (float) ($transaction['sspd'] ?? 0);
            if (abs($componentTax - $tax) > 0.02) {
                $anomalies[] = $this->anomaly(
                    'INFO',
                    'TAX_COMPONENT_MISMATCH',
                    $transactionId,
                    $packageId,
                    sprintf('Jumlah komponen pajak %.2f berbeda dari tax_total %.2f; verifikasi source BKU.', $componentTax, $tax)
                );
            }

            if ((int) ($items['item_count'] ?? 0) === 0) {
                $anomalies[] = $this->anomaly('WARNING', 'NO_TRANSACTION_ITEMS', $transactionId, $packageId, 'Transaksi tidak memiliki rincian item.');
            }
            if ((int) ($items['blank_item_description_count'] ?? 0) > 0) {
                $count = (int) $items['blank_item_description_count'];
                $blankItemDescriptionCount += $count;
                $anomalies[] = $this->anomaly('WARNING', 'BLANK_ITEM_DESCRIPTION', $transactionId, $packageId, $count.' item_description masih kosong.');
            }
            if ((float) ($items['item_amount'] ?? 0) > 0 && abs((float) $items['item_amount'] - $gross) > 0.02) {
                $anomalies[] = $this->anomaly(
                    'INFO',
                    'ITEM_TOTAL_MISMATCH',
                    $transactionId,
                    $packageId,
                    sprintf('Total item %.2f berbeda dari bruto transaksi %.2f; verifikasi mapping source.', (float) $items['item_amount'], $gross)
                );
            }
            if (strtoupper((string) ($transaction['source_status'] ?? 'ACTIVE')) !== 'ACTIVE') {
                $anomalies[] = $this->anomaly('WARNING', 'SOURCE_NOT_ACTIVE', $transactionId, $packageId, 'Source transaksi tidak berstatus ACTIVE.');
            }
            if ((bool) ($transaction['requires_reconciliation'] ?? false)) {
                $anomalies[] = $this->anomaly('WARNING', 'REQUIRES_RECONCILIATION', $transactionId, $packageId, 'Transaksi membutuhkan rekonsiliasi.');
            }

            $this->auditPackageLifecycle($transaction, $anomalies);

            if ($category === null) {
                $unassigned++;

                continue;
            }

            $categories[$category]['transactions']++;
            $status = strtoupper((string) ($transaction['package_status'] ?? 'NONE'));
            if ($packageId === null) {
                $categories[$category]['none']++;
            } else {
                $categories[$category]['packages']++;
                $key = strtolower($status);
                if (array_key_exists($key, $categories[$category])) {
                    $categories[$category][$key]++;
                }
                $this->auditCategoryDetails($category, $transactionId, $packageId, $transaction, $detailStats, $anomalies);
            }

            $candidateIssues = $this->candidateIssues(
                $transaction,
                $items,
                $financialMismatch,
                array_slice($anomalies, $transactionAnomalyStart),
            );
            if ($candidateIssues === []) {
                $categories[$category]['candidate_count']++;
            }
            $candidatePool[$category][] = [
                'category' => $category,
                'transaction_id' => $transactionId,
                'no_bukti' => (string) ($transaction['no_bukti'] ?? ''),
                'transaction_date' => (string) ($transaction['transaction_date'] ?? ''),
                'fund_source_id' => $transaction['fund_source_id'] !== null ? (int) $transaction['fund_source_id'] : null,
                'package_id' => $packageId,
                'package_status' => $packageId === null ? 'NONE' : $status,
                'issues' => $candidateIssues,
            ];
        }

        $this->auditOrphansAndNumbers($pdo, $tables, $anomalies);

        $candidates = [];
        foreach (self::CATEGORIES as $category) {
            $pool = $candidatePool[$category] ?? [];
            usort($pool, function (array $a, array $b): int {
                $issueCompare = count($a['issues']) <=> count($b['issues']);
                if ($issueCompare !== 0) {
                    return $issueCompare;
                }
                $statusRank = ['DRAFT' => 0, 'READY' => 1, 'NONE' => 2, 'NUMBERED' => 3, 'FINAL' => 4, 'CANCELLED' => 5];
                $statusCompare = ($statusRank[$a['package_status']] ?? 9) <=> ($statusRank[$b['package_status']] ?? 9);
                if ($statusCompare !== 0) {
                    return $statusCompare;
                }

                return [$a['transaction_date'], $a['transaction_id']] <=> [$b['transaction_date'], $b['transaction_id']];
            });
            $candidates[$category] = $pool[0] ?? null;
        }

        $migration = ['count' => null, 'latest' => null];
        if (in_array('migrations', $tables, true)) {
            $migration['count'] = (int) $pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn();
            $migration['latest'] = $pdo->query('SELECT migration FROM migrations ORDER BY id DESC LIMIT 1')->fetchColumn() ?: null;
        }

        $severityCounts = ['CRITICAL' => 0, 'WARNING' => 0, 'INFO' => 0];
        foreach ($anomalies as $anomaly) {
            $severityCounts[$anomaly['severity']] = ($severityCounts[$anomaly['severity']] ?? 0) + 1;
        }

        return [
            'read_only' => true,
            'query_only' => $queryOnly,
            'database_path' => $databasePath,
            'integrity' => [
                'status' => $integrityRows === ['ok'] ? 'PASS' : 'FAIL',
                'messages' => $integrityRows,
                'foreign_key_violations' => count($foreignKeyRows),
                'migration_count' => $migration['count'],
                'latest_migration' => $migration['latest'],
            ],
            'context' => [
                'year' => $selectedYear,
                'quarter' => $quarter,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'fund_source_id' => $fundSourceId,
            ],
            'summary' => [
                'transactions' => count($transactions),
                'packages' => collect($transactions)->filter(fn (array $row): bool => filled($row['package_id'] ?? null))->count(),
                'unassigned_category' => $unassigned,
                'blank_item_descriptions' => $blankItemDescriptionCount,
                'financial_mismatches' => $financialMismatchCount,
                'anomalies' => count($anomalies),
                'critical' => $severityCounts['CRITICAL'],
                'warnings' => $severityCounts['WARNING'],
                'info' => $severityCounts['INFO'],
            ],
            'categories' => array_values($categories),
            'candidates' => $candidates,
            'anomalies' => $anomalies,
        ];
    }

    private function connectReadOnly(string $databasePath): PDO
    {
        $pdo = new PDO('sqlite:'.$databasePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $pdo->exec('PRAGMA query_only = ON');
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');

        if ((int) $pdo->query('PRAGMA query_only')->fetchColumn() !== 1) {
            throw new RuntimeException('Koneksi audit gagal masuk mode SQLite query_only.');
        }

        return $pdo;
    }

    /** @return array<int, string> */
    private function tables(PDO $pdo): array
    {
        return array_map(
            'strval',
            $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    private function resolveFundSourceId(PDO $pdo, array $tables, int|string|null $fundSource): ?int
    {
        if ($fundSource === null || trim((string) $fundSource) === '') {
            return null;
        }
        if (is_numeric($fundSource)) {
            return (int) $fundSource;
        }
        if (! in_array('fund_sources', $tables, true)) {
            throw new RuntimeException('Filter sumber dana membutuhkan tabel fund_sources.');
        }

        $statement = $pdo->prepare('SELECT id FROM fund_sources WHERE LOWER(code) = LOWER(:value) OR LOWER(name) = LOWER(:value) LIMIT 1');
        $statement->execute([':value' => trim((string) $fundSource)]);
        $id = $statement->fetchColumn();
        if ($id === false) {
            throw new RuntimeException('Sumber dana tidak ditemukan: '.$fundSource);
        }

        return (int) $id;
    }

    private function latestYearWithTransactions(PDO $pdo, ?int $fundSourceId): ?int
    {
        $sql = 'SELECT MAX(fy.year) FROM transactions t JOIN fiscal_years fy ON fy.id = t.fiscal_year_id';
        $params = [];
        if ($fundSourceId !== null) {
            $sql .= ' WHERE t.fund_source_id = :fund_source_id';
            $params[':fund_source_id'] = $fundSourceId;
        }
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        $year = $statement->fetchColumn();

        if ($year !== false && $year !== null) {
            return (int) $year;
        }

        $year = $pdo->query('SELECT MAX(year) FROM fiscal_years')->fetchColumn();

        return $year === false || $year === null ? null : (int) $year;
    }

    /** @return array{0:string,1:string} */
    private function quarterRange(int $year, int $quarter): array
    {
        $startMonth = (($quarter - 1) * 3) + 1;
        $start = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $startMonth));
        $end = $start->modify('+3 months -1 day');

        return [$start->format('Y-m-d'), $end->format('Y-m-d')];
    }

    /** @return array{where:string} */
    private function scopeSql(?int $fundSourceId): array
    {
        $where = 'WHERE fy.year = :year AND t.transaction_date >= :date_from AND t.transaction_date <= :date_to';
        if ($fundSourceId !== null) {
            $where .= ' AND t.fund_source_id = :fund_source_id';
        }

        return ['where' => $where];
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchAll(PDO $pdo, string $sql, array $params = []): array
    {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int, array<string, mixed>> */
    private function indexedStats(array $rows, string $key): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int) $row[$key]] = $row;
        }

        return $indexed;
    }

    /** @return array<string, mixed> */
    private function detailStats(PDO $pdo, array $tables, string $where, array $params): array
    {
        $missing = [];
        $stats = [
            'goods' => [], 'participants' => [], 'work_orders' => [], 'travels' => [], 'honors' => [], 'service_recipients' => [],
        ];

        if (in_array('spj_goods', $tables, true)) {
            $stats['goods'] = $this->indexedStats($this->fetchAll($pdo, <<<SQL
                SELECT ti.transaction_id, COUNT(g.id) AS row_count
                FROM transaction_items ti
                JOIN transactions t ON t.id = ti.transaction_id
                JOIN fiscal_years fy ON fy.id = t.fiscal_year_id
                LEFT JOIN spj_goods g ON g.transaction_item_id = ti.id
                {$where}
                GROUP BY ti.transaction_id
            SQL, $params), 'transaction_id');
        } else {
            $missing[] = 'spj_goods';
        }

        if (in_array('spj_participants', $tables, true)) {
            $stats['participants'] = $this->indexedStats($this->fetchAll($pdo, <<<SQL
                SELECT ti.transaction_id, COUNT(p.id) AS row_count, COALESCE(SUM(p.portions), 0) AS portions
                FROM transaction_items ti
                JOIN transactions t ON t.id = ti.transaction_id
                JOIN fiscal_years fy ON fy.id = t.fiscal_year_id
                LEFT JOIN spj_participants p ON p.transaction_item_id = ti.id
                {$where}
                GROUP BY ti.transaction_id
            SQL, $params), 'transaction_id');
        } else {
            $missing[] = 'spj_participants';
        }

        if (in_array('spj_work_orders', $tables, true)) {
            $workerJoin = in_array('spj_workers', $tables, true) ? 'LEFT JOIN spj_workers w ON w.work_order_id = wo.id' : '';
            $workerSelect = in_array('spj_workers', $tables, true) ? 'COUNT(w.id)' : '0';
            $stats['work_orders'] = $this->indexedStats($this->fetchAll($pdo, <<<SQL
                SELECT t.id AS transaction_id, COUNT(DISTINCT wo.id) AS row_count, {$workerSelect} AS worker_count
                FROM transactions t
                JOIN fiscal_years fy ON fy.id = t.fiscal_year_id
                LEFT JOIN spj_work_orders wo ON wo.transaction_id = t.id
                {$workerJoin}
                {$where}
                GROUP BY t.id
            SQL, $params), 'transaction_id');
            if (! in_array('spj_workers', $tables, true)) {
                $missing[] = 'spj_workers';
            }
        } else {
            $missing[] = 'spj_work_orders';
            if (! in_array('spj_workers', $tables, true)) {
                $missing[] = 'spj_workers';
            }
        }

        if (in_array('spj_travels', $tables, true)) {
            $stats['travels'] = $this->indexedStats($this->fetchAll($pdo, <<<SQL
                SELECT t.id AS transaction_id, COUNT(tr.id) AS row_count
                FROM transactions t
                JOIN fiscal_years fy ON fy.id = t.fiscal_year_id
                LEFT JOIN spj_travels tr ON tr.transaction_id = t.id
                {$where}
                GROUP BY t.id
            SQL, $params), 'transaction_id');
        } else {
            $missing[] = 'spj_travels';
        }

        if (in_array('spj_honors', $tables, true)) {
            $stats['honors'] = $this->indexedStats($this->fetchAll($pdo, <<<SQL
                SELECT ti.transaction_id, COUNT(h.id) AS row_count
                FROM transaction_items ti
                JOIN transactions t ON t.id = ti.transaction_id
                JOIN fiscal_years fy ON fy.id = t.fiscal_year_id
                LEFT JOIN spj_honors h ON h.transaction_item_id = ti.id
                {$where}
                GROUP BY ti.transaction_id
            SQL, $params), 'transaction_id');
        } else {
            $missing[] = 'spj_honors';
        }

        if (in_array('spj_service_recipients', $tables, true)) {
            $stats['service_recipients'] = $this->indexedStats($this->fetchAll($pdo, <<<SQL
                SELECT
                    t.id AS transaction_id,
                    COUNT(sr.id) AS row_count,
                    COALESCE(SUM(sr.amount), 0) AS gross_amount,
                    COALESCE(SUM(sr.tax_amount), 0) AS tax_amount,
                    COALESCE(SUM(sr.net_amount), 0) AS net_amount
                FROM transactions t
                JOIN fiscal_years fy ON fy.id = t.fiscal_year_id
                LEFT JOIN spj_service_recipients sr ON sr.transaction_id = t.id
                {$where}
                GROUP BY t.id
            SQL, $params), 'transaction_id');
        } else {
            $missing[] = 'spj_service_recipients';
        }

        $stats['missing_tables'] = array_values(array_unique($missing));

        return $stats;
    }

    private function normalizeCategory(mixed $value): ?string
    {
        $value = strtoupper(trim((string) $value));
        $value = match ($value) {
            'BELANJA_MODAL' => 'BARANG',
            'PERJALANAN_DINAS' => 'SPPD',
            'JASA_HONORARIUM' => 'HONOR_PEGAWAI',
            'UPAH' => 'PEMELIHARAAN',
            'LAINNYA' => 'JASA_LAINNYA',
            default => $value,
        };

        return in_array($value, self::CATEGORIES, true) ? $value : null;
    }

    /** @param array<int, array<string, mixed>> $anomalies */
    private function auditPackageLifecycle(array $transaction, array &$anomalies): void
    {
        if (blank($transaction['package_id'] ?? null)) {
            return;
        }

        $transactionId = (int) $transaction['id'];
        $packageId = (int) $transaction['package_id'];
        $status = strtoupper((string) ($transaction['package_status'] ?? 'DRAFT'));
        $numberedAt = $transaction['package_numbered_at'] ?? null;
        $finalizedAt = $transaction['package_finalized_at'] ?? null;
        $cancelledAt = $transaction['package_cancelled_at'] ?? null;
        $number = trim((string) ($transaction['package_document_number'] ?? ''));

        if (in_array($status, ['DRAFT', 'READY'], true) && filled($numberedAt)) {
            $anomalies[] = $this->anomaly('CRITICAL', 'PACKAGE_PRENUMBERED_STATE', $transactionId, $packageId, $status.' memiliki numbered_at.');
        }
        if ($status === 'NUMBERED' && ($number === '' || blank($numberedAt))) {
            $anomalies[] = $this->anomaly('CRITICAL', 'NUMBERED_PACKAGE_INCOMPLETE', $transactionId, $packageId, 'Paket NUMBERED tidak memiliki document_number/numbered_at lengkap.');
        }
        if ($status === 'FINAL' && ($number === '' || blank($numberedAt) || blank($finalizedAt))) {
            $anomalies[] = $this->anomaly('CRITICAL', 'FINAL_PACKAGE_INCOMPLETE', $transactionId, $packageId, 'Paket FINAL tidak memiliki nomor/timestamp final lengkap.');
        }
        if ($status === 'CANCELLED' && blank($cancelledAt)) {
            $anomalies[] = $this->anomaly('WARNING', 'CANCELLED_PACKAGE_WITHOUT_TIMESTAMP', $transactionId, $packageId, 'Paket CANCELLED tidak memiliki cancelled_at.');
        }
        if (! in_array($status, ['DRAFT', 'READY', 'NUMBERED', 'FINAL', 'CANCELLED'], true)) {
            $anomalies[] = $this->anomaly('WARNING', 'UNKNOWN_PACKAGE_STATUS', $transactionId, $packageId, 'Status Paket tidak canonical: '.$status);
        }
    }

    /** @param array<int, array<string, mixed>> $anomalies */
    private function auditCategoryDetails(string $category, int $transactionId, int $packageId, array $transaction, array $details, array &$anomalies): void
    {
        $count = fn (string $group, string $field = 'row_count'): int => (int) ($details[$group][$transactionId][$field] ?? 0);

        match ($category) {
            'BARANG' => $this->auditBarangDetails($transactionId, $packageId, $transaction, $count('goods'), $anomalies),
            'KONSUMSI' => $count('participants') < 1
                ? $anomalies[] = $this->anomaly('WARNING', 'KONSUMSI_PARTICIPANT_EMPTY', $transactionId, $packageId, 'Paket KONSUMSI belum memiliki peserta.')
                : null,
            'PEMELIHARAAN' => $this->auditMaintenanceDetails($transactionId, $packageId, $details, $anomalies),
            'SPPD' => $count('travels') < 1
                ? $anomalies[] = $this->anomaly('WARNING', 'SPPD_TRAVEL_EMPTY', $transactionId, $packageId, 'Paket SPPD belum memiliki pelaksana perjalanan.')
                : null,
            'HONOR_PEGAWAI' => $count('honors') < 1
                ? $anomalies[] = $this->anomaly('WARNING', 'HONOR_RECIPIENT_EMPTY', $transactionId, $packageId, 'Paket HONOR_PEGAWAI belum memiliki penerima honor.')
                : null,
            'JASA_LAINNYA' => $this->auditServiceRecipientDetails($transactionId, $packageId, $transaction, $details, $anomalies),
            default => null,
        };
    }

    /** @param array<int, array<string, mixed>> $anomalies */
    private function auditBarangDetails(int $transactionId, int $packageId, array $transaction, int $goodsCount, array &$anomalies): void
    {
        if ($this->isSiplahTransaction($transaction)) {
            $hasMarketplaceReference = filled($transaction['siplah_order_number'] ?? null)
                || filled($transaction['payment_reference'] ?? null)
                || filled($transaction['invoice_number'] ?? null);
            if (! $hasMarketplaceReference) {
                $anomalies[] = $this->anomaly(
                    'WARNING',
                    'SIPLAH_REFERENCE_EMPTY',
                    $transactionId,
                    $packageId,
                    'Paket BARANG SiPLah belum memiliki referensi pesanan, pembayaran, atau invoice marketplace.'
                );
            }

            return;
        }

        if ($goodsCount < 1) {
            $anomalies[] = $this->anomaly('WARNING', 'BARANG_DETAIL_EMPTY', $transactionId, $packageId, 'Paket BARANG non-SiPLah belum memiliki detail pengadaan pada spj_goods.');
        }
    }

    private function isSiplahTransaction(array $transaction): bool
    {
        return (bool) ($transaction['is_siplah'] ?? false)
            || strtolower(trim((string) ($transaction['payment_method'] ?? ''))) === 'siplah';
    }

    /** @param array<int, array<string, mixed>> $anomalies */
    private function auditMaintenanceDetails(int $transactionId, int $packageId, array $details, array &$anomalies): void
    {
        if ((int) ($details['work_orders'][$transactionId]['row_count'] ?? 0) < 1) {
            $anomalies[] = $this->anomaly('WARNING', 'MAINTENANCE_WORK_ORDER_EMPTY', $transactionId, $packageId, 'Paket PEMELIHARAAN belum memiliki work order.');
        }
        if ((int) ($details['work_orders'][$transactionId]['worker_count'] ?? 0) < 1) {
            $anomalies[] = $this->anomaly('WARNING', 'MAINTENANCE_WORKER_EMPTY', $transactionId, $packageId, 'Paket PEMELIHARAAN belum memiliki pekerja.');
        }
    }

    /** @param array<int, array<string, mixed>> $anomalies */
    private function auditServiceRecipientDetails(int $transactionId, int $packageId, array $transaction, array $details, array &$anomalies): void
    {
        $row = $details['service_recipients'][$transactionId] ?? null;
        if (! $row || (int) ($row['row_count'] ?? 0) < 1) {
            $anomalies[] = $this->anomaly('WARNING', 'SERVICE_RECIPIENT_EMPTY', $transactionId, $packageId, 'Paket JASA_LAINNYA belum memiliki penerima jasa.');

            return;
        }

        foreach ([
            'gross_amount' => 'gross_amount',
            'tax_amount' => 'tax_total',
            'net_amount' => 'net_amount',
        ] as $detailField => $transactionField) {
            if (abs((float) $row[$detailField] - (float) ($transaction[$transactionField] ?? 0)) > 0.02) {
                $anomalies[] = $this->anomaly(
                    'WARNING',
                    'SERVICE_RECIPIENT_RECONCILIATION',
                    $transactionId,
                    $packageId,
                    'Agregat '.$detailField.' penerima jasa tidak sama dengan '.$transactionField.' transaksi.'
                );
            }
        }
    }

    /** @return array<int, string> */
    private function candidateIssues(array $transaction, array $items, bool $financialMismatch, array $transactionAnomalies = []): array
    {
        $issues = [];
        if (strtoupper((string) ($transaction['source_status'] ?? 'ACTIVE')) !== 'ACTIVE') {
            $issues[] = 'source_not_active';
        }
        if ((bool) ($transaction['requires_reconciliation'] ?? false)) {
            $issues[] = 'requires_reconciliation';
        }
        if ((int) ($items['item_count'] ?? 0) < 1) {
            $issues[] = 'no_items';
        }
        if ((int) ($items['blank_item_description_count'] ?? 0) > 0) {
            $issues[] = 'blank_item_description';
        }
        if ((float) ($transaction['gross_amount'] ?? 0) <= 0) {
            $issues[] = 'gross_not_positive';
        }
        if ($financialMismatch) {
            $issues[] = 'gross_tax_net_mismatch';
        }

        foreach ($transactionAnomalies as $anomaly) {
            if (in_array($anomaly['severity'] ?? null, ['CRITICAL', 'WARNING'], true)) {
                $issues[] = strtolower((string) ($anomaly['code'] ?? 'audit_warning'));
            }
        }

        return array_values(array_unique($issues));
    }

    /** @param array<int, array<string, mixed>> $anomalies */
    private function auditOrphansAndNumbers(PDO $pdo, array $tables, array &$anomalies): void
    {
        $orphanPackages = $this->fetchAll($pdo, 'SELECT p.id FROM spj_packages p LEFT JOIN transactions t ON t.id = p.transaction_id WHERE t.id IS NULL');
        foreach ($orphanPackages as $row) {
            $anomalies[] = $this->anomaly('CRITICAL', 'ORPHAN_PACKAGE', null, (int) $row['id'], 'Paket tidak memiliki transaksi parent.');
        }

        $duplicates = $this->fetchAll($pdo, 'SELECT fiscal_year_id, no_bukti, COUNT(*) AS total FROM transactions GROUP BY fiscal_year_id, no_bukti HAVING COUNT(*) > 1');
        foreach ($duplicates as $row) {
            $anomalies[] = $this->anomaly('CRITICAL', 'DUPLICATE_NO_BUKTI', null, null, 'Duplicate no_bukti '.$row['no_bukti'].' pada fiscal_year_id '.$row['fiscal_year_id'].'.');
        }

        if (! in_array('spj_documents', $tables, true)) {
            return;
        }
        $duplicateNumbers = $this->fetchAll($pdo, <<<'SQL'
            SELECT document_number, COUNT(*) AS total
            FROM spj_documents
            WHERE document_number IS NOT NULL
              AND TRIM(document_number) <> ''
              AND status <> 'CANCELLED'
            GROUP BY document_number
            HAVING COUNT(*) > 1
        SQL);
        foreach ($duplicateNumbers as $row) {
            $anomalies[] = $this->anomaly('CRITICAL', 'DUPLICATE_ACTIVE_DOCUMENT_NUMBER', null, null, 'Nomor dokumen aktif ganda: '.$row['document_number'].'.');
        }
    }

    /** @return array<string, mixed> */
    private function anomaly(string $severity, string $code, ?int $transactionId, ?int $packageId, string $message): array
    {
        return [
            'severity' => $severity,
            'code' => $code,
            'transaction_id' => $transactionId,
            'package_id' => $packageId,
            'message' => $message,
        ];
    }
}
