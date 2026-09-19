<?php

namespace Tests\Feature;

use App\Services\V2BIsolatedDatabaseGuard;
use App\Services\V2BSourceIdentityRegistryService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

final class V2BIsolatedSchemaTest extends TestCase
{
    private string $isolatedTargetPath;

    private string $readOnlySourcePath;

    protected function setUp(): void
    {
        parent::setUp();

        $source = getenv('SPJ_V2_B_SOURCE_PATH') ?: storage_path('app/v2-b-isolated/tenant-10260756-v2b.sqlite');

        if (! is_file($source)) {
            throw new RuntimeException("V2-B source fixture is missing: {$source}");
        }

        $targetDirectory = storage_path('app/v2-b-isolated');
        File::ensureDirectoryExists($targetDirectory);
        $this->isolatedTargetPath = $targetDirectory.'/v2b-'.getmypid().'-'.bin2hex(random_bytes(8)).'.sqlite';
        $this->readOnlySourcePath = $source;

        if (! File::copy($source, $this->isolatedTargetPath)) {
            throw new RuntimeException("Unable to create V2-B isolated clone: {$this->isolatedTargetPath}");
        }

        putenv('SPJ_V2_B_TARGET_PATH='.$this->isolatedTargetPath);
        putenv('SPJ_V2_B_SOURCE_PATH='.$this->readOnlySourcePath);
    }

    protected function tearDown(): void
    {
        DB::disconnect('school');
        DB::purge('school');

        if (isset($this->isolatedTargetPath)) {
            File::delete($this->isolatedTargetPath);
        }

        putenv('SPJ_V2_B_TARGET_PATH');
        putenv('SPJ_V2_B_SOURCE_PATH');

        parent::tearDown();
    }

