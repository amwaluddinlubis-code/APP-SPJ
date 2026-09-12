<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class ArkasActivityHierarchyResolver
{
    /** @var array<string,array{program_code:string,program_name:string,sub_program_code:string,sub_program_name:string}> */
    private array $cache = [];

    /**
     * Resolve hirarki Program -> Sub Program untuk transaksi ARKAS tanpa
     * mengubah source transaction. Nama parent berasal dari payload RKAS yang
     * sudah disinkronkan; kode mempunyai fallback deterministik dari kode kegiatan.
     *
     * @return array{program_code:string,program_name:string,sub_program_code:string,sub_program_name:string}
     */
    public function resolve(Transaction $transaction): array
    {
        $fallback = $this->codesForActivity((string) $transaction->activity_code);
        $resolved = [
            'program_code' => $fallback['program_code'],
            'program_name' => '',
            'sub_program_code' => $fallback['sub_program_code'],
            'sub_program_name' => '',
        ];

        $sourceKasId = trim((string) $transaction->id_kas_umum);
        if ($sourceKasId === '') {
            return $resolved;
        }

        $cacheKey = (string) $transaction->fiscal_year_id.'|'.$sourceKasId.'|'.(string) $transaction->activity_code;
        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        $bkuRow = DB::connection('school')->table('arkas_bku_rows')
            ->where('fiscal_year_id', $transaction->fiscal_year_id)
            ->where('source_kas_id', $sourceKasId)
            ->first(['payload']);
        $bkuPayload = $this->decodePayload($bkuRow->payload ?? null);
        $sourceRapbsId = $this->firstText($bkuPayload, ['ID_RAPBS', 'id_rapbs']);

        if ($sourceRapbsId === null) {
            return $this->cache[$cacheKey] = $resolved;
        }

        $rkasRow = DB::connection('school')->table('arkas_rkas_items')
            ->where('fiscal_year_id', $transaction->fiscal_year_id)
            ->where('source_rapbs_id', $sourceRapbsId)
            ->first(['payload']);
        $rkasPayload = $this->decodePayload($rkasRow->payload ?? null);

        $resolved['program_code'] = $this->firstText($rkasPayload, ['KODE_PROGRAM', 'kode_program']) ?? $resolved['program_code'];
        $resolved['program_name'] = $this->firstText($rkasPayload, ['NAMA_PROGRAM', 'nama_program']) ?? '';
        $resolved['sub_program_code'] = $this->firstText($rkasPayload, ['KODE_SUB_PROGRAM', 'kode_sub_program']) ?? $resolved['sub_program_code'];
        $resolved['sub_program_name'] = $this->firstText($rkasPayload, ['NAMA_SUB_PROGRAM', 'nama_sub_program']) ?? '';

        return $this->cache[$cacheKey] = $resolved;
    }

    /**
     * @return array{program_code:string,sub_program_code:string}
     */
    public function codesForActivity(string $activityCode): array
    {
        $raw = trim($activityCode);
        $normalized = trim($raw, '.');
        $parts = array_values(array_filter(
            array_map('trim', explode('.', $normalized)),
            static fn (string $part): bool => $part !== ''
        ));
        $trailingDot = str_ends_with($raw, '.') ? '.' : '';

        return [
            'program_code' => count($parts) >= 1 ? $parts[0].$trailingDot : '',
            'sub_program_code' => count($parts) >= 2 ? $parts[0].'.'.$parts[1].$trailingDot : '',
        ];
    }

    /** @return array<string,mixed> */
    private function decodePayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }
        if (is_object($payload)) {
            return (array) $payload;
        }
        if (! is_string($payload) || trim($payload) === '') {
            return [];
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (\JsonException) {
            return [];
        }
    }

    /** @param array<string,mixed> $payload @param array<int,string> $keys */
    private function firstText(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }
            $value = trim((string) ($payload[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
