<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\SchoolDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PDO;
use Tests\TestCase;

class SpjQuarterAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    private ?string $tenantPath = null;

    protected function tearDown(): void
    {
        if ($this->tenantPath && File::exists($this->tenantPath)) {
            File::delete($this->tenantPath);
        }

        parent::tearDown();
    }

    public function test_audit_quarter_reads_existing_tenant_without_changing_database_bytes(): void
    {
        $school = School::create(['npsn' => '10208183', 'name' => 'SDN Audit']);
        $this->tenantPath = storage_path('framework/testing/spj-quarter-audit-'.uniqid().'.sqlite');
        File::ensureDirectoryExists(dirname($this->tenantPath));
        $this->buildTenantFixture($this->tenantPath);

        $database = SchoolDatabase::create([
            'school_id' => $school->id,
            'database_path' => $this->tenantPath,
            'status' => 'READY',
        ]);
        $recordUpdatedAt = $database->updated_at?->toISOString();
        $hashBefore = hash_file('sha256', $this->tenantPath);

        $this->artisan('spj:audit-quarter', [
            'npsn' => '10208183',
            '--quarter' => 1,
            '--year' => 2026,
        ])
            ->expectsOutputToContain('SPJ QUARTER AUDIT — READ ONLY')
            ->expectsOutputToContain('query_only=ON')
            ->expectsOutputToContain('BARANG')
            ->expectsOutputToContain('READ-ONLY GUARANTEE')
            ->assertExitCode(0);

        clearstatcache(true, $this->tenantPath);
        $this->assertSame($hashBefore, hash_file('sha256', $this->tenantPath));
        $this->assertSame($recordUpdatedAt, $database->fresh()->updated_at?->toISOString());
    }

    public function test_audit_quarter_never_creates_a_missing_tenant_database(): void
    {
        $school = School::create(['npsn' => '87654321', 'name' => 'SD Tanpa Database']);
        $missingPath = storage_path('framework/testing/missing-spj-audit-'.uniqid().'.sqlite');
        SchoolDatabase::create([
            'school_id' => $school->id,
            'database_path' => $missingPath,
            'status' => 'READY',
        ]);

        $this->artisan('spj:audit-quarter', [
            'npsn' => '87654321',
            '--quarter' => 1,
        ])
            ->expectsOutputToContain('tidak ditemukan')
            ->assertExitCode(1);

        $this->assertFileDoesNotExist($missingPath);
    }

    private function buildTenantFixture(string $path): void
    {
        $pdo = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec(<<<'SQL'
            CREATE TABLE fiscal_years (
                id INTEGER PRIMARY KEY,
                year INTEGER NOT NULL
            );
            CREATE TABLE transactions (
                id INTEGER PRIMARY KEY,
                fiscal_year_id INTEGER NOT NULL,
                fund_source_id INTEGER NULL,
                no_bukti TEXT NOT NULL,
                transaction_date TEXT NOT NULL,
                description TEXT NULL,
                payment_description TEXT NULL,
                spj_category TEXT NULL,
                recipient_name TEXT NULL,
                spj_recipient_name TEXT NULL,
                receipt_recipient_name TEXT NULL,
                gross_amount NUMERIC NOT NULL DEFAULT 0,
                ppn NUMERIC NOT NULL DEFAULT 0,
                pph21 NUMERIC NOT NULL DEFAULT 0,
                pph22 NUMERIC NOT NULL DEFAULT 0,
                pph23 NUMERIC NOT NULL DEFAULT 0,
                pph4 NUMERIC NOT NULL DEFAULT 0,
                sspd NUMERIC NOT NULL DEFAULT 0,
                tax_total NUMERIC NOT NULL DEFAULT 0,
                net_amount NUMERIC NOT NULL DEFAULT 0,
                source_status TEXT NOT NULL DEFAULT 'ACTIVE',
                requires_reconciliation INTEGER NOT NULL DEFAULT 0
            );
            CREATE TABLE transaction_items (
                id INTEGER PRIMARY KEY,
                transaction_id INTEGER NOT NULL,
                source_item_id TEXT NULL,
                description TEXT NOT NULL,
                item_description TEXT NULL,
                amount NUMERIC NOT NULL DEFAULT 0
            );
            CREATE TABLE spj_packages (
                id INTEGER PRIMARY KEY,
                transaction_id INTEGER NOT NULL UNIQUE,
                document_number TEXT NULL,
                status TEXT NOT NULL DEFAULT 'DRAFT',
                numbered_at TEXT NULL,
                finalized_at TEXT NULL,
                cancelled_at TEXT NULL
            );
            CREATE TABLE spj_goods (
                id INTEGER PRIMARY KEY,
                transaction_item_id INTEGER NOT NULL
            );
            CREATE TABLE spj_participants (
                id INTEGER PRIMARY KEY,
                transaction_item_id INTEGER NOT NULL,
                portions INTEGER NOT NULL DEFAULT 1
            );
            CREATE TABLE spj_work_orders (
                id INTEGER PRIMARY KEY,
                transaction_id INTEGER NOT NULL
            );
            CREATE TABLE spj_workers (
                id INTEGER PRIMARY KEY,
                work_order_id INTEGER NOT NULL
            );
            CREATE TABLE spj_travels (
                id INTEGER PRIMARY KEY,
                transaction_id INTEGER NOT NULL
            );
            CREATE TABLE spj_honors (
                id INTEGER PRIMARY KEY,
                transaction_item_id INTEGER NOT NULL
            );
            CREATE TABLE spj_service_recipients (
                id INTEGER PRIMARY KEY,
                transaction_id INTEGER NOT NULL,
                amount NUMERIC NOT NULL DEFAULT 0,
                tax_amount NUMERIC NOT NULL DEFAULT 0,
                net_amount NUMERIC NOT NULL DEFAULT 0
            );
            CREATE TABLE migrations (
                id INTEGER PRIMARY KEY,
                migration TEXT NOT NULL,
                batch INTEGER NOT NULL
            );
        SQL);

        $pdo->exec("INSERT INTO fiscal_years (id, year) VALUES (1, 2026)");
        $pdo->exec(<<<'SQL'
            INSERT INTO transactions (
                id, fiscal_year_id, fund_source_id, no_bukti, transaction_date,
                description, payment_description, spj_category, recipient_name, receipt_recipient_name,
                gross_amount, tax_total, net_amount, source_status, requires_reconciliation
            ) VALUES (
                10, 1, 1, 'BKU-001', '2026-02-10',
                'Pembelian ATK', 'Pembayaran ATK', 'BARANG', 'Toko Uji', 'Toko Uji',
                1000000, 0, 1000000, 'ACTIVE', 0
            )
        SQL);
        $pdo->exec("INSERT INTO transaction_items (id, transaction_id, description, item_description, amount) VALUES (100, 10, 'ATK', 'Alat tulis kantor', 1000000)");
        $pdo->exec("INSERT INTO spj_packages (id, transaction_id, status) VALUES (200, 10, 'DRAFT')");
        $pdo->exec("INSERT INTO spj_goods (id, transaction_item_id) VALUES (300, 100)");
        $pdo->exec("INSERT INTO migrations (id, migration, batch) VALUES (1, '2026_09_01_000000_create_complete_spj_tenant_tables', 1)");
        $pdo = null;
    }
}
