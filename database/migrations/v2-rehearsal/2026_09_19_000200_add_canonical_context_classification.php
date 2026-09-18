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

        $db = DB::connection('school');
        if (! Schema::connection('school')->hasColumn('spj_transactions', 'canonical_context_status')) {
            $db->statement("ALTER TABLE spj_transactions ADD COLUMN canonical_context_status VARCHAR(40) NOT NULL DEFAULT 'REQUIRES_REVIEW'");
        }
        if (! Schema::connection('school')->hasColumn('spj_transactions', 'canonical_context_reason')) {
            $db->statement('ALTER TABLE spj_transactions ADD COLUMN canonical_context_reason TEXT NULL');
        }
        if (! Schema::connection('school')->hasColumn('legacy_transaction_v2_map', 'canonical_context_status')) {
            $db->statement("ALTER TABLE legacy_transaction_v2_map ADD COLUMN canonical_context_status VARCHAR(40) NOT NULL DEFAULT 'REQUIRES_REVIEW'");
        }
        if (! Schema::connection('school')->hasColumn('legacy_transaction_v2_map', 'canonical_context_reason')) {
            $db->statement('ALTER TABLE legacy_transaction_v2_map ADD COLUMN canonical_context_reason TEXT NULL');
        }
    }

    public function down(): void
    {
        // Rehearsal migrations are additive and are never rolled back on an original tenant.
    }
};
