<?php

use App\Services\V2BIsolatedDatabaseGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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

        $v2Tables = [
            'arkas_source_identity_registry',
            'spj_transactions',
            'spj_transaction_sources',
            'spj_transaction_overlays',
            'spj_item_overlays',
            'legacy_transaction_v2_map',
        ];
        $existingTables = array_values(array_filter($v2Tables, fn (string $table): bool => Schema::connection('school')->hasTable($table)));
        if ($existingTables !== [] && count($existingTables) !== count($v2Tables)) {
            throw new RuntimeException('V2-B rehearsal schema is partial; existing tables: '.implode(', ', $existingTables));
        }
        if (count($existingTables) === count($v2Tables)) {
            return;
        }

        Schema::connection('school')->create('arkas_source_identity_registry', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('source_id');
            $table->string('source_table', 120);
            $table->string('source_key', 160);
            $table->json('primary_key_json');
            $table->string('identity_type', 40);
            $table->unsignedBigInteger('current_raw_mirror_row_id')->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->string('source_status', 30)->default('ACTIVE');
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();
            $table->unique(['source_id', 'source_table', 'source_key'], 'arkas_source_identity_boundary_unique');
            $table->index(['source_id', 'source_table', 'source_status'], 'arkas_source_identity_status_index');
            $table->foreign('current_raw_mirror_row_id', 'arkas_source_identity_raw_row_fk')
                ->references('id')->on('arkas_raw_mirror_rows')->nullOnDelete();
        });

        Schema::connection('school')->create('spj_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fiscal_year_id');
            $table->unsignedBigInteger('fund_source_id');
            $table->unsignedBigInteger('source_id');
            $table->string('source_membership_hash', 64);
            $table->string('source_status', 30)->default('ACTIVE');
            $table->boolean('requires_reconciliation')->default(false);
            $table->timestamp('source_missing_since')->nullable();
            $table->timestamps();
            $table->index(['fiscal_year_id', 'fund_source_id'], 'spj_v2_transaction_context_index');
            $table->unique(['fiscal_year_id', 'fund_source_id', 'source_id', 'source_membership_hash'], 'spj_v2_transaction_identity_unique');
        });

        Schema::connection('school')->create('spj_transaction_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('spj_transaction_id')->constrained('spj_transactions')->cascadeOnDelete();
            $table->foreignId('arkas_source_identity_id')->constrained('arkas_source_identity_registry')->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['spj_transaction_id', 'arkas_source_identity_id'], 'spj_v2_transaction_source_unique');
            $table->index(['spj_transaction_id', 'sort_order'], 'spj_v2_transaction_source_order_index');
        });

        Schema::connection('school')->create('spj_transaction_overlays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('spj_transaction_id')->unique()->constrained('spj_transactions')->cascadeOnDelete();
            $table->string('spj_category', 40)->nullable();
            $table->text('payment_description')->nullable();
            $table->string('payment_method', 40)->nullable();
            $table->string('payment_reference', 160)->nullable();
            $table->string('receipt_recipient_name', 200)->nullable();
            $table->json('operator_metadata')->nullable();
            $table->timestamps();
        });

        Schema::connection('school')->create('spj_item_overlays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('spj_transaction_source_id')->unique()->constrained('spj_transaction_sources')->cascadeOnDelete();
            $table->text('item_description')->nullable();
            $table->json('operator_metadata')->nullable();
            $table->timestamps();
        });

        Schema::connection('school')->create('legacy_transaction_v2_map', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('legacy_transaction_id');
            $table->foreignId('spj_transaction_id')->constrained('spj_transactions')->restrictOnDelete();
            $table->string('mapping_status', 30);
            $table->text('mapping_reason')->nullable();
            $table->timestamps();
            $table->unique('legacy_transaction_id', 'legacy_transaction_v2_map_legacy_unique');
            $table->unique('spj_transaction_id', 'legacy_transaction_v2_map_spj_unique');
            $table->index('mapping_status', 'legacy_transaction_v2_map_status_index');
        });
    }

    public function down(): void
    {
        Schema::connection('school')->dropIfExists('legacy_transaction_v2_map');
        Schema::connection('school')->dropIfExists('spj_item_overlays');
        Schema::connection('school')->dropIfExists('spj_transaction_overlays');
        Schema::connection('school')->dropIfExists('spj_transaction_sources');
        Schema::connection('school')->dropIfExists('spj_transactions');
        Schema::connection('school')->dropIfExists('arkas_source_identity_registry');
    }
};
