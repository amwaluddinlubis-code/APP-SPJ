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
     * @return array{mode:string,matched:int,unmatched:int,ambiguous:int,items:int,packages:int,backup:?string,report:string}
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
                $updates[] = [$transaction->id, $old];
                foreach ($oldItems[(int) $old['id'] ?? 0] ?? [] as $item) {
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
                $db->transaction(function () use ($db, $updates, $itemUpdates, $packageUpdates): void {
                    foreach ($updates as [$id, $old]) {
                        $db->table('spj_fresh_transactions')->where('id', $id)->update([
                            'spj_category' => $old['spj_category'],
                            'payment_description' => $old['payment_description'],
                            'payment_method' => $old['payment_method'],
                            'payment_reference' => $old['payment_reference'],
                            'receipt_recipient_name' => $old['receipt_recipient_name'],
                            'requires_reconciliation' => $old['requires_reconciliation'],
                            'updated_at' => now(),
                        ]);
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
                            $db->table('spj_fresh_transaction_items')->where('id', $targetItem->id)->update([
                                'item_description' => $item['item_description'],
                                'updated_at' => now(),
                            ]);
                        }
                    }
                    foreach ($packageUpdates as [$transactionId, $package]) {
                        $attributes = [
                            'quarter_code' => $package['quarter_code'], 'semester_code' => $package['semester_code'],
                            'phase_code' => $package['phase_code'], 'status' => $package['status'],
                            'document_number' => $package['document_number'], 'numbered_at' => $package['numbered_at'],
                            'generated_at' => $package['generated_at'], 'finalized_at' => $package['finalized_at'],
                            'finalized_by' => $package['finalized_by'], 'cancelled_at' => $package['cancelled_at'],
                            'cancelled_by' => $package['cancelled_by'], 'cancellation_reason' => $package['cancellation_reason'],
                            'snapshot' => $package['snapshot'], 'updated_at' => now(),
                        ];
                        $existing = $db->table('spj_fresh_packages')->where('spj_fresh_transaction_id', $transactionId)->first();
                        if ($existing) {
                            $db->table('spj_fresh_packages')->where('id', $existing->id)->update($attributes);
                        } else {
                            $db->table('spj_fresh_packages')->insert($attributes + ['spj_fresh_transaction_id' => $transactionId, 'created_at' => now()]);
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
