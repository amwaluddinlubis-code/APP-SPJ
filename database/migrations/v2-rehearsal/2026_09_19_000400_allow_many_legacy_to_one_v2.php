<?php

use App\Services\V2BIsolatedDatabaseGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        app(V2BIsolatedDatabaseGuard::class)->assertMigrationTarget(
            DB::connection('school'),
            config('spj.v2_b_isolated_manifest'),
        );

        if (! Schema::connection('school')->hasTable('legacy_transaction_v2_map')) {
            throw new RuntimeException('Cannot widen V2 bridge before the base rehearsal schema exists.');
        }

        // Keep one provenance row per legacy transaction, but allow multiple
        // legacy rows to point to one canonical V2 transaction.
        if (Schema::connection('school')->hasIndex('legacy_transaction_v2_map', 'legacy_transaction_v2_map_spj_unique')) {
            Schema::connection('school')->table('legacy_transaction_v2_map', function ($table): void {
                $table->dropUnique('legacy_transaction_v2_map_spj_unique');
            });
        }

        if (! Schema::connection('school')->hasIndex('legacy_transaction_v2_map', 'legacy_transaction_v2_map_spj_index')) {
            Schema::connection('school')->table('legacy_transaction_v2_map', function ($table): void {
                $table->index('spj_transaction_id', 'legacy_transaction_v2_map_spj_index');
            });
        }
    }

    public function down(): void
    {
        // Rehearsal migrations are additive and are never rolled back on an original tenant.
    }
};
