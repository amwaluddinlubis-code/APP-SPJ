<?php

namespace Tests\Feature;

use App\Services\SpjReadPathSelector;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class V2DReadPathSelectorTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    public function test_default_configuration_keeps_legacy_even_when_v2_is_resolvable(): void
    {
        $db = $this->database();
        $this->createSchema();
        $this->insertCanonical($db, 4, 1, 7);
        config()->set('spj.v2_read_path', 'legacy');

        $selection = app(SpjReadPathSelector::class)->select($db, 4, 1);

        $this->assertSame('legacy', $selection['path']);
        $this->assertNull($selection['source_id']);
        $this->assertSame('NOT_RESOLVED', $selection['source_status']);
    }

    public function test_invalid_configuration_fails_safe_to_legacy(): void
    {
        $db = $this->database();
        $this->createSchema();
        $this->insertCanonical($db, 4, 1, 7);
        config()->set('spj.v2_read_path', 'experimental');

        $selection = app(SpjReadPathSelector::class)->select($db, 4, 1);

        $this->assertSame('legacy', $selection['path']);
        $this->assertSame('experimental', $selection['requested']);
        $this->assertNull($selection['source_id']);
    }

    public function test_explicit_v2_requires_exactly_one_canonical_source(): void
    {
        $db = $this->database();
        $this->createSchema();
        $this->insertCanonical($db, 4, 1, 7);
        config()->set('spj.v2_read_path', 'v2');

        $selection = app(SpjReadPathSelector::class)->select($db, 4, 1);

        $this->assertSame('v2', $selection['path']);
        $this->assertSame(7, $selection['source_id']);
        $this->assertSame('RESOLVED', $selection['source_status']);
    }

    public function test_v2_request_falls_back_when_context_has_no_canonical_source(): void
    {
        $db = $this->database();
        $this->createSchema();
        $this->insertCanonical($db, 6, 1, 7);
        config()->set('spj.v2_read_path', 'v2');

        $selection = app(SpjReadPathSelector::class)->select($db, 4, 1);

        $this->assertSame('legacy', $selection['path']);
        $this->assertNull($selection['source_id']);
        $this->assertSame('UNAVAILABLE', $selection['source_status']);
    }

    public function test_v2_request_falls_back_when_context_has_multiple_sources(): void
    {
        $db = $this->database();
        $this->createSchema();
        $this->insertCanonical($db, 4, 1, 7);
        $this->insertCanonical($db, 4, 1, 9, 'hash-9');
        config()->set('spj.v2_read_path', 'v2');

        $selection = app(SpjReadPathSelector::class)->select($db, 4, 1);

        $this->assertSame('legacy', $selection['path']);
        $this->assertNull($selection['source_id']);
        $this->assertSame('AMBIGUOUS', $selection['source_status']);
    }

    public function test_v2_request_falls_back_when_v2_schema_is_missing(): void
    {
        $db = $this->database();
        config()->set('spj.v2_read_path', 'v2');

        $selection = app(SpjReadPathSelector::class)->select($db, 4, 1);

        $this->assertSame('legacy', $selection['path']);
        $this->assertNull($selection['source_id']);
        $this->assertSame('UNAVAILABLE', $selection['source_status']);
    }

    public function test_rollback_to_legacy_is_configuration_only_and_immediate(): void
    {
        $db = $this->database();
        $this->createSchema();
        $this->insertCanonical($db, 4, 1, 7);

        config()->set('spj.v2_read_path', 'v2');
        $this->assertSame('v2', app(SpjReadPathSelector::class)->select($db, 4, 1)['path']);

        config()->set('spj.v2_read_path', 'legacy');
        $rolledBack = app(SpjReadPathSelector::class)->select($db, 4, 1);

        $this->assertSame('legacy', $rolledBack['path']);
        $this->assertNull($rolledBack['source_id']);
        $this->assertSame(1, $db->table('spj_transactions')->count());
    }

    private function database(): Connection
    {
        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');

        return DB::connection('school');
    }

    private function createSchema(): void
    {
        Schema::connection('school')->create('spj_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fiscal_year_id');
            $table->unsignedBigInteger('fund_source_id');
            $table->unsignedBigInteger('source_id');
            $table->string('source_membership_hash', 64);
            $table->string('source_status', 30)->default('ACTIVE');
            $table->string('canonical_context_status', 40)->default('REQUIRES_REVIEW');
        });
    }

    private function insertCanonical(
        Connection $db,
        int $fiscalYearId,
        int $fundSourceId,
        int $sourceId,
        string $membershipHash = 'hash-7',
    ): void {
        $db->table('spj_transactions')->insert([
            'fiscal_year_id' => $fiscalYearId,
            'fund_source_id' => $fundSourceId,
            'source_id' => $sourceId,
            'source_membership_hash' => $membershipHash,
            'source_status' => 'ACTIVE',
            'canonical_context_status' => 'ACTIVE_CANONICAL',
        ]);
    }
}
