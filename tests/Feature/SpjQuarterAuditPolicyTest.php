<?php

namespace Tests\Feature;

use App\Services\SpjQuarterAuditService;
use Illuminate\Support\Facades\File;
use PDO;
use Tests\TestCase;

class SpjQuarterAuditPolicyTest extends TestCase
{
    private ?string $path = null;

    protected function tearDown(): void
    {
        if ($this->path && File::exists($this->path)) {
            File::delete($this->path);
        }

        parent::tearDown();
    }

    public function test_siplah_barang_does_not_require_internal_goods_rows(): void
    {
        $result = $this->auditFixture();

        $codes = collect($result['anomalies'])
            ->where('transaction_id', 1)
            ->pluck('code');

        $this->assertNotContains('BARANG_DETAIL_EMPTY', $codes);
        $this->assertSame([], $result['candidates']['BARANG']['issues']);
    }

    public function test_category_reconciliation_warning_disqualifies_clean_candidate(): void
    {
        $result = $this->auditFixture();

        $candidate = $result['candidates']['JASA_LAINNYA'];
        $this->assertContains('service_recipient_reconciliation', $candidate['issues']);

        $category = collect($result['categories'])->firstWhere('category', 'JASA_LAINNYA');
        $this->assertSame(0, $category['candidate_count']);
    }

    /** @return array<string, mixed> */
    private function auditFixture(): array
    {
        $this->path = storage_path('framework/testing/spj-quarter-policy-'.uniqid().'.sqlite');
        File::ensureDirectoryExists(dirname($this->path));
        $pdo = new PDO('sqlite:'.$this->path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec(<<<'SQL'
            CREATE TABLE fiscal_years (id INTEGER PRIMARY KEY, year INTEGER NOT NULL);
            CREATE TABLE transactions (
                id INTEGER PRIMARY KEY, fiscal_year_id INTEGER NOT NULL, fund_source_id INTEGER NULL,
                no_bukti TEXT NOT NULL, transaction_date TEXT NOT NULL, spj_category TEXT NULL,
                payment_method TEXT NULL, is_siplah INTEGER NOT NULL DEFAULT 0,
                siplah_order_number TEXT NULL, payment_reference TEXT NULL, invoice_number TEXT NULL,
                gross_amount NUMERIC NOT NULL DEFAULT 0, ppn NUMERIC NOT NULL DEFAULT 0,
                pph21 NUMERIC NOT NULL DEFAULT 0, pph22 NUMERIC NOT NULL DEFAULT 0,
                pph23 NUMERIC NOT NULL DEFAULT 0, pph4 NUMERIC NOT NULL DEFAULT 0,
                sspd NUMERIC NOT NULL DEFAULT 0, tax_total NUMERIC NOT NULL DEFAULT 0,
                net_amount NUMERIC NOT NULL DEFAULT 0, source_status TEXT NOT NULL DEFAULT 'ACTIVE',
                requires_reconciliation INTEGER NOT NULL DEFAULT 0
            );
            CREATE TABLE transaction_items (
                id INTEGER PRIMARY KEY, transaction_id INTEGER NOT NULL, source_item_id TEXT NULL,
                description TEXT NOT NULL, item_description TEXT NULL, amount NUMERIC NOT NULL DEFAULT 0
            );
            CREATE TABLE spj_packages (
                id INTEGER PRIMARY KEY, transaction_id INTEGER NOT NULL UNIQUE, document_number TEXT NULL,
                status TEXT NOT NULL DEFAULT 'DRAFT', numbered_at TEXT NULL, finalized_at TEXT NULL, cancelled_at TEXT NULL
            );
            CREATE TABLE spj_goods (id INTEGER PRIMARY KEY, transaction_item_id INTEGER NOT NULL);
            CREATE TABLE spj_participants (id INTEGER PRIMARY KEY, transaction_item_id INTEGER NOT NULL, portions INTEGER NOT NULL DEFAULT 1);
            CREATE TABLE spj_work_orders (id INTEGER PRIMARY KEY, transaction_id INTEGER NOT NULL);
            CREATE TABLE spj_workers (id INTEGER PRIMARY KEY, work_order_id INTEGER NOT NULL);
            CREATE TABLE spj_travels (id INTEGER PRIMARY KEY, transaction_id INTEGER NOT NULL);
            CREATE TABLE spj_honors (id INTEGER PRIMARY KEY, transaction_item_id INTEGER NOT NULL);
            CREATE TABLE spj_service_recipients (
                id INTEGER PRIMARY KEY, transaction_id INTEGER NOT NULL, amount NUMERIC NOT NULL DEFAULT 0,
                tax_amount NUMERIC NOT NULL DEFAULT 0, net_amount NUMERIC NOT NULL DEFAULT 0
            );
        SQL);

        $pdo->exec("INSERT INTO fiscal_years (id, year) VALUES (1, 2026)");
        $pdo->exec(<<<'SQL'
            INSERT INTO transactions (
                id, fiscal_year_id, fund_source_id, no_bukti, transaction_date, spj_category,
                payment_method, is_siplah, siplah_order_number, gross_amount, tax_total, net_amount
            ) VALUES
                (1, 1, 1, 'BPU-SIPLAH', '2026-03-01', 'BARANG', 'siplah', 1, 'SIPL-001', 100000, 0, 100000),
                (2, 1, 1, 'BPU-JASA', '2026-03-02', 'JASA_LAINNYA', 'tunai', 0, NULL, 100000, 10000, 90000)
        SQL);
        $pdo->exec("INSERT INTO transaction_items (id, transaction_id, description, item_description, amount) VALUES (11, 1, 'Barang', 'Barang SiPLah', 100000), (12, 2, 'Jasa', 'Jasa tenaga', 100000)");
        $pdo->exec("INSERT INTO spj_packages (id, transaction_id, status) VALUES (21, 1, 'DRAFT'), (22, 2, 'DRAFT')");
        $pdo->exec("INSERT INTO spj_service_recipients (id, transaction_id, amount, tax_amount, net_amount) VALUES (31, 2, 100000, 0, 100000)");
        $pdo = null;

        return app(SpjQuarterAuditService::class)->audit($this->path, 1, 2026);
    }
}
