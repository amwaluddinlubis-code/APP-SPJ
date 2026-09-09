<?php

namespace App\Services;

use App\Models\ArkasImportProfile;
use App\Models\FiscalYear;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ArkasDomainAdapter
{
    /** @return array<string, string> */
    public static function targetDomains(): array
    {
        return ['raw' => 'Snapshot saja', 'rkas' => 'RKAS / RAPBS', 'rkas_periods' => 'Periode RKAS', 'bku' => 'BKU', 'activity_reference' => 'Referensi kegiatan', 'period_reference' => 'Referensi periode'];
    }

    /** @return array<string, string> */
    public static function mappingRoles(): array
    {
        return [
            'source_key' => 'Kunci', 'source_rapbs' => 'ID RAPBS', 'period' => 'Periode',
            'code' => 'Kode', 'name' => 'Nama', 'parent' => 'Induk', 'account_code' => 'Rekening',
            'description' => 'Uraian', 'amount' => 'Jumlah', 'volume' => 'Volume', 'category' => 'Kategori',
            'proof_number' => 'No bukti', 'transaction_date' => 'Tanggal transaksi', 'year' => 'Tahun',
            'fund_source' => 'Sumber dana',
        ];
    }

    /** @return array{target_domain:string, bridge_command:string, target_table:?string, mapping:array<string,string>, source_key_column:?string, description:string} */
    public static function presetFor(string $sourceTable): array
    {
        return match (strtolower($sourceTable)) {
            'kas_umum' => ['target_domain' => 'bku', 'bridge_command' => 'bku', 'target_table' => 'arkas_bku_rows', 'source_key_column' => 'id_kas_umum', 'mapping' => ['id_kas_umum' => 'source_key', 'parent_id_kas_umum' => 'parent', 'kategori_bku' => 'category', 'no_bukti' => 'proof_number', 'tanggal_transaksi' => 'transaction_date', 'jumlah' => 'amount', 'id_ref_sumber_dana' => 'fund_source'], 'description' => 'Sinkronisasi lama memakai Bridge bku, bukan rows kas_umum langsung.'],
            'rapbs' => ['target_domain' => 'rkas', 'bridge_command' => 'rkas', 'target_table' => 'arkas_rkas_items', 'source_key_column' => 'id_rapbs', 'mapping' => ['id_rapbs' => 'source_key', 'kode_rekening' => 'account_code', 'uraian' => 'description', 'jumlah' => 'amount', 'id_ref_sumber_dana' => 'fund_source'], 'description' => 'RKAS lama mengambil data rapbs melalui Bridge command rkas dan memperkaya kode kegiatan.'],
            'rapbs_periode' => ['target_domain' => 'rkas_periods', 'bridge_command' => 'rows', 'target_table' => 'arkas_rkas_periods', 'source_key_column' => 'id_rapbs', 'mapping' => ['id_rapbs' => 'source_rapbs', 'id_periode' => 'period', 'volume' => 'volume', 'jumlah' => 'amount'], 'description' => 'Detail periode dihubungkan ke RKAS berdasarkan ID_RAPBS dan ID_PERIODE.'],
            'ref_kode' => ['target_domain' => 'activity_reference', 'bridge_command' => 'rows', 'target_table' => 'activity_references', 'source_key_column' => 'id_kode', 'mapping' => ['id_kode' => 'code', 'uraian_kode' => 'name', 'parent_kode' => 'parent'], 'description' => 'Kode kegiatan dan nama hierarki dipakai sebagai referensi kegiatan.'],
            'ref_periode' => ['target_domain' => 'period_reference', 'bridge_command' => 'periods', 'target_table' => 'arkas_periods', 'source_key_column' => 'id_periode', 'mapping' => ['id_periode' => 'source_key', 'nama_periode' => 'name'], 'description' => 'Referensi periode dipakai untuk label bulan/triwulan/semester.'],
            'kas_umum_nota' => ['target_domain' => 'raw', 'bridge_command' => 'rows', 'target_table' => null, 'source_key_column' => 'id_kas_nota', 'mapping' => ['id_kas_nota' => 'source_key', 'id_kas_umum' => 'parent'], 'description' => 'Metadata nota disimpan sebagai snapshot agar dapat dipakai dokumen dan laporan tanpa mengubah transaksi operator.'],
            'kas_umum_nota_pajak' => ['target_domain' => 'raw', 'bridge_command' => 'rows', 'target_table' => null, 'source_key_column' => 'id_kas_nota_pajak', 'mapping' => ['id_kas_nota_pajak' => 'source_key', 'id_kas_nota' => 'parent'], 'description' => 'Rincian pajak nota disimpan sebagai snapshot sumber ARKAS.'],
            'ref_rekening' => ['target_domain' => 'raw', 'bridge_command' => 'rows', 'target_table' => null, 'source_key_column' => 'id_rekening', 'mapping' => ['id_rekening' => 'source_key', 'kode_rekening' => 'account_code', 'nama_rekening' => 'name'], 'description' => 'Master rekening disimpan sebagai snapshot untuk pengayaan RKAS dan laporan.'],
            'ref_level_kode' => ['target_domain' => 'raw', 'bridge_command' => 'rows', 'target_table' => null, 'source_key_column' => 'id_level_kode', 'mapping' => ['id_level_kode' => 'source_key'], 'description' => 'Tingkat hierarki kode disimpan sebagai snapshot referensi.'],
            default => ['target_domain' => 'raw', 'bridge_command' => 'rows', 'target_table' => null, 'source_key_column' => null, 'mapping' => [], 'description' => 'Belum ada preset domain; gunakan snapshot generik atau atur mapping manual.'],
        };
    }

    /** @param array<int, array{name:string}> $columns @param array<string, mixed> $preset @return array<string, string> */
    public static function initialMapping(array $columns, array $preset): array
    {
        $mapping = $preset['mapping'] ?? [];
        $aliases = [
            'source_key' => ['id', 'kode', 'key'], 'source_rapbs' => ['id_rapbs'], 'period' => ['periode', 'bulan'],
            'code' => ['kode'], 'name' => ['nama', 'name'], 'parent' => ['parent', 'induk', 'id_kas_umum', 'id_kas_nota'],
            'account_code' => ['kode_rekening', 'rekening'], 'description' => ['uraian', 'deskripsi'],
            'amount' => ['jumlah', 'nilai', 'nominal', 'saldo'], 'volume' => ['volume', 'qty', 'kuantitas'],
            'category' => ['kategori'], 'proof_number' => ['no_bukti', 'nomor_bukti'],
            'transaction_date' => ['tanggal', 'date'], 'year' => ['tahun', 'year'], 'fund_source' => ['sumber_dana', 'fund_source'],
        ];
        $usedRoles = array_values($mapping);
        foreach ($columns as $column) {
            $name = strtolower($column['name']);
            if (array_key_exists($column['name'], $mapping)) {
                continue;
            }
            foreach ($aliases as $role => $roleAliases) {
                if (in_array($role, $usedRoles, true)) {
                    continue;
                }
                if (in_array($name, $roleAliases, true) || str_contains($name, $role.'_')) {
                    $mapping[$column['name']] = $role;
                    $usedRoles[] = $role;
                    break;
                }
            }
        }

        return $mapping;
    }

    public static function targetTable(string $targetDomain): ?string
    {
        return match ($targetDomain) {
            'rkas' => 'arkas_rkas_items',
            'rkas_periods' => 'arkas_rkas_periods',
            'bku' => 'arkas_bku_rows',
            'activity_reference' => 'activity_references',
            'period_reference' => 'arkas_periods',
            default => null,
        };
    }

    /** @param array<int, array<string, mixed>> $records */
    public function synchronize(ArkasImportProfile $profile, FiscalYear $year, array $records): int
    {
        return match ($profile->target_domain) {
            'rkas' => $this->upsertRkas($profile, $year, $records),
            'rkas_periods' => $this->upsertPeriods($profile, $year, $records),
            'bku' => $this->upsertBku($profile, $year, $records),
            'activity_reference' => $this->upsertActivities($profile, $year, $records),
            'period_reference' => $this->upsertPeriodReferences($profile, $records),
            default => 0,
        };
    }

    /** @param array<int, array<string, mixed>> $records */
    private function upsertRkas(ArkasImportProfile $profile, FiscalYear $year, array $records): int
    {
        $db = DB::connection('school');
        $this->refresh($profile, $year, 'arkas_rkas_items', $db);
        $count = 0;
        foreach ($records as $record) {
            $key = $this->get($record, $profile, 'source_key', ['ID_RAPBS', 'id_rapbs']);
            if ($key === null) {
                continue;
            }
            $db->table('arkas_rkas_items')->updateOrInsert(['fiscal_year_id' => $year->id, 'source_rapbs_id' => $key], [
                'fund_source_id' => (int) ($this->get($record, $profile, 'fund_source', ['ID_REF_SUMBER_DANA', 'id_ref_sumber_dana']) ?: $year->fund_source_id),
                'activity_code' => $this->get($record, $profile, 'code', ['KODE_KEGIATAN', 'kode_kegiatan']),
                'activity_name' => $this->get($record, $profile, 'name', ['NAMA_KEGIATAN', 'nama_kegiatan']),
                'account_code' => $this->get($record, $profile, 'account_code', ['KODE_REKENING', 'kode_rekening']),
                'description' => $this->get($record, $profile, 'description', ['URAIAN', 'uraian']),
                'amount' => $this->number($record, $profile, 'amount', ['JUMLAH', 'jumlah']), 'payload' => $this->json($record), 'updated_at' => now(), 'created_at' => now(),
            ]);
            $count++;
        }

        return $count;
    }

    /** @param array<int, array<string, mixed>> $records */
    private function upsertPeriods(ArkasImportProfile $profile, FiscalYear $year, array $records): int
    {
        $db = DB::connection('school');
        $this->refresh($profile, $year, 'arkas_rkas_periods', $db);
        $names = $db->table('arkas_periods')->pluck('name', 'source_period_id')->all();
        $count = 0;
        foreach ($records as $record) {
            $rapbs = $this->get($record, $profile, 'source_rapbs', ['ID_RAPBS', 'id_rapbs']);
            $period = $this->get($record, $profile, 'period', ['ID_PERIODE', 'id_periode']);
            if ($rapbs === null || $period === null) {
                continue;
            }
            $name = $this->get($record, $profile, 'name', ['NAMA_PERIODE', 'PERIODE', 'nama_periode', 'periode']) ?: (string) ($names[$period] ?? '');
            [$month, $quarter, $semester] = $this->coordinates($period, $name);
            $db->table('arkas_rkas_periods')->updateOrInsert(['fiscal_year_id' => $year->id, 'fund_source_id' => $year->fund_source_id, 'source_rapbs_id' => $rapbs, 'source_period_id' => $period], [
                'period_name' => $name ?: null, 'month_number' => $month, 'quarter_number' => $quarter, 'semester_number' => $semester,
                'volume' => $this->number($record, $profile, 'volume', ['VOLUME', 'volume']), 'amount' => $this->number($record, $profile, 'amount', ['JUMLAH', 'jumlah']),
                'payload' => $this->json($record), 'updated_at' => now(), 'created_at' => now(),
            ]);
            $count++;
        }

        return $count;
    }

    /** @param array<int, array<string, mixed>> $records */
    private function upsertBku(ArkasImportProfile $profile, FiscalYear $year, array $records): int
    {
        $db = DB::connection('school');
        $this->refresh($profile, $year, 'arkas_bku_rows', $db);
        $count = 0;
        foreach ($records as $record) {
            $key = $this->get($record, $profile, 'source_key', ['ID_KAS_UMUM', 'id_kas_umum']);
            if ($key === null) {
                continue;
            }
            $db->table('arkas_bku_rows')->updateOrInsert(['fiscal_year_id' => $year->id, 'source_kas_id' => $key], [
                'fund_source_id' => (int) ($this->get($record, $profile, 'fund_source', ['ID_REF_SUMBER_DANA', 'id_ref_sumber_dana']) ?: $year->fund_source_id),
                'parent_kas_id' => $this->get($record, $profile, 'parent', ['PARENT_ID_KAS_UMUM', 'parent_id_kas_umum']), 'category' => $this->get($record, $profile, 'category', ['KATEGORI_BKU', 'kategori_bku']),
                'no_bukti' => $this->get($record, $profile, 'proof_number', ['NO_BUKTI', 'no_bukti']), 'transaction_date' => $this->date($record, $profile, 'transaction_date', ['TANGGAL_TRANSAKSI', 'tanggal_transaksi']),
                'amount' => $this->number($record, $profile, 'amount', ['JUMLAH', 'jumlah']), 'payload' => $this->json($record), 'updated_at' => now(), 'created_at' => now(),
            ]);
            $count++;
        }

        return $count;
    }

    /** @param array<int, array<string, mixed>> $records */
    private function upsertActivities(ArkasImportProfile $profile, FiscalYear $year, array $records): int
    {
        $db = DB::connection('school');
        $count = 0;
        foreach ($records as $record) {
            $code = $this->get($record, $profile, 'code', ['KODE_KEGIATAN', 'kode_kegiatan', 'ID_KODE', 'id_kode']);
            if ($code === null) {
                continue;
            }
            $db->table('activity_references')->updateOrInsert(['fiscal_year_id' => $year->id, 'activity_code' => $code], [
                'source_ref_code' => $this->get($record, $profile, 'parent', ['ID_REF_KODE', 'id_ref_kode', 'PARENT_KODE', 'parent_kode']),
                'activity_name' => $this->get($record, $profile, 'name', ['NAMA_KEGIATAN', 'nama_kegiatan', 'URAIAN_KODE', 'uraian_kode']), 'updated_at' => now(), 'created_at' => now(),
            ]);
            $count++;
        }

        return $count;
    }

    /** @param array<int, array<string, mixed>> $records */
    private function upsertPeriodReferences(ArkasImportProfile $profile, array $records): int
    {
        $db = DB::connection('school');
        $count = 0;
        foreach ($records as $record) {
            $key = $this->get($record, $profile, 'source_key', ['ID_PERIODE', 'id_periode']);
            if ($key === null) {
                continue;
            }
            $db->table('arkas_periods')->updateOrInsert(['source_period_id' => $key], ['name' => $this->get($record, $profile, 'name', ['NAMA_PERIODE', 'PERIODE', 'nama_periode', 'periode']) ?: $key, 'updated_at' => now(), 'created_at' => now()]);
            $count++;
        }

        return $count;
    }

    private function refresh(ArkasImportProfile $profile, FiscalYear $year, string $table, Connection $db): void
    {
        if ($profile->sync_mode === 'full_refresh') {
            $db->table($table)->where('fiscal_year_id', $year->id)->delete();
        }
    }

    /** @param array<string, mixed> $record @param array<int, string> $aliases */
    private function get(array $record, ArkasImportProfile $profile, string $role, array $aliases): ?string
    {
        $mapped = array_search($role, $profile->mapping ?? [], true);
        $value = $mapped !== false ? $this->recordValue($record, (string) $mapped) : null;
        foreach ($value === null ? $aliases : [] as $alias) {
            $value = $this->recordValue($record, $alias);
            if ($value !== null) {
                break;
            }
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** @param array<string, mixed> $record @param array<int, string> $aliases */
    private function number(array $record, ArkasImportProfile $profile, string $role, array $aliases): float
    {
        return (float) str_replace(',', '.', preg_replace('/[^0-9,.-]/', '', $this->get($record, $profile, $role, $aliases) ?? '0') ?? '0');
    }

    /** @param array<string, mixed> $record @param array<int, string> $aliases */
    private function date(array $record, ArkasImportProfile $profile, string $role, array $aliases): ?string
    {
        $value = $this->get($record, $profile, $role, $aliases);
        if ($value === null) {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $record */
    private function recordValue(array $record, string $column): mixed
    {
        foreach ($record as $key => $value) {
            if (strcasecmp((string) $key, $column) === 0) {
                return filled($value) ? $value : null;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $record */
    private function json(array $record): string
    {
        return json_encode($record, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /** @return array{0:?int,1:?int,2:?int} */
    private function coordinates(string $period, string $name): array
    {
        $months = ['januari', 'februari', 'maret', 'april', 'mei', 'juni', 'juli', 'agustus', 'september', 'oktober', 'november', 'desember'];
        $month = array_search(mb_strtolower(trim($name)), $months, true);
        if ($month !== false) {
            $month++;
        } elseif ((int) $period >= 81 && (int) $period <= 92) {
            $month = (int) $period - 80;
        } else {
            $month = null;
        }
        if ($month !== null) {
            return [$month, (int) ceil($month / 3), (int) ceil($month / 6)];
        }
        if ((int) $period >= 1 && (int) $period <= 4) {
            return [null, (int) $period, (int) ceil((int) $period / 2)];
        }

        return [null, null, null];
    }
}
