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
     * @return array{mode:string,matched:int,unmatched:int,ambiguous:int,items:int,packages:int,fields_migrated:int,fields_preserved:int,backup:?string,report:string}
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

        $tempPath = storage_path('.overlay-migration-'.Str::uuid().'.sqlite');
        File::ensureDirectoryExists(dirname($tempPath));
        $reportDirectory = storage_path('app/overlay-migration-reports/'.preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $school->npsn));
        File::ensureDirectoryExists($reportDirectory);
        $attached = false;
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
        ];

        try {
            $source = new PDO('sqlite:'.$tempPath);
            $source->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $source->exec((string) File::get($sourceSql));
            $this->assertSourceTables($source);

            $oldTransactions = $this->oldTransactions($source);
            $oldItems = $this->oldItems($source);
            $oldPackages = $this->oldPackages($source);
            $db = DB::connection('school');
            $db->getPdo()->exec('ATTACH DATABASE '.$db->getPdo()->quote($tempPath).' AS old_overlay');
            $attached = true;

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

                    continue;
                }
                if ($match['transaction'] === null) {
                    $summary['unmatched']++;

                    continue;
                }
                $summary['matched']++;
                $old = $match['transaction'];
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
        } finally {
            if ($attached) {
                try {
                    DB::connection('school')->getPdo()->exec('DETACH DATABASE old_overlay');
                } catch (\Throwable) {
                }
            }
            if (File::exists($tempPath)) {
                File::delete($tempPath);
            }
        }
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

    /** @param array<string, mixed> $payload @param array<int, array<string, mixed>> $maps @return array{status:string,transaction:?array<string,mixed>} */
    private function resolveTransaction(object $transaction, array $payload, array $maps): array
    {
        foreach ([(string) ($payload['id_kas_umum'] ?? ''), (string) $transaction->source_key] as $key) {
            if ($key !== '' && count($maps['key:'.$key] ?? []) === 1) {
                return ['status' => 'matched', 'transaction' => $maps['key:'.$key][0]];
            }
        }
        $proof = trim((string) ($payload['no_bukti'] ?? ''));
        $date = (string) ($payload['tanggal_transaksi'] ?? '');
        $candidates = $maps['proof-date:'.$proof.':'.$date] ?? [];
        if (count($candidates) === 1) {
            return ['status' => 'matched', 'transaction' => $candidates[0]];
        }
        if (count($candidates) > 1) {
            return ['status' => 'ambiguous', 'transaction' => null];
        }
        $candidates = $maps['proof:'.$proof] ?? [];

        return count($candidates) === 1
            ? ['status' => 'matched', 'transaction' => $candidates[0]]
            : ['status' => count($candidates) > 1 ? 'ambiguous' : 'unmatched', 'transaction' => null];
    }
}
