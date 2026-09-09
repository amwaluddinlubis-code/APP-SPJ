<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\SchoolBackup;
use App\Models\SchoolDatabase;
use App\Services\SchoolBackupService;
use App\Services\SchoolDatabaseManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use RuntimeException;
use Tests\TestCase;

class SchoolDatabaseMaintenanceHardeningTest extends TestCase
{
    private string $dataPath;

    private string $centralPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralPath = storage_path('framework/testing/p0-07-central-'.uniqid().'.sqlite');
        File::ensureDirectoryExists(dirname($this->centralPath));
        File::put($this->centralPath, '');
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', $this->centralPath);
        config()->set('database.connections.sqlite.journal_mode', null);
        DB::purge('sqlite');
        Artisan::call('migrate:fresh', ['--database' => 'sqlite', '--force' => true]);

        $this->dataPath = storage_path('framework/testing/p0-07-'.uniqid());
        File::ensureDirectoryExists($this->dataPath);
        config()->set('spj.data_path', $this->dataPath);
        config()->set('spj.backup_retention', 1);
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::purge('school');
        DB::purge('sqlite');
        File::deleteDirectory($this->dataPath);
        foreach ([$this->centralPath, $this->centralPath.'-wal', $this->centralPath.'-shm'] as $file) {
            File::delete($file);
        }

