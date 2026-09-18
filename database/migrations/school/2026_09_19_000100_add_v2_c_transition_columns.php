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

        if (! Schema::connection('school')->hasColumn('spj_packages', 'spj_transaction_id')) {
            DB::connection('school')->statement('ALTER TABLE spj_packages ADD COLUMN spj_transaction_id INTEGER REFERENCES spj_transactions(id) ON DELETE SET NULL');
            DB::connection('school')->statement('CREATE INDEX spj_packages_v2_transaction_index ON spj_packages (spj_transaction_id)');
        }

        if (! Schema::connection('school')->hasColumn('legacy_transaction_v2_map', 'legacy_source_key')) {
            DB::connection('school')->statement('ALTER TABLE legacy_transaction_v2_map ADD COLUMN legacy_source_key VARCHAR(160) NULL');
        }
        if (! Schema::connection('school')->hasColumn('legacy_transaction_v2_map', 'current_membership_hash')) {
            DB::connection('school')->statement('ALTER TABLE legacy_transaction_v2_map ADD COLUMN current_membership_hash VARCHAR(64) NULL');
        }
    }

    public function down(): void
    {
        if (Schema::connection('school')->hasTable('legacy_transaction_v2_map')) {
            Schema::connection('school')->table('legacy_transaction_v2_map', function (Blueprint $table): void {
                if (Schema::connection('school')->hasColumn('legacy_transaction_v2_map', 'current_membership_hash')) {
                    $table->dropColumn('current_membership_hash');
                }
                if (Schema::connection('school')->hasColumn('legacy_transaction_v2_map', 'legacy_source_key')) {
                    $table->dropColumn('legacy_source_key');
                }
            });
        }

        if (Schema::connection('school')->hasColumn('spj_packages', 'spj_transaction_id')) {
            Schema::connection('school')->table('spj_packages', function (Blueprint $table): void {
                $table->dropForeign(['spj_transaction_id']);
                $table->dropIndex('spj_packages_v2_transaction_index');
                $table->dropColumn('spj_transaction_id');
            });
        }
    }
};
