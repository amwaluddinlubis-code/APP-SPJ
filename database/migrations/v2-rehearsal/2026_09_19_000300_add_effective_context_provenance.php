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
        if (! Schema::connection('school')->hasColumn('legacy_transaction_v2_map', 'legacy_fiscal_year_id')) {
            $db->statement('ALTER TABLE legacy_transaction_v2_map ADD COLUMN legacy_fiscal_year_id INTEGER NULL');
        }
        if (! Schema::connection('school')->hasColumn('legacy_transaction_v2_map', 'effective_context_key')) {
            $db->statement('ALTER TABLE legacy_transaction_v2_map ADD COLUMN effective_context_key VARCHAR(180) NULL');
        }
    }

    public function down(): void
    {
        // Rehearsal migrations are additive and are never rolled back on an original tenant.
    }
};
