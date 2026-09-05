<?php

namespace App\Services;

use App\Models\ArkasSource;
use App\Models\FiscalYear;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Synchronizes the reference tables that may be safely refreshed from ARKAS. */
class ArkasReferenceSynchronizationService
{
    public function __construct(private ArkasBridgeClient $bridge) {}

    /** @return Collection<int, FiscalYear> */
    public function synchronizeFiscalYearContexts(ArkasSource $source): Collection
    {
        $years = $this->sourceYears($source);
        if ($years === []) {
            throw new \RuntimeException('Database ARKAS tidak mengembalikan tahun anggaran.');
        }

        $contexts = [];
        foreach ($years as $year) {
            foreach (ArkasPipePayload::decode($this->bridge->execute($source, 'fund-sources', $year), 'fund-sources') as $fundSource) {
                $id = (int) ($fundSource['ID_SUMBER_DANA'] ?? 0);
                if ($id === 0) {
                    continue;
                }
                $contexts[] = [
                    'year' => $year,
                    'fund_source_id' => $id,
                    'code' => (string) ($fundSource['KODE'] ?? ''),
                    'name' => (string) ($fundSource['NAMA_SUMBER_DANA'] ?? ''),
                    'payload' => $fundSource,
                ];
            }
        }
        if ($contexts === []) {
            throw new \RuntimeException('Database ARKAS tidak mengembalikan sumber dana untuk tahun yang tersedia.');
        }

        $db = DB::connection('school');
        $db->transaction(function () use ($db, $contexts): void {
            $db->table('fiscal_years')->update(['is_active' => false, 'updated_at' => now()]);
            foreach ($contexts as $context) {
                $db->table('fund_sources')->updateOrInsert(
                    ['id' => $context['fund_source_id']],
                    ['code' => $context['code'], 'name' => $context['name'], 'is_hidden' => false, 'payload' => json_encode($context['payload'], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE), 'updated_at' => now(), 'created_at' => now()],
                );
                $db->table('fiscal_years')->updateOrInsert(
                    ['year' => $context['year'], 'fund_source_id' => $context['fund_source_id']],
                    ['fund_source' => $context['name'], 'is_active' => true, 'updated_at' => now(), 'created_at' => now()],
                );
            }
        });

        return FiscalYear::query()->with('fundSource')->where('is_active', true)->orderByDesc('year')->get();
    }

