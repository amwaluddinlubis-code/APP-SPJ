<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\SchoolDatabase;
use App\Services\SchoolDatabaseManager;
use App\Services\SchoolDatabaseResetService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class SchoolDatabaseResetServiceTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/school-reset-'.uniqid().'.sqlite');
        File::ensureDirectoryExists(dirname($this->databasePath));
        $this->createDatabaseWithExistingRows($this->databasePath);

        Config::set('database.connections.school.database', $this->databasePath);
        Config::set('database.connections.school.journal_mode', null);
        DB::purge('school');
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        foreach ([$this->databasePath, $this->databasePath.'-wal', $this->databasePath.'-shm'] as $file) {
            File::delete($file);
        }

        parent::tearDown();
    }

    public function test_reset_rebuilds_database_restarts_autoincrement_and_cleans_sidecars(): void
    {
        File::put($this->databasePath.'-wal', 'stale-wal');
        File::put($this->databasePath.'-shm', 'stale-shm');

        $school = new School([
            'npsn' => '12345678',
            'name' => 'Sekolah Uji',
        ]);
        $school->id = 99;
        $school->setRelation('databaseRecord', new SchoolDatabase([
            'school_id' => $school->id,
            'database_path' => $this->databasePath,
            'status' => 'READY',
        ]));

        $manager = new class($this->databasePath) extends SchoolDatabaseManager
        {
            public function __construct(private readonly string $path) {}

            public function provision(School $school): SchoolDatabase
            {
                $pdo = new \PDO('sqlite:'.$this->path);
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $pdo->exec('CREATE TABLE reset_rows (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
                $pdo = null;

                Config::set('database.connections.school.database', $this->path);
                Config::set('database.connections.school.journal_mode', null);
                DB::purge('school');
                DB::reconnect('school');

                return new SchoolDatabase([
                    'school_id' => $school->id,
                    'database_path' => $this->path,
                    'status' => 'READY',
                ]);
            }
        };

        (new SchoolDatabaseResetService($manager))->reset($school);

        $this->assertFalse(File::exists($this->databasePath.'-wal'));
        $this->assertFalse(File::exists($this->databasePath.'-shm'));
        $this->assertSame(0, DB::connection('school')->table('reset_rows')->count());
        $this->assertSame(0, DB::connection('school')->table('sqlite_sequence')->count());

        $id = DB::connection('school')->table('reset_rows')->insertGetId(['name' => 'Baru']);
        $this->assertSame(1, $id);
    }

    public function test_reset_refuses_to_delete_primary_application_database(): void
    {
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', $this->databasePath);

        $school = new School([
            'npsn' => '87654321',
            'name' => 'Sekolah Salah Path',
        ]);
        $school->id = 100;
        $school->setRelation('databaseRecord', new SchoolDatabase([
            'school_id' => $school->id,
            'database_path' => $this->databasePath,
            'status' => 'READY',
        ]));

        $manager = new class extends SchoolDatabaseManager
        {
            public bool $provisionCalled = false;

            public function provision(School $school): SchoolDatabase
            {
                $this->provisionCalled = true;

                throw new RuntimeException('Provision tidak boleh terpanggil.');
            }
        };

        try {
            (new SchoolDatabaseResetService($manager))->reset($school);
            $this->fail('Reset wajib ditolak ketika path tenant sama dengan database utama.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('database utama aplikasi', $exception->getMessage());
        }

        $this->assertFalse($manager->provisionCalled);
        $this->assertTrue(File::exists($this->databasePath));

        $pdo = new \PDO('sqlite:'.$this->databasePath);
        $this->assertSame(3, (int) $pdo->query('SELECT COUNT(*) FROM reset_rows')->fetchColumn());
        $pdo = null;
    }

    private function createDatabaseWithExistingRows(string $path): void
    {
        $pdo = new \PDO('sqlite:'.$path);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE reset_rows (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $pdo->exec("INSERT INTO reset_rows (name) VALUES ('Lama 1'), ('Lama 2'), ('Lama 3')");
        $pdo = null;
    }
}