    public function test_v2_b_schema_lifecycle_mapping_and_immutability_are_safe_on_isolated_copy(): void
    {
        $target = getenv('SPJ_V2_B_TARGET_PATH') ?: '';
        $source = getenv('SPJ_V2_B_SOURCE_PATH') ?: '';
        $this->assertNotSame('', $target, 'SPJ_V2_B_TARGET_PATH must be an explicit isolated clone.');
        $this->assertNotSame('', $source, 'SPJ_V2_B_SOURCE_PATH must be explicit and read-only.');

        config()->set('database.connections.school.database', $target);
        DB::purge('school');
        $connection = DB::connection('school');
        $before = $this->manifest($connection);
        $sourceHashBefore = hash_file('sha256', $source);
        $this->assertSame(0, (int) $connection->selectOne('PRAGMA query_only')->query_only);
        DB::disconnect('school');
        DB::purge('school');

        config()->set('spj.v2_b_isolated_manifest', [
            'target_path' => $target, 'npsn' => 10260756, 'source_id' => 1,
            'source_identity_npsn' => 10260756, 'source_path' => $source,
            'source_read_only' => true, 'query_only' => true,
        ]);

        Artisan::call('migrate', [
            '--database' => 'school',
            '--path' => 'database/migrations/v2-rehearsal',
            '--force' => true,
        ]);
        Artisan::call('migrate', [
            '--database' => 'school',
            '--path' => 'database/migrations/v2-rehearsal',
            '--force' => true,
        ]);

        $connection = DB::connection('school');
        foreach (['arkas_source_identity_registry', 'spj_transactions', 'spj_transaction_sources', 'spj_transaction_overlays', 'spj_item_overlays', 'legacy_transaction_v2_map'] as $table) {
            $this->assertTrue($connection->getSchemaBuilder()->hasTable($table));
        }
        foreach ($before['tables'] as $table => $hash) {
            $this->assertSame($hash, $this->tableHash($connection, $table), 'Pre/post manifest changed: '.$table);
        }
        $this->assertSame($sourceHashBefore, hash_file('sha256', $source));
        $this->assertSame('ok', $connection->selectOne('PRAGMA integrity_check')->integrity_check);
        $this->assertCount(0, $connection->select('PRAGMA foreign_key_check'));

        $raw = $connection->table('arkas_raw_mirror_rows as rows')
            ->join('arkas_raw_mirror_tables as tables', 'tables.id', '=', 'rows.mirror_table_id')
            ->where('tables.source_id', 1)
            ->where('tables.source_table', 'kas_umum')
            ->select('rows.*')
            ->first();
        $this->assertNotNull($raw);
        $service = app(V2BSourceIdentityRegistryService::class);
        $identityId = $service->registerOrRefresh($connection, 1, 'kas_umum', $raw->source_key, ['ID_KAS_UMUM' => $raw->source_key], 'PRIMARY_KEY', (int) $raw->id, $raw->payload_hash);
        $service->markMissing($connection, 1, 'kas_umum', $raw->source_key);
        $returnedId = $service->registerOrRefresh($connection, 1, 'kas_umum', $raw->source_key, ['ID_KAS_UMUM' => $raw->source_key], 'PRIMARY_KEY', (int) $raw->id, $raw->payload_hash);
        $this->assertSame($identityId, $returnedId);
        $this->assertSame('ACTIVE', $connection->table('arkas_source_identity_registry')->where('id', $identityId)->value('source_status'));

        try {
            $connection->table('arkas_source_identity_registry')->insert([
                'source_id' => 1, 'source_table' => 'kas_umum', 'source_key' => $raw->source_key,
                'primary_key_json' => json_encode(['ID_KAS_UMUM' => $raw->source_key]), 'identity_type' => 'PRIMARY_KEY',
                'source_status' => 'ACTIVE', 'first_seen_at' => now(), 'last_seen_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->fail('Duplicate source identity was accepted.');
        } catch (UniqueConstraintViolationException) {
            $this->assertTrue(true);
        }

        $guard = app(V2BIsolatedDatabaseGuard::class);
        $this->assertSame('{"id_ref_kode":"A","tahun":2026}', $guard->serializePrimaryKey(['id_ref_kode', 'tahun'], ['id_ref_kode' => 'A', 'tahun' => 2026]));
        $this->assertSame('{"tahun":2026,"id_ref_kode":"A"}', $guard->serializePrimaryKey(['tahun', 'id_ref_kode'], ['id_ref_kode' => 'A', 'tahun' => 2026]));

        $existingV2 = $connection->table('spj_transactions')->where('source_membership_hash', hash('sha256', $raw->source_key))->first();
        $transactionId = $existingV2?->id ?: $connection->table('spj_transactions')->insertGetId([
            'fiscal_year_id' => 1, 'fund_source_id' => 1, 'source_id' => 1,
            'source_membership_hash' => hash('sha256', $raw->source_key), 'source_status' => 'ACTIVE',
            'requires_reconciliation' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $existingLink = $connection->table('spj_transaction_sources')->where('spj_transaction_id', $transactionId)->where('arkas_source_identity_id', $identityId)->first();
        $sourceLinkId = $existingLink?->id ?: $connection->table('spj_transaction_sources')->insertGetId([
            'spj_transaction_id' => $transactionId, 'arkas_source_identity_id' => $identityId,
            'sort_order' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $connection->table('spj_transaction_overlays')->updateOrInsert(['spj_transaction_id' => $transactionId], [
            'spj_transaction_id' => $transactionId, 'spj_category' => 'GOODS',
            'payment_description' => 'operator-only overlay', 'operator_metadata' => json_encode(['kept' => true]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $connection->table('spj_item_overlays')->updateOrInsert(['spj_transaction_source_id' => $sourceLinkId], [
            'spj_transaction_source_id' => $sourceLinkId, 'item_description' => 'operator item note',
            'operator_metadata' => json_encode(['kept' => true]), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $legacyRows = $connection->table('transactions')->orderBy('id')->limit(2)->get();
        $this->assertCount(2, $legacyRows);
        $legacySourceKeys = $legacyRows->pluck('source_key')->all();
        $connection->table('legacy_transaction_v2_map')->updateOrInsert(['legacy_transaction_id' => $legacyRows[0]->id], [
            'legacy_transaction_id' => $legacyRows[0]->id, 'spj_transaction_id' => $transactionId,
            'mapping_status' => 'EXACT', 'mapping_reason' => 'all source_item_id values resolved',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $secondExisting = $connection->table('spj_transactions')->where('source_membership_hash', hash('sha256', $legacyRows[1]->source_key))->first();
        $secondSpjId = $secondExisting?->id ?: $connection->table('spj_transactions')->insertGetId([
            'fiscal_year_id' => 1, 'fund_source_id' => 1, 'source_id' => 1,
            'source_membership_hash' => hash('sha256', $legacyRows[1]->source_key), 'source_status' => 'ACTIVE',
            'requires_reconciliation' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $connection->table('legacy_transaction_v2_map')->updateOrInsert(['legacy_transaction_id' => $legacyRows[1]->id], [
            'legacy_transaction_id' => $legacyRows[1]->id, 'spj_transaction_id' => $secondSpjId,
            'mapping_status' => 'DETERMINISTIC', 'mapping_reason' => 'derived without source_key rewrite',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertSame($legacySourceKeys, $connection->table('transactions')->whereIn('id', $legacyRows->pluck('id'))->orderBy('id')->pluck('source_key')->all());
        $this->assertSame('operator-only overlay', $connection->table('spj_transaction_overlays')->where('spj_transaction_id', $transactionId)->value('payment_description'));
        $this->assertSame('operator item note', $connection->table('spj_item_overlays')->where('spj_transaction_source_id', $sourceLinkId)->value('item_description'));
        $this->assertSame('DETERMINISTIC', $connection->table('legacy_transaction_v2_map')->where('spj_transaction_id', $secondSpjId)->value('mapping_status'));
        $this->assertSame('ok', $connection->selectOne('PRAGMA integrity_check')->integrity_check);
        $this->assertCount(0, $connection->select('PRAGMA foreign_key_check'));

        $this->expectException(RuntimeException::class);
        $service->registerOrRefresh($connection, 1, 'kas_umum', 'UNSTABLE', ['value' => 'UNSTABLE'], 'UNSTABLE_FALLBACK', null, null);
    }

    public function test_guard_rejects_original_path_and_identity_mismatch(): void
    {
        $guard = app(V2BIsolatedDatabaseGuard::class);
        config()->set('database.connections.school.database', 'D:\\lrvProject\\spj-bosp-data\\school-databases\\10208183\\spj.sqlite');
        DB::purge('school');
        $this->expectException(RuntimeException::class);
        $guard->assertMigrationTarget(DB::connection('school'), [
            'target_path' => 'D:\\lrvProject\\spj-bosp-data\\school-databases\\10208183\\spj.sqlite',
            'npsn' => 10208183, 'source_id' => 1, 'source_identity_npsn' => 10260756,
            'source_path' => 'D:\\backupdata\\datasmp.db', 'source_read_only' => true, 'query_only' => true,
        ]);
    }

    public function test_guard_rejects_path_traversal_to_original_tenant(): void
    {
        $guard = app(V2BIsolatedDatabaseGuard::class);
        $target = storage_path('app/v2-b-isolated'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'school-databases'.DIRECTORY_SEPARATOR.'10260786'.DIRECTORY_SEPARATOR.'spj.sqlite');
        config()->set('database.connections.school.database', $target);
        DB::purge('school');

        $this->expectException(RuntimeException::class);
        $guard->assertMigrationTarget(DB::connection('school'), [
            'target_path' => $target,
            'npsn' => 10260756,
            'source_id' => 1,
            'source_identity_npsn' => 10260756,
            'source_path' => 'D:\\backupdata\\datasmp.db',
            'source_read_only' => true,
            'query_only' => true,
        ]);
    }

    public function test_registry_rejects_mismatched_raw_mirror_lineage_and_unknown_type(): void
    {
        $target = getenv('SPJ_V2_B_TARGET_PATH') ?: '';
        $source = getenv('SPJ_V2_B_SOURCE_PATH') ?: '';
        $this->assertNotSame('', $target);
        $this->assertNotSame('', $source);
        config()->set('database.connections.school.database', $target);
        config()->set('spj.v2_b_isolated_manifest', [
            'target_path' => $target, 'npsn' => 10260756, 'source_id' => 1,
            'source_identity_npsn' => 10260756, 'source_path' => $source,
            'source_read_only' => true, 'query_only' => true,
        ]);
        DB::purge('school');
        $connection = DB::connection('school');
        Artisan::call('migrate', ['--database' => 'school', '--path' => 'database/migrations/v2-rehearsal', '--force' => true]);
        $raw = $connection->table('arkas_raw_mirror_rows as rows')
            ->join('arkas_raw_mirror_tables as tables', 'tables.id', '=', 'rows.mirror_table_id')
            ->where('tables.source_id', 1)
            ->where('tables.source_table', 'kas_umum')
            ->select('rows.*')
            ->first();
        $other = $connection->table('arkas_raw_mirror_rows')->where('id', '!=', $raw->id)->first();
        $service = app(V2BSourceIdentityRegistryService::class);

        try {
            $service->registerOrRefresh($connection, 1, 'kas_umum', $raw->source_key, ['id_kas_umum' => $raw->source_key], 'UNKNOWN_TYPE', null, null);
            $this->fail('Unknown identity type was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('identity type', strtolower($exception->getMessage()));
        }

        $this->expectException(RuntimeException::class);
        $service->registerOrRefresh($connection, 1, 'kas_umum', $raw->source_key, ['id_kas_umum' => $raw->source_key], 'PRIMARY_KEY', (int) $other->id, $raw->payload_hash);
    }

    private function manifest($connection): array
    {
        $tables = $connection->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' AND name <> 'migrations'");
        $manifest = [];
        $v2Tables = ['arkas_source_identity_registry', 'spj_transactions', 'spj_transaction_sources', 'spj_transaction_overlays', 'spj_item_overlays', 'legacy_transaction_v2_map'];
        foreach ($tables as $table) {
            if (in_array($table->name, $v2Tables, true)) {
                continue;
            }
            $manifest[$table->name] = $this->tableHash($connection, $table->name);
        }

        return ['tables' => $manifest];
    }

    private function tableHash($connection, string $table): string
    {
        $rows = $connection->table($table)->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();

        return hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
