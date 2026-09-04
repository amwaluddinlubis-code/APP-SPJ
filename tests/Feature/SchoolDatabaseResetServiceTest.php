<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\SchoolDatabase;
use App\Services\SchoolDatabaseManager;
use App\Services\SchoolDatabaseResetService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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

    public function test_reset_rebuilds_database_and_restarts_autoincrement_from_one(): void
    {
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
            public function __construct(private readonly string $path)
            {
            }

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

        $this->assertSame(0, DB::connection('school')->table('reset_rows')->count());
        $id = DB::connection('school')->table('reset_rows')->insertGetId(['name' => 'Baru']);
        $this->assertSame(1, $id);
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
