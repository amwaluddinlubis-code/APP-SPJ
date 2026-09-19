<?php

namespace Tests\Feature;

use App\Models\ArkasSource;
use App\Services\ArkasBridgeClient;
use App\Services\ArkasDatabaseExplorer;
use App\Services\ArkasRawMirrorService;
use App\Services\ArkasSourceKeyResolver;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class ArkasTenantDumpRehearsalTest extends TestCase
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

    public function test_available_dump_rehearses_tenant_mirror_and_is_idempotent(): void
    {
        $path = (string) env('ARKAS_DUMP_PATH', '');
        if ($path === '' || ! is_file($path)) {
            self::markTestSkipped('Set ARKAS_DUMP_PATH to run the external ARKAS dump rehearsal.');
        }

        $bridge = new DumpRehearsalBridge($path);
        $source = new ArkasSource;
        $source->id = 9001;
        $mirror = new ArkasRawMirrorService(
            new ArkasDatabaseExplorer($bridge),
            $bridge,
            new ArkasSourceKeyResolver,
        );

        $dryRun = $mirror->dryRun($source);
        self::assertTrue($dryRun['dry_run']);
        self::assertSame(7771, $dryRun['rows']);
        self::assertSame(0, DB::connection('school')->table('arkas_raw_mirror_tables')->count());

        $first = $mirror->synchronize($source);
        $firstTables = DB::connection('school')->table('arkas_raw_mirror_tables')->count();
        $firstRows = DB::connection('school')->table('arkas_raw_mirror_rows')->count();
        $second = $mirror->synchronize($source);
        $secondTables = DB::connection('school')->table('arkas_raw_mirror_tables')->count();
        $secondRows = DB::connection('school')->table('arkas_raw_mirror_rows')->count();

        self::assertSame(['pegawai'], $first['optional_unavailable']);
        self::assertSame(13, $first['tables']);
        self::assertSame(7771, $first['rows']);
        self::assertSame($firstTables, $secondTables);
        self::assertSame($firstRows, $secondRows);
        self::assertSame($first['rows'], $firstRows);
        self::assertSame($second['rows'], $secondRows);
        self::assertSame(0, DB::connection('school')->table('arkas_raw_mirror_rows')
            ->select('mirror_table_id', 'source_key', 'ordinal')
            ->groupBy('mirror_table_id', 'source_key', 'ordinal')
            ->havingRaw('COUNT(*) > 1')
            ->count());
    }
}

final class DumpRehearsalBridge extends ArkasBridgeClient
{
    private PDO $database;

    public function __construct(string $dumpPath)
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec(file_get_contents($dumpPath));
    }

    public function execute(ArkasSource $source, string $command, ?int $year = null, ?string $table = null, ?int $fundSourceId = null, ?int $limit = null, ?int $offset = null): string
    {
        return match ($command) {
            'tables' => $this->tables(),
            'schema' => $this->schema($table),
            'rows' => $this->rows($table, $limit ?? 100000, $offset ?? 0),
            default => throw new \RuntimeException('Unsupported rehearsal command: '.$command),
        };
    }

    private function tables(): string
    {
        $tables = $this->database->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

        return implode(PHP_EOL, array_map(static fn (string $table): string => 'TABLE|'.$table, $tables));
    }

    private function schema(?string $table): string
    {
        $quoted = $this->database->quote((string) $table);
        $columns = $this->database->query('PRAGMA table_info('.$quoted.')')->fetchAll(PDO::FETCH_ASSOC);
        $fields = [];
        foreach ($columns as $position => $column) {
            $fields[] = implode('|', [
                'COLUMN',
                $position,
                $column['name'],
                $column['type'],
                (int) $column['notnull'] === 1 ? '0' : '1',
                '',
                $column['pk'],
            ]);
        }

        return implode(PHP_EOL, $fields);
    }

    private function rows(?string $table, int $limit, int $offset): string
    {
        $quoted = $this->database->quote((string) $table);
        $rows = $this->database->query('SELECT * FROM '.$quoted.' LIMIT '.max(1, $limit).' OFFSET '.max(0, $offset))->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) {
            return '';
        }

        $fields = array_keys($rows[0]);
        $lines = ['FIELDS|'.implode('|', $fields)];
        foreach ($rows as $row) {
            $values = array_map(static fn (mixed $value): string => str_replace('|', '/', (string) ($value ?? '')), array_values($row));
            $lines[] = 'DATA|'.implode('|', $values);
        }

        return implode(PHP_EOL, $lines);
    }
}