        parent::tearDown();
    }

    public function test_restore_preserves_source_backup_creates_rollback_and_keeps_tenants_switchable(): void
    {
        $first = $this->makeSchool('10000001', 'TENANT-1');
        $second = $this->makeSchool('10000002', 'TENANT-2');
        $manager = app(SchoolDatabaseManager::class);
        $backups = app(SchoolBackupService::class);

        $backup = $backups->create($first, 'MANUAL', null);
        $backupPath = $this->backupPath($backup);
        $this->assertTrue(File::exists($backupPath));
        $this->assertSame(['TENANT-1'], $this->databaseValues($backupPath));

        $manager->activate($first);
        DB::connection('school')->table('maintenance_rows')->delete();
        $mutatedId = DB::connection('school')->table('maintenance_rows')->insertGetId(['value' => 'MUTATED']);
        $this->assertSame(2, $mutatedId);

        $backups->restore($first, $backup, null);

        $this->assertTrue(File::exists($backupPath), 'Backup sumber restore tidak boleh terhapus oleh retention.');
        $this->assertDatabaseHas('school_backups', ['id' => $backup->id, 'school_id' => $first->id]);
        $this->assertSame(2, SchoolBackup::query()->where('school_id', $first->id)->count());

        $manager->activate($first);
        $this->assertSame(['TENANT-1'], DB::connection('school')->table('maintenance_rows')->pluck('value')->all());
        $this->assertSame(1, (int) DB::connection('school')->table('sqlite_sequence')->where('name', 'maintenance_rows')->value('seq'));
        $this->assertFalse(File::exists($first->databaseRecord->database_path.'.restore.tmp'));

        $rollback = SchoolBackup::query()
            ->where('school_id', $first->id)
            ->where('reason', 'SEBELUM_PEMULIHAN')
            ->sole();
        $this->assertSame(['MUTATED'], $this->databaseValues($this->backupPath($rollback)));

        $this->assertDatabaseHas('schools', ['id' => $first->id, 'npsn' => '10000001']);
        $this->assertDatabaseHas('schools', ['id' => $second->id, 'npsn' => '10000002']);
        $this->assertDatabaseCount('school_databases', 2);

        $manager->activate($second);
        $this->assertSame(['TENANT-2'], DB::connection('school')->table('maintenance_rows')->pluck('value')->all());
        $manager->activate($first);
        $this->assertSame(['TENANT-1'], DB::connection('school')->table('maintenance_rows')->pluck('value')->all());
    }

    public function test_corrupt_backup_is_rejected_before_active_tenant_is_replaced(): void
    {
        $school = $this->makeSchool('10000003', 'SAFE-TENANT');
        $corruptPath = $this->dataPath.DIRECTORY_SEPARATOR.'backups'.DIRECTORY_SEPARATOR.$school->npsn.DIRECTORY_SEPARATOR.'corrupt.sqlite';
        File::ensureDirectoryExists(dirname($corruptPath));
        File::put($corruptPath, 'not-a-sqlite-database');

        $backup = SchoolBackup::query()->create([
            'school_id' => $school->id,
            'file_path' => 'backups/'.$school->npsn.'/corrupt.sqlite',
            'file_name' => 'corrupt.sqlite',
            'file_size' => File::size($corruptPath),
            'reason' => 'MANUAL',
            'created_by' => null,
        ]);

        try {
            app(SchoolBackupService::class)->restore($school, $backup, null);
            $this->fail('Backup rusak seharusnya ditolak sebelum database aktif diganti.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Berkas backup', $exception->getMessage());
        }

        app(SchoolDatabaseManager::class)->activate($school);
        $this->assertSame(['SAFE-TENANT'], DB::connection('school')->table('maintenance_rows')->pluck('value')->all());
        $this->assertSame(1, SchoolBackup::query()->where('school_id', $school->id)->count());
    }

    public function test_failed_post_restore_integrity_check_rolls_back_pre_restore_database(): void
    {
        config()->set('spj.backup_retention', 3);
        $school = $this->makeSchool('10000004', 'ORIGINAL');
        $manager = new class extends SchoolDatabaseManager
        {
            public bool $failNextIntegrityCheck = false;

            public function integrityCheck(School $school): string
            {
                if ($this->failNextIntegrityCheck) {
                    $this->failNextIntegrityCheck = false;

                    return 'forced-integrity-failure';
                }

                return parent::integrityCheck($school);
            }
        };
        $backups = new SchoolBackupService($manager);
        $backup = $backups->create($school, 'MANUAL', null);

        $manager->activate($school);
        DB::connection('school')->table('maintenance_rows')->delete();
        DB::connection('school')->table('maintenance_rows')->insert(['value' => 'MUTATED-BEFORE-RESTORE']);
        $manager->failNextIntegrityCheck = true;

        try {
            $backups->restore($school, $backup, null);
            $this->fail('Restore wajib dianggap gagal ketika integrity check hasil pemulihan gagal.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('rollback otomatis dijalankan', $exception->getMessage());
        }

        $manager->activate($school);
        $this->assertSame(
            ['MUTATED-BEFORE-RESTORE'],
            DB::connection('school')->table('maintenance_rows')->pluck('value')->all(),
        );
        $this->assertTrue(File::exists($this->backupPath($backup)));
        $this->assertSame(
            ['ORIGINAL'],
            $this->databaseValues($this->backupPath($backup)),
            'Backup sumber harus tetap menjadi snapshot yang dipilih operator.',
        );
        $this->assertSame(
            ['MUTATED-BEFORE-RESTORE'],
            $this->databaseValues($this->backupPath(
                SchoolBackup::query()->where('school_id', $school->id)->where('reason', 'SEBELUM_PEMULIHAN')->sole(),
            )),
        );
    }

    public function test_same_second_backups_use_distinct_artifact_paths(): void
    {
        config()->set('spj.backup_retention', 5);
        Carbon::setTestNow('2026-09-10 08:00:00');
        $school = $this->makeSchool('10000005', 'UNIQUE-BACKUP');
        $backups = app(SchoolBackupService::class);

        $first = $backups->create($school, 'MANUAL', null);
        $second = $backups->create($school, 'MANUAL', null);

        $this->assertNotSame($first->file_path, $second->file_path);
        $this->assertTrue(File::exists($this->backupPath($first)));
        $this->assertTrue(File::exists($this->backupPath($second)));
        $this->assertSame(2, SchoolBackup::query()->where('school_id', $school->id)->count());
    }

    private function makeSchool(string $npsn, string $value): School
    {
        $school = School::query()->create([
            'npsn' => $npsn,
            'name' => 'Sekolah '.$npsn,
        ]);
        $folder = $this->dataPath.DIRECTORY_SEPARATOR.'school-databases'.DIRECTORY_SEPARATOR.$npsn;
        File::ensureDirectoryExists($folder);
        $path = $folder.DIRECTORY_SEPARATOR.'spj.sqlite';
        $this->createTenantDatabase($path, $value);

        SchoolDatabase::query()->create([
            'school_id' => $school->id,
            'database_path' => $path,
            'status' => 'READY',
        ]);
        $school->unsetRelation('databaseRecord');

        return $school;
    }

    private function createTenantDatabase(string $path, string $value): void
    {
        $pdo = new PDO('sqlite:'.$path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('CREATE TABLE maintenance_rows (id INTEGER PRIMARY KEY AUTOINCREMENT, value TEXT NOT NULL)');
        $statement = $pdo->prepare('INSERT INTO maintenance_rows (value) VALUES (?)');
        $statement->execute([$value]);
        $pdo = null;
    }

    /** @return array<int,string> */
    private function databaseValues(string $path): array
    {
        $pdo = new PDO('sqlite:'.$path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $values = $pdo->query('SELECT value FROM maintenance_rows ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        $pdo = null;

        return array_map('strval', $values);
    }

    private function backupPath(SchoolBackup $backup): string
    {
        return $this->dataPath.DIRECTORY_SEPARATOR.$backup->file_path;
    }
}