    /** @return array<string,int> */
    public function synchronizeBase(FiscalYear $year, ArkasSource $source): array
    {
        $fundSources = ArkasPipePayload::decode($this->bridge->execute($source, 'fund-sources', $year->year), 'fund-sources');
        $fiscalYearContexts = $this->fiscalYearContexts($source, $year->year, $fundSources);
        $profile = ArkasPipePayload::values($this->bridge->execute($source, 'profile', $year->year), 'profile');
        $pegawai = ArkasPipePayload::decode($this->bridge->execute($source, 'pegawai', $year->year), 'pegawai');
        $ptk = ArkasPipePayload::decode($this->bridge->execute($source, 'ptk', $year->year), 'ptk');
        $rekening = ArkasPipePayload::decode($this->bridge->execute($source, 'rekening', $year->year), 'rekening');
        $periods = ArkasPipePayload::pairs($this->bridge->execute($source, 'periods'), 'periods');
        $db = DB::connection('school');
        $now = now();

        $db->transaction(function () use ($db, $year, $fiscalYearContexts, $profile, $pegawai, $ptk, $rekening, $periods, $now) {
            $db->table('fiscal_years')->update(['is_active' => false, 'updated_at' => $now]);
            foreach ($fiscalYearContexts as $context) {
                $db->table('fund_sources')->updateOrInsert(
                    ['id' => $context['fund_source_id']],
                    ['code' => $context['code'], 'name' => $context['name'],
                        'is_hidden' => false, 'payload' => json_encode($context['payload'], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE), 'updated_at' => $now, 'created_at' => $now]
                );
                $db->table('fiscal_years')->updateOrInsert(
                    ['year' => $context['year'], 'fund_source_id' => $context['fund_source_id']],
                    ['fund_source' => $context['name'], 'is_active' => true, 'updated_at' => $now, 'created_at' => $now]
                );
            }
            $db->table('school_profiles')->updateOrInsert(['fiscal_year_id' => $year->id], [
                'principal_name' => $profile['KEPALA_SEKOLAH'] ?? null,
                'principal_nip' => $profile['NIP_KEPALA_SEKOLAH'] ?? null,
                'treasurer_name' => $profile['BENDAHARA'] ?? null,
                'treasurer_nip' => $profile['NIP_BENDAHARA'] ?? null,
                'principal_email' => $profile['EMAIL_KEPALA_SEKOLAH'] ?? null,
                'principal_phone' => $profile['TELP_KEPALA_SEKOLAH'] ?? null,
                'treasurer_email' => $profile['EMAIL_BENDAHARA'] ?? null,
                'treasurer_phone' => $profile['TELP_BENDAHARA'] ?? null,
                'payload' => json_encode($profile, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE), 'updated_at' => $now, 'created_at' => $now,
            ]);
            foreach ($pegawai as $record) {
                $this->saveEmployee('PEGAWAI', $record['NIP'] ?: sha1(($record['NAMA'] ?? '').'|'.($record['JABATAN'] ?? '')), $record);
            }
            foreach ($ptk as $record) {
                $this->saveEmployee('PTK', $record['PTK_ID_ARKAS'], $record);
            }
            foreach ($rekening as $record) {
                $flags = [];
                foreach (['is_honor' => 'IS_HONOR', 'is_ppn' => 'IS_PPN', 'is_pph21' => 'IS_PPH21', 'is_pph22' => 'IS_PPH22', 'is_pph23' => 'IS_PPH23', 'is_pph4' => 'IS_PPH4', 'is_sspd' => 'IS_SSPD', 'is_buku' => 'IS_BUKU'] as $column => $field) {
                    $flags[$column] = $this->flag($record[$field] ?? false);
                }
                $db->table('account_references')->updateOrInsert(['fiscal_year_id' => $year->id, 'account_code' => $record['KODE_REKENING']], $flags + [
                    'account_name' => $record['NAMA_REKENING'] ?? null, 'spj_category' => $record['KATEGORI_SPJ'] ?? null,
                    'payload' => json_encode($record, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE), 'updated_at' => $now, 'created_at' => $now,
                ]);
            }
            foreach ($periods as $period) {
                $db->table('arkas_periods')->updateOrInsert(['source_period_id' => $period['id']], ['name' => $period['name'], 'updated_at' => $now, 'created_at' => $now]);
            }
            $this->seedAccountHierarchy();
        });

        return ['profile' => 1, 'fund_sources' => count($fundSources), 'employees' => count($pegawai) + count($ptk), 'accounts' => count($rekening), 'periods' => count($periods)];
    }

    /** @return array<int, array{year:int, fund_source_id:int, code:string, name:string, payload:array<string,mixed>}> */
    private function fiscalYearContexts(ArkasSource $source, int $selectedYear, array $selectedFundSources): array
    {
        $years = $this->sourceYears($source);
        $years = array_values(array_unique([...$years, $selectedYear]));
        $contexts = [];

        foreach ($years as $year) {
            $fundSources = $year === $selectedYear
                ? $selectedFundSources
                : ArkasPipePayload::decode($this->bridge->execute($source, 'fund-sources', $year), 'fund-sources');
            foreach ($fundSources as $fundSource) {
                $id = (int) ($fundSource['ID_SUMBER_DANA'] ?? 0);
                if ($id === 0) {
                    continue;
                }
                $contexts[] = [
                    'year' => $year,
                    'fund_source_id' => $id,
                    'code' => (string) ($fundSource['KODE'] ?? ''),
                    'name' => (string) ($fundSource['NAMA_SUMBER_DANA'] ?? ''),
                    'payload' => $fundSource,
                ];
            }
        }

        return $contexts;
    }

