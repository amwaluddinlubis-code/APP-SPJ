<?php

namespace App\Services;

use App\Models\School;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;

final class SpjOverlayMigrationService
{
    /**
     * Build read-only decision-support artifacts for unresolved overlay links.
     *
     * @return array{json:string,csv:string,ambiguous:int,unmatched:int,possible_manual_lookup:int,truly_missing_or_unverified:int}
     */
    public function decisionSupport(School $school, string $sourceSql, ?string $outputDirectory = null): array
    {
        if (! File::isFile($sourceSql)) {
            throw new RuntimeException('Ekspor SQL lama tidak ditemukan: '.$sourceSql);
        }

        $targetPath = (string) ($school->databaseRecord?->database_path ?? '');
        if (! File::isFile($targetPath)) {
            throw new RuntimeException('Database tenant tidak ditemukan: '.$targetPath);
        }

        $source = new PDO('sqlite::memory:');
        $source->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $source->exec((string) File::get($sourceSql));
        $this->assertSourceTables($source);

        $oldTransactions = array_values($this->oldTransactionRows($source));
        $oldMaps = $this->oldTransactions($source);
        $db = DB::connection('school');
        $rawRows = $db->table('arkas_raw_mirror_rows')->get()->keyBy('id');
        $freshTransactions = $db->table('spj_fresh_transactions')->orderBy('id')->get();
        $ambiguous = [];
        $unmatched = [];

        foreach ($freshTransactions as $fresh) {
            $raw = $rawRows->get($fresh->raw_mirror_row_id);
            $payload = $raw ? (json_decode((string) $raw->payload, true) ?: []) : [];
            $match = $this->resolveTransaction($fresh, $payload, $oldMaps);
            if ($match['status'] === 'ambiguous') {
                $ambiguous[] = $this->decisionRecord($fresh, $payload, $match);
            } elseif ($match['status'] === 'unmatched') {
                $unmatched[] = $this->unmatchedDecisionRecord($fresh, $payload, $oldTransactions);
            }
        }

        $directory = $outputDirectory ?: storage_path('app/overlay-migration-reports/'.preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $school->npsn));
        File::ensureDirectoryExists($directory);
        $stamp = now()->format('Ymd_His');
        $base = $directory.'/'.$stamp.'_decision_support';
        $jsonPath = $base.'.json';
        $csvPath = $base.'.csv';
        $possibleManualLookup = count(array_filter($unmatched, static fn (array $row): bool => $row['classification'] === 'possible_manual_lookup'));
        $trulyMissing = count($unmatched) - $possibleManualLookup;
        $artifact = [
            'schema_version' => 1,
            'mode' => 'read-only',
            'school_id' => $school->id,
            'school_npsn' => $school->npsn,
            'source_sql' => $sourceSql,
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'ambiguous' => count($ambiguous),
                'unmatched' => count($unmatched),
                'possible_manual_lookup' => $possibleManualLookup,
                'truly_missing_or_unverified' => $trulyMissing,
                'winner_selection' => 'none',
            ],
            'ambiguous' => $ambiguous,
            'unmatched' => $unmatched,
            'mapping_template' => [
                'old_transaction_id' => null,
                'fresh_transaction_id' => null,
                'decision' => 'PENDING',
                'reason' => null,
            ],
        ];
        File::put($jsonPath, json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $handle = fopen($csvPath, 'wb');
        fputcsv($handle, ['status', 'fresh_transaction_id', 'old_transaction_id', 'fresh_no_bukti', 'old_no_bukti', 'fresh_date', 'old_date', 'fresh_amount', 'old_amount', 'classification', 'reason']);
        foreach ($ambiguous as $row) {
            foreach ($row['old_candidates'] as $candidate) {
                fputcsv($handle, ['ambiguous', $row['fresh_transaction']['id'], $candidate['id'], $row['fresh_transaction']['no_bukti'], $candidate['no_bukti'], $row['fresh_transaction']['date'], $candidate['date'], $row['fresh_transaction']['amount'], $candidate['amount'], 'manual_decision', $row['reason']]);
            }
        }
        foreach ($unmatched as $row) {
            fputcsv($handle, ['unmatched', $row['fresh_transaction']['id'], null, $row['fresh_transaction']['no_bukti'], null, $row['fresh_transaction']['date'], null, $row['fresh_transaction']['amount'], null, $row['classification'], $row['reason']]);
        }
        fclose($handle);

        return ['json' => $jsonPath, 'csv' => $csvPath, 'ambiguous' => count($ambiguous), 'unmatched' => count($unmatched), 'possible_manual_lookup' => $possibleManualLookup, 'truly_missing_or_unverified' => $trulyMissing];
    }

    /**
     * Validate explicit manual mappings without applying them.
     *
     * @return array{valid:bool,errors:array<int,string>,accepted:int,report:string}
     */
    public function validateMapping(School $school, string $sourceSql, string $mappingFile, ?string $outputDirectory = null): array
    {
        if (! File::isFile($mappingFile)) {
            throw new RuntimeException('Mapping file tidak ditemukan: '.$mappingFile);
        }

        $decoded = strtolower((string) pathinfo($mappingFile, PATHINFO_EXTENSION)) === 'csv'
            ? $this->readMappingCsv($mappingFile)
            : json_decode((string) File::get($mappingFile), true);
        $mappings = is_array($decoded) && array_key_exists('mappings', $decoded) ? $decoded['mappings'] : $decoded;
        if (! is_array($mappings)) {
            throw new RuntimeException('Mapping file harus berupa JSON array atau object dengan key mappings.');
        }

        $source = new PDO('sqlite::memory:');
        $source->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $source->exec((string) File::get($sourceSql));
        $this->assertSourceTables($source);
        $oldRows = collect($this->oldTransactionRows($source))->keyBy(fn (array $row): int => (int) $row['id']);
        $db = DB::connection('school');
        $freshRows = $db->table('spj_fresh_transactions')->get()->keyBy('id');
        $rawRows = $db->table('arkas_raw_mirror_rows')->get()->keyBy('id');
        $oldMaps = $this->oldTransactions($source);
        $deterministicFresh = [];
        foreach ($freshRows as $fresh) {
            $raw = $rawRows->get($fresh->raw_mirror_row_id);
            $match = $this->resolveTransaction($fresh, $raw ? (json_decode((string) $raw->payload, true) ?: []) : [], $oldMaps);
            if ($match['status'] === 'matched') {
                $deterministicFresh[(int) $fresh->id] = (int) $match['transaction']['id'];
            }
        }

        $errors = [];
        $seenOld = [];
        $seenFresh = [];
        foreach ($mappings as $index => $mapping) {
            $line = (int) $index + 1;
            $oldId = (int) ($mapping['old_transaction_id'] ?? 0);
            $freshId = (int) ($mapping['fresh_transaction_id'] ?? 0);
            if (array_key_exists('school_id', $mapping) && (int) $mapping['school_id'] !== (int) $school->id) {
                $errors[] = "Mapping {$line}: school_id tidak sama dengan tenant command.";
            }
            if (($mapping['decision'] ?? '') !== 'APPROVE') {
                $errors[] = "Mapping {$line}: decision harus APPROVE.";
            }
            if (! $oldRows->has($oldId)) {
                $errors[] = "Mapping {$line}: old_transaction_id {$oldId} tidak ada pada source.";
            }
            if (! $freshRows->has($freshId)) {
                $errors[] = "Mapping {$line}: fresh_transaction_id {$freshId} tidak ada pada tenant.";
            }
            if (isset($seenOld[$oldId])) {
                $errors[] = "Mapping {$line}: old_transaction_id {$oldId} duplikat.";
            }
            if (isset($seenFresh[$freshId])) {
                $errors[] = "Mapping {$line}: fresh_transaction_id {$freshId} duplikat.";
            }
            if (isset($deterministicFresh[$freshId])) {
                $errors[] = "Mapping {$line}: fresh_transaction_id {$freshId} sudah matched deterministik.";
            }
            $seenOld[$oldId] = true;
            $seenFresh[$freshId] = true;
        }

        $directory = $outputDirectory ?: storage_path('app/overlay-migration-reports/'.preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $school->npsn));
        File::ensureDirectoryExists($directory);
        $report = $directory.'/'.now()->format('Ymd_His').'_mapping_validation.json';
        File::put($report, json_encode(['mode' => 'read-only', 'school_id' => $school->id, 'valid' => $errors === [], 'accepted' => $errors === [] ? count($mappings) : 0, 'errors' => $errors], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return ['valid' => $errors === [], 'errors' => $errors, 'accepted' => $errors === [] ? count($mappings) : 0, 'report' => $report];
    }

    /** @return array<int, array<string, mixed>> */
    private function readMappingCsv(string $mappingFile): array
    {
        $handle = fopen($mappingFile, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Mapping CSV tidak dapat dibaca.');
        }
        $headers = fgetcsv($handle);
        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            if ($headers === false || count(array_filter($values, static fn (mixed $value): bool => $value !== null && $value !== '')) === 0) {
                continue;
            }
            $rows[] = array_combine($headers, array_pad($values, count($headers), null));
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @return array{mode:string,matched:int,unmatched:int,ambiguous:int,items:int,packages:int,fields_migrated:int,fields_preserved:int,backup:?string,report:string,matched_mappings:array<int,array<string,mixed>>,unresolved:array<int,array<string,mixed>>}
     */
    public function migrate(School $school, string $sourceSql, bool $execute = false): array
    {
        if (! File::isFile($sourceSql)) {
            throw new RuntimeException('Ekspor SQL lama tidak ditemukan: '.$sourceSql);
        }

        $targetPath = (string) ($school->databaseRecord?->database_path ?? '');
        if (! File::isFile($targetPath)) {
            throw new RuntimeException('Database tenant tidak ditemukan: '.$targetPath);
        }

        $reportDirectory = storage_path('app/overlay-migration-reports/'.preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $school->npsn));
        File::ensureDirectoryExists($reportDirectory);
        $summary = [
            'mode' => $execute ? 'execute' : 'dry-run',
            'matched' => 0,
            'unmatched' => 0,
            'ambiguous' => 0,
            'items' => 0,
            'packages' => 0,
            'fields_migrated' => 0,
            'fields_preserved' => 0,
            'backup' => null,
            'matched_mappings' => [],
            'unresolved' => [],
        ];

        // The SQL dump is read-only input. Keep its transient database in memory
        // so this command does not depend on storage-directory write permission.
        $source = new PDO('sqlite::memory:');
        $source->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $source->exec((string) File::get($sourceSql));
        $this->assertSourceTables($source);

        $oldTransactions = $this->oldTransactions($source);
        $oldItems = $this->oldItems($source);
        $oldPackages = $this->oldPackages($source);
        $db = DB::connection('school');
        $rawRows = $db->table('arkas_raw_mirror_rows')->get()->keyBy('id');
        $transactions = $db->table('spj_fresh_transactions')->get();
        $updates = [];
        $itemUpdates = [];
        $packageUpdates = [];
        foreach ($transactions as $transaction) {
            $raw = $rawRows->get($transaction->raw_mirror_row_id);
            $payload = $raw ? (json_decode((string) $raw->payload, true) ?: []) : [];
            $match = $this->resolveTransaction($transaction, $payload, $oldTransactions);
            if ($match['status'] === 'ambiguous') {
                $summary['ambiguous']++;
                $summary['unresolved'][] = $this->unresolvedRecord($transaction, $payload, $match);

                continue;
            }
            if ($match['transaction'] === null) {
                $summary['unmatched']++;
                $summary['unresolved'][] = $this->unresolvedRecord($transaction, $payload, $match);

                continue;
            }
            $summary['matched']++;
            $old = $match['transaction'];
            $summary['matched_mappings'][] = [
                'fresh_transaction_id' => $transaction->id,
                'old_transaction_id' => $old['id'] ?? null,
                'reason' => $match['reason'],
                'confidence' => 'deterministic',
            ];
            $updates[] = [$transaction, $old];
            foreach ($oldItems[(int) ($old['id'] ?? 0)] ?? [] as $item) {
                $itemUpdates[] = [$transaction->id, $item];
            }
            if (isset($oldPackages[(int) $old['id']])) {
                $packageUpdates[] = [$transaction->id, $oldPackages[(int) $old['id']]];
            }
        }
        $summary['items'] = count($itemUpdates);
        $summary['packages'] = count($packageUpdates);

        if ($execute) {
            $backup = $targetPath.'.before-overlay-migration-'.now()->format('Ymd_His').'.sqlite';
            File::copy($targetPath, $backup);
            $summary['backup'] = $backup;
            $db->transaction(function () use ($db, $updates, $itemUpdates, $packageUpdates, &$summary): void {
                foreach ($updates as [$transaction, $old]) {
                    $attributes = [];
                    foreach ([
                        'spj_category', 'payment_description', 'payment_method',
                        'payment_reference', 'receipt_recipient_name',
                    ] as $field) {
                        $oldValue = $old[$field] ?? null;
                        if (blank($transaction->{$field}) && filled($oldValue)) {
                            $attributes[$field] = $oldValue;
                            $summary['fields_migrated']++;
                        } elseif (filled($transaction->{$field}) && filled($oldValue)) {
                            $summary['fields_preserved']++;
                        }
                    }
                    if ((bool) ($old['requires_reconciliation'] ?? false) && ! $transaction->requires_reconciliation) {
                        $attributes['requires_reconciliation'] = true;
                        $summary['fields_migrated']++;
                    }
                    if ($attributes !== []) {
                        $db->table('spj_fresh_transactions')->where('id', $transaction->id)->update($attributes + ['updated_at' => now()]);
                    }
                }
                foreach ($itemUpdates as [$transactionId, $item]) {
                    $targetItem = $db->table('spj_fresh_transaction_items')
                        ->where('spj_fresh_transaction_id', $transactionId)
                        ->where('source_key', (string) ($item['source_item_id'] ?? ''))
                        ->first()
                        ?: $db->table('spj_fresh_transaction_items')
                            ->where('spj_fresh_transaction_id', $transactionId)
                            ->orderBy('sort_order')
                            ->first();
                    if ($targetItem !== null) {
                        if (blank($targetItem->item_description) && filled($item['item_description'] ?? null)) {
                            $db->table('spj_fresh_transaction_items')->where('id', $targetItem->id)->update([
                                'item_description' => $item['item_description'],
                                'updated_at' => now(),
                            ]);
                            $summary['fields_migrated']++;
                        } elseif (filled($targetItem->item_description) && filled($item['item_description'] ?? null)) {
                            $summary['fields_preserved']++;
                        }
                    }
                }
                foreach ($packageUpdates as [$transactionId, $package]) {
                    $existing = $db->table('spj_fresh_packages')->where('spj_fresh_transaction_id', $transactionId)->first();
                    if ($existing) {
                        $attributes = [];
                        foreach ([
                            'quarter_code', 'semester_code', 'phase_code', 'document_number',
                            'numbered_at', 'generated_at', 'finalized_at', 'finalized_by',
                            'cancelled_at', 'cancelled_by', 'cancellation_reason', 'snapshot',
                        ] as $field) {
                            if (blank($existing->{$field}) && filled($package[$field] ?? null)) {
                                $attributes[$field] = $package[$field];
                            }
                        }
                        if (($existing->status ?? 'DRAFT') === 'DRAFT' && ($package['status'] ?? 'DRAFT') !== 'DRAFT') {
                            $attributes['status'] = $package['status'];
                        }
                        if ($attributes !== []) {
                            $db->table('spj_fresh_packages')->where('id', $existing->id)->update($attributes + ['updated_at' => now()]);
                        }
                    } else {
                        $db->table('spj_fresh_packages')->insert([
                            'quarter_code' => $package['quarter_code'] ?? null,
                            'semester_code' => $package['semester_code'] ?? null,
                            'phase_code' => $package['phase_code'] ?? null,
                            'status' => $package['status'] ?? 'DRAFT',
                            'document_number' => $package['document_number'] ?? null,
                            'numbered_at' => $package['numbered_at'] ?? null,
                            'generated_at' => $package['generated_at'] ?? null,
                            'finalized_at' => $package['finalized_at'] ?? null,
                            'finalized_by' => $package['finalized_by'] ?? null,
                            'cancelled_at' => $package['cancelled_at'] ?? null,
                            'cancelled_by' => $package['cancelled_by'] ?? null,
                            'cancellation_reason' => $package['cancellation_reason'] ?? null,
                            'snapshot' => $package['snapshot'] ?? null,
                            'spj_fresh_transaction_id' => $transactionId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });
        }

        $reportPath = $reportDirectory.'/'.now()->format('Ymd_His').'_'.Str::random(8).'.json';
        $summary['report'] = $reportPath;
        File::put($reportPath, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $summary;
    }

    private function assertSourceTables(PDO $source): void
    {
        foreach (['transactions', 'transaction_items', 'spj_packages'] as $table) {
            if ($source->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ".$source->quote($table))->fetchColumn() === false) {
                throw new RuntimeException('Tabel '.$table.' tidak ada pada ekspor SQL lama.');
            }
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function oldTransactions(PDO $source): array
    {
        $rows = $source->query('SELECT * FROM transactions')->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            foreach (array_filter([(string) ($row['id_kas_umum'] ?? ''), (string) ($row['source_key'] ?? '')]) as $key) {
                $result['key:'.$key][] = $row;
            }
            $result['proof-date:'.trim((string) ($row['no_bukti'] ?? '')).':'.(string) ($row['transaction_date'] ?? '')][] = $row;
            $result['proof:'.trim((string) ($row['no_bukti'] ?? ''))][] = $row;
        }

        return $result;
    }

    /** @return array<int, array<string, mixed>> */
    private function oldTransactionRows(PDO $source): array
    {
        return $source->query('SELECT * FROM transactions')->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param array<string, mixed> $payload @param array{status:string,transaction:?array<string,mixed>,reason:string,candidate_old_transaction_ids:array<int,int>,candidate_old_transactions:array<int,array<string,mixed>>} $match @return array<string, mixed> */
    private function decisionRecord(object $fresh, array $payload, array $match): array
    {
        return [
            'reason' => $match['reason'],
            'fresh_transaction' => $this->freshDecisionFields($fresh, $payload),
            'old_candidates' => array_map(fn (array $candidate): array => $this->oldDecisionFields($candidate), $match['candidate_old_transactions']),
            'winner' => null,
        ];
    }

    /** @param array<string, mixed> $payload @param array<int, array<string, mixed>> $oldTransactions @return array<string, mixed> */
    private function unmatchedDecisionRecord(object $fresh, array $payload, array $oldTransactions): array
    {
        $freshFields = $this->freshDecisionFields($fresh, $payload);
        $possible = array_values(array_filter($oldTransactions, function (array $old) use ($freshFields): bool {
            $sameDate = $freshFields['date'] !== null && $freshFields['date'] === ($old['transaction_date'] ?? null);
            $sameAmount = $freshFields['amount'] !== null && (string) $freshFields['amount'] === (string) ($old['amount'] ?? '');

            return $sameDate && $sameAmount;
        }));

        return [
            'reason' => 'no deterministic candidate',
            'fresh_transaction' => $freshFields,
            'raw_search_context' => [
                'identifiers' => array_filter(['id_kas_umum' => $freshFields['raw_id_kas_umum'], 'source_key' => $freshFields['source_key'], 'no_bukti' => $freshFields['no_bukti']]),
                'date' => $freshFields['date'],
                'amount' => $freshFields['amount'],
                'description' => $freshFields['description'],
            ],
            'possible_manual_candidates' => array_map(fn (array $candidate): array => $this->oldDecisionFields($candidate), $possible),
            'classification' => $possible === [] ? 'truly_missing_or_unverified' : 'possible_manual_lookup',
            'winner' => null,
        ];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function freshDecisionFields(object $fresh, array $payload): array
    {
        return [
            'id' => (int) $fresh->id,
            'source_key' => $fresh->source_key,
            'no_bukti' => $payload['no_bukti'] ?? null,
            'date' => $payload['tanggal_transaksi'] ?? $payload['tanggal'] ?? null,
            'amount' => $payload['saldo'] ?? $payload['jumlah'] ?? $payload['nilai'] ?? $payload['nominal'] ?? null,
            'description' => $payload['uraian'] ?? $payload['description'] ?? null,
            'raw_id_kas_umum' => $payload['id_kas_umum'] ?? null,
            'raw_identifiers' => $payload,
        ];
    }

    /** @param array<string, mixed> $old @return array<string, mixed> */
    private function oldDecisionFields(array $old): array
    {
        return [
            'id' => (int) ($old['id'] ?? 0),
            'no_bukti' => $old['no_bukti'] ?? null,
            'date' => $old['transaction_date'] ?? null,
            'amount' => $old['amount'] ?? null,
            'description' => $old['payment_description'] ?? $old['description'] ?? null,
            'source_key' => $old['source_key'] ?? null,
            'raw_identifiers' => array_filter(['id_kas_umum' => $old['id_kas_umum'] ?? null, 'source_key' => $old['source_key'] ?? null]),
        ];
    }

    /** @return array<int, array<int, array<string, mixed>>> */
    private function oldItems(PDO $source): array
    {
        $result = [];
        foreach ($source->query('SELECT * FROM transaction_items')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[(int) $row['transaction_id']][] = $row;
        }

        return $result;
    }

    /** @return array<int, array<string, mixed>> */
    private function oldPackages(PDO $source): array
    {
        $result = [];
        foreach ($source->query('SELECT * FROM spj_packages')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[(int) $row['transaction_id']] = $row;
        }

        return $result;
    }

    /** @param array<string, mixed> $payload @param array<int, array<string, mixed>> $maps @return array{status:string,transaction:?array<string,mixed>,reason:string,candidate_old_transaction_ids:array<int,int>,candidate_old_transactions:array<int,array<string,mixed>>} */
    private function resolveTransaction(object $transaction, array $payload, array $maps): array
    {
        foreach ([(string) ($payload['id_kas_umum'] ?? ''), (string) $transaction->source_key] as $key) {
            if ($key !== '' && count($maps['key:'.$key] ?? []) === 1) {
                return ['status' => 'matched', 'transaction' => $maps['key:'.$key][0], 'reason' => 'unique id_kas_umum/source_key', 'candidate_old_transaction_ids' => [(int) $maps['key:'.$key][0]['id']], 'candidate_old_transactions' => [$maps['key:'.$key][0]]];
            }
        }
        $proof = trim((string) ($payload['no_bukti'] ?? ''));
        $date = (string) ($payload['tanggal_transaksi'] ?? '');
        $candidates = $maps['proof-date:'.$proof.':'.$date] ?? [];
        if (count($candidates) === 1) {
            return ['status' => 'matched', 'transaction' => $candidates[0], 'reason' => 'unique no_bukti+tanggal', 'candidate_old_transaction_ids' => [(int) $candidates[0]['id']], 'candidate_old_transactions' => [$candidates[0]]];
        }
        if (count($candidates) > 1) {
            return ['status' => 'ambiguous', 'transaction' => null, 'reason' => 'duplicate no_bukti+tanggal', 'candidate_old_transaction_ids' => array_map(fn (array $candidate): int => (int) $candidate['id'], $candidates), 'candidate_old_transactions' => $candidates];
        }
        $candidates = $maps['proof:'.$proof] ?? [];

        return count($candidates) === 1
            ? ['status' => 'matched', 'transaction' => $candidates[0], 'reason' => 'unique no_bukti', 'candidate_old_transaction_ids' => [(int) $candidates[0]['id']], 'candidate_old_transactions' => [$candidates[0]]]
            : ['status' => count($candidates) > 1 ? 'ambiguous' : 'unmatched', 'transaction' => null, 'reason' => count($candidates) > 1 ? 'duplicate no_bukti' : 'no deterministic candidate', 'candidate_old_transaction_ids' => array_map(fn (array $candidate): int => (int) $candidate['id'], $candidates), 'candidate_old_transactions' => $candidates];
    }

    /** @param array<string, mixed> $payload @param array{status:string,transaction:?array<string,mixed>,reason:string,candidate_old_transaction_ids:array<int,int>,candidate_old_transactions:array<int,array<string,mixed>>} $match @return array<string,mixed> */
    private function unresolvedRecord(object $transaction, array $payload, array $match): array
    {
        return [
            'fresh_transaction_id' => $transaction->id,
            'fresh_source_key' => $transaction->source_key,
            'raw_id_kas_umum' => $payload['id_kas_umum'] ?? null,
            'raw_no_bukti' => $payload['no_bukti'] ?? null,
            'raw_tanggal_transaksi' => $payload['tanggal_transaksi'] ?? null,
            'status' => $match['status'],
            'reason' => $match['reason'],
            'candidate_old_transaction_ids' => $match['candidate_old_transaction_ids'],
            'candidate_old_transactions' => array_map(static fn (array $candidate): array => array_intersect_key($candidate, array_flip(['id', 'id_kas_umum', 'source_key', 'no_bukti', 'transaction_date', 'amount'])), $match['candidate_old_transactions']),
            'classification' => $match['status'] === 'ambiguous' ? 'manual_decision' : 'unmatched_requires_source_audit',
        ];
    }
}
