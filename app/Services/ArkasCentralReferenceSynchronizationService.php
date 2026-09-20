<?php

namespace App\Services;

use App\Models\ArkasSource;
use App\Models\School;
use Illuminate\Support\Facades\Log;

final class ArkasCentralReferenceSynchronizationService
{
    public function __construct(
        private readonly ArkasBridgeClient $bridge,
        private readonly ArkasDatabaseExplorer $explorer,
        private readonly ArkasPersistentReferenceAuthority $authority,
    ) {}

    /** @return array{ref_kode:array{table:string, rows:int, accepted:int, quarantined:int, release:string}, ref_rekening:array{table:string, rows:int, accepted:int, quarantined:int, release:string}, ref_acuan_barang:array{table:string, rows:int, accepted:int, quarantined:int, release:string}} */
    public function synchronizeCodeReference(School $school, ArkasSource $source): array
    {
        $release = (string) config('arkas.reference_release', '2026.09');
        $codeResult = $this->promoteCodeVariants($school, $source, $release);
        $accountResult = $this->promoteVersionedTable($source, 'ref_rekening', $release);
        $itemResult = $this->promoteVersionedTable($source, 'ref_acuan_barang', $release);

        Log::info('ARKAS central ref_kode synchronization completed.', [
            'school_id' => $school->id,
            'source_id' => $source->id,
            'ref_kode' => $codeResult,
            'ref_rekening' => $accountResult,
            'ref_acuan_barang' => $itemResult,
            'release' => $release,
        ]);

        return [
            'ref_kode' => $codeResult,
            'ref_rekening' => $accountResult,
            'ref_acuan_barang' => $itemResult,
        ];
    }

    /** @return array{table:string, rows:int, accepted:int, quarantined:int, release:string} */
    private function promoteCodeVariants(School $school, ArkasSource $source, string $release): array
    {
        $normalized = $this->normalizedRows('ref_kode', $this->fetchRows($source, 'ref_kode'));
        $result = $this->authority->promoteCodeVariants((string) $school->id, $normalized, $release, (string) $source->id);

        return ['table' => 'ref_kode', 'rows' => count($normalized), 'accepted' => $result['accepted'], 'quarantined' => $result['quarantined'], 'release' => $release];
    }

    /** @return array{table:string, rows:int, accepted:int, quarantined:int, release:string} */
    private function promoteVersionedTable(ArkasSource $source, string $table, string $release): array
    {
        if (! in_array($table, $this->explorer->tables($source), true)) {
            return ['table' => $table, 'rows' => 0, 'accepted' => 0, 'quarantined' => 0, 'release' => $release];
        }

        $normalized = $this->normalizedRows($table, $this->fetchRows($source, $table));
        $result = $this->authority->promote($table, $normalized, $release, [], (string) $source->id);

        return ['table' => $table, 'rows' => count($normalized), 'accepted' => $result['accepted'], 'quarantined' => $result['quarantined'], 'release' => $release];
    }

    /** @param array<int, array<string, mixed>> $rows @return array<int, array<string, mixed>> */
    private function normalizedRows(string $table, array $rows): array
    {
        $schema = ArkasReferenceCentralSchema::for($table);
        $allowed = $schema['semantic_columns'];
        if ($table === 'ref_kode') {
            $allowed = [...$allowed, ...($schema['extension_columns'] ?? [])];
        }

        return array_map(function (array $row) use ($allowed): array {
            $row = array_change_key_case($row, CASE_LOWER);
            if (isset($row['nama_rekening']) && ! isset($row['rekening'])) {
                $row['rekening'] = $row['nama_rekening'];
            }
            if (isset($row['uraian_rekening']) && ! isset($row['rekening'])) {
                $row['rekening'] = $row['uraian_rekening'];
            }
            if (isset($row['kode']) && ! isset($row['kode_rekening'])) {
                $row['kode_rekening'] = $row['kode'];
            }

            return array_intersect_key($row, array_flip($allowed));
        }, $rows);
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchRows(ArkasSource $source, string $table): array
    {
        $records = [];
        $offset = 0;
        $pageSize = 5000;

        do {
            $page = ArkasPipePayload::decode(
                $this->bridge->execute($source, 'rows', null, $table, null, $pageSize, $offset),
                'rows:'.$table,
            );
            $records = [...$records, ...$page];
            $offset += count($page);
        } while (count($page) === $pageSize);

        return $records;
    }
}
