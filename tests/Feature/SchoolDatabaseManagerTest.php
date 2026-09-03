<?php

namespace Tests\Feature;

use App\Models\School;
use App\Services\SchoolDatabaseManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class SchoolDatabaseManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    public function test_completed_school_schema_does_not_run_migrations_again(): void
    {
        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');

        Schema::connection('school')->create('transaction_items', function (Blueprint $table): void {
            $table->id();
            $table->text('item_description')->nullable();
        });
        Schema::connection('school')->create('spj_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transaction_item_id');
        });
        Schema::connection('school')->create('spj_travels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transaction_id');
            $table->string('assignment_letter_number')->nullable();
            $table->date('assignment_letter_date')->nullable();
        });
        Schema::connection('school')->create('spj_work_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('maintenance_id');
        });
        Schema::connection('school')->create('spj_honors', function (Blueprint $table): void {
            $table->id();
        });
        Schema::connection('school')->create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('vendor_name')->nullable();
            $table->decimal('ppn_rate')->nullable();
        });
        Schema::connection('school')->create('migrations', function (Blueprint $table): void {
            $table->id();
            $table->string('migration');
            $table->integer('batch');
        });
        foreach (File::files(database_path('migrations/school')) as $migration) {
            DB::connection('school')->table('migrations')->insert([
                'migration' => $migration->getBasename('.php'),
                'batch' => 1,
            ]);
        }

        Artisan::shouldReceive('call')->never();

        $school = new School(['npsn' => '12345678', 'name' => 'Sekolah Uji']);
        $manager = Mockery::mock(SchoolDatabaseManager::class)->makePartial();
        $manager->shouldReceive('activate')->once()->with($school);

        $manager->ensureMigrated($school);

        $this->assertTrue(Schema::connection('school')->hasTable('transactions'));
    }

    public function test_sqlite_connections_wait_for_concurrent_writes(): void
    {
        $this->assertSame(5000, config('database.connections.sqlite.busy_timeout'));
        $this->assertSame(5000, config('database.connections.school.busy_timeout'));
    }
}