    /** @return array<int, int> */
    private function sourceYears(ArkasSource $source): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (string $value): int => (int) trim($value),
            ArkasPipePayload::lines($this->bridge->execute($source, 'years'), 'years')
        ), fn (int $value): bool => $value > 0)));
    }

    /** Builds activity and supplier references from the raw RKAS/BKU already imported. */
    public function synchronizeDerived(FiscalYear $year): array
    {
        $db = DB::connection('school');
        $now = now();
        $activities = 0;
        $partners = 0;
        foreach ($db->table('arkas_rkas_items')->where('fiscal_year_id', $year->id)->get() as $item) {
            $payload = json_decode($item->payload, true) ?: [];
            if (blank($item->activity_code)) {
                continue;
            }
            $db->table('activity_references')->updateOrInsert(['fiscal_year_id' => $year->id, 'activity_code' => $item->activity_code], [
                'source_ref_code' => $payload['ID_REF_KODE'] ?? null, 'activity_name' => $item->activity_name,
                'updated_at' => $now, 'created_at' => $now,
            ]);
            $activities++;
        }
        foreach ($db->table('arkas_bku_rows')->where('fiscal_year_id', $year->id)->get() as $row) {
            $payload = json_decode($row->payload, true) ?: [];
            $name = trim((string) ($payload['NAMA_TOKO'] ?? ''));
            if ($name === '') {
                continue;
            }
            $npwp = trim((string) ($payload['NPWP_REKANAN'] ?? ''));
            $db->table('business_partners')->updateOrInsert(['name' => $name, 'npwp' => $npwp], [
                'phone' => $payload['NO_TELP_TOKO'] ?? null, 'address' => $payload['ALAMAT_TOKO'] ?? null,
                'is_business_entity' => $this->flag($payload['IS_BADAN_USAHA'] ?? false), 'is_arkas_synced' => true,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE), 'updated_at' => $now, 'created_at' => $now,
            ]);
            $partners++;
        }

        return ['activities' => $activities, 'partners' => $partners];
    }

    private function saveEmployee(string $type, string $key, array $record): void
    {
        if (blank($key) || blank($record['NAMA'] ?? null)) {
            return;
        }
        $ptk = $type === 'PTK';
        DB::connection('school')->table('employees')->updateOrInsert(['source_type' => $type, 'source_key' => $key], [
            'name' => $record['NAMA'], 'nip' => $record['NIP'] ?? null, 'nik' => $record['NIK'] ?? null, 'nuptk' => $record['NUPTK'] ?? null,
            'gender' => $record['JENIS_KELAMIN'] ?? null, 'employment_status' => $ptk ? null : ($record['STATUS_PEGAWAI'] ?? null),
            'staff_type' => $record['JENIS_PTK'] ?? null, 'position' => $record['JABATAN'] ?? null, 'npwp' => $record['NPWP'] ?? null,
            'bank_name' => $record['NAMA_BANK'] ?? null, 'bank_account' => $record['NO_REKENING'] ?? null,
            'is_active' => $this->flag($record['STATUS_AKTIF'] ?? true), 'payload' => json_encode($record, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE), 'updated_at' => now(), 'created_at' => now(),
        ]);
    }

    private function seedAccountHierarchy(): void
    {
        foreach ([
            ['5', 'BELANJA', 1], ['5.1', 'BELANJA OPERASI', 2], ['5.1.02', 'BELANJA BARANG DAN JASA', 3], ['5.1.02.01', 'BELANJA BARANG', 4], ['5.1.02.02', 'BELANJA JASA', 4], ['5.1.02.03', 'BELANJA PEMELIHARAAN', 4], ['5.1.02.04', 'BELANJA PERJALANAN DINAS', 4], ['5.2', 'BELANJA MODAL', 2], ['5.2.02', 'BELANJA MODAL PERALATAN DAN MESIN', 3], ['5.2.05', 'BELANJA MODAL ASET TETAP LAINNYA', 3]] as [$code,$name,$level]) {
            DB::connection('school')->table('account_hierarchies')->updateOrInsert(['account_code' => $code], ['account_name' => $name, 'level' => $level, 'updated_at' => now(), 'created_at' => now()]);
        }
    }

    private function flag(mixed $value): bool
    {
        return in_array(strtoupper(trim((string) $value)), ['1', 'TRUE', 'YA', 'Y', 'AKTIF'], true);
    }
}
