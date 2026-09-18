<?php

namespace Tests\Feature;

use App\Models\ArkasSource;
use App\Services\ArkasBridgeClient;
use App\Services\ArkasDatabaseExplorer;
use App\Services\ArkasRawMirrorService;
use App\Services\ArkasSourceKeyResolver;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class ArkasRawMirrorAtomicityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        Artisan::call('migrate', ['--database' => 'school', '--path' => 'database/migrations/school', '--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    public function test_failed_row_refresh_keeps_previous_snapshot_and_metadata(): void
    {
        $source = new ArkasSource;
        $source->id = 7;
        $oldSeenAt = now()->subHour();
        $mirrorTableId = DB::connection('school')->table('arkas_raw_mirror_tables')->insertGetId([
            'source_id' => $source->id,
            'source_table' => 'kas_umum',
            'schema' => json_encode([['name' => 'ID']], JSON_THROW_ON_ERROR),
            'schema_hash' => hash('sha256', 'old-schema'),
            'row_count' => 1,
            'status' => 'ACTIVE',
            'last_seen_at' => $oldSeenAt,
            'last_synced_at' => $oldSeenAt,
            'created_at' => $oldSeenAt,
            'updated_at' => $oldSeenAt,
        ]);
        DB::connection('school')->table('arkas_raw_mirror_rows')->insert([
            'mirror_table_id' => $mirrorTableId,
            'source_key' => 'OLD-ROW',
            'ordinal' => 0,
            'payload' => json_encode(['ID' => 'OLD-ROW'], JSON_THROW_ON_ERROR),
            'payload_hash' => hash('sha256', 'old-row'),
            'created_at' => $oldSeenAt,
            'updated_at' => $oldSeenAt,
        ]);
        $this->assertNotNull(DB::connection('school')->table('arkas_raw_mirror_tables')->where('id', $mirrorTableId)->first());

        $explorer = Mockery::mock(ArkasDatabaseExplorer::class);
        $explorer->shouldReceive('tables')->once()->with($source)->andReturn(['kas_umum']);
        $explorer->shouldReceive('inspect')->once()->with($source, 'kas_umum', 1)->andReturn([
            'columns' => [[
                'name' => 'ID',
                'type' => 'TEXT',
                'nullable' => 'Ya',
                'primary' => 'Ya',
                'primary_order' => '1',
            ]],
            'rows' => [],
        ]);
        $bridge = Mockery::mock(ArkasBridgeClient::class);
        $bridge->shouldReceive('execute')->once()->withArgs(fn (ArkasSource $actualSource, string $command): bool => $actualSource->id === $source->id && $command === 'rows')
            ->andReturn("FIELDS|ID\nDATA|NEW-ROW-1\nDATA|NEW-ROW-2");
        $sourceKeys = Mockery::mock(ArkasSourceKeyResolver::class);
        $sourceKeys->shouldReceive('resolveFromColumns')->twice()->andReturnUsing(function (): string {
            static $calls = 0;
            $calls++;
            if ($calls === 2) {
                throw new \RuntimeException('deterministic row failure');
            }

            return 'NEW-ROW-1';
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('deterministic row failure');

        try {
            (new ArkasRawMirrorService($explorer, $bridge, $sourceKeys))->synchronize($source);
        } finally {
            $metadata = DB::connection('school')->table('arkas_raw_mirror_tables')->where('id', $mirrorTableId)->first();
            $this->assertSame('ACTIVE', $metadata->status);
            $this->assertSame(1, (int) $metadata->row_count);
            $this->assertSame($oldSeenAt->toDateTimeString(), $metadata->last_synced_at);
            $this->assertNull($metadata->last_error);
            $this->assertSame(['OLD-ROW'], DB::connection('school')->table('arkas_raw_mirror_rows')->where('mirror_table_id', $mirrorTableId)->pluck('source_key')->all());
        }
    }
}
