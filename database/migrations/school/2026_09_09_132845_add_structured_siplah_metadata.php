<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->string('siplah_transaction_id', 180)->nullable()->after('siplah_metadata');
            $table->string('siplah_marketplace', 180)->nullable()->after('siplah_transaction_id');
            $table->text('siplah_merchant_address')->nullable()->after('siplah_marketplace');
            $table->timestamp('siplah_payment_date')->nullable()->after('siplah_merchant_address');
            $table->boolean('siplah_dq_passed')->nullable()->after('siplah_payment_date');
            $table->boolean('siplah_backfilled')->nullable()->after('siplah_dq_passed');
            $table->boolean('siplah_budget_mapping_rejected')->nullable()->after('siplah_backfilled');
            $table->boolean('siplah_partially_mapped')->nullable()->after('siplah_budget_mapping_rejected');
            $table->index(['fiscal_year_id', 'siplah_transaction_id'], 'transactions_siplah_transaction_index');
        });

        Schema::table('transaction_items', function (Blueprint $table): void {
            $table->string('siplah_item_mpid', 80)->nullable()->after('source_item_id');
            $table->string('rkas_item_code', 80)->nullable()->after('siplah_item_mpid');
            $table->text('rkas_item_name')->nullable()->after('rkas_item_code');
            $table->text('siplah_item_name')->nullable()->after('rkas_item_name');
            $table->decimal('siplah_mapped_quantity', 14, 2)->nullable()->after('siplah_item_name');
            $table->decimal('siplah_quantity_received', 14, 2)->nullable()->after('siplah_mapped_quantity');
            $table->decimal('siplah_unit_dpp', 18, 2)->nullable()->after('siplah_quantity_received');
            $table->decimal('siplah_unit_ppn', 18, 2)->nullable()->after('siplah_unit_dpp');
            $table->decimal('siplah_unit_price', 18, 2)->nullable()->after('siplah_unit_ppn');
            $table->decimal('siplah_unit_insurance_cost', 18, 2)->nullable()->after('siplah_unit_price');
            $table->decimal('siplah_unit_packaging_cost', 18, 2)->nullable()->after('siplah_unit_insurance_cost');
            $table->json('siplah_item_metadata')->nullable()->after('siplah_unit_packaging_cost');
            $table->index(['transaction_id', 'siplah_item_mpid'], 'transaction_items_siplah_mpid_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaction_items', function (Blueprint $table): void {
            $table->dropIndex('transaction_items_siplah_mpid_index');
            $table->dropColumn(['siplah_item_mpid', 'rkas_item_code', 'rkas_item_name', 'siplah_item_name', 'siplah_mapped_quantity', 'siplah_quantity_received', 'siplah_unit_dpp', 'siplah_unit_ppn', 'siplah_unit_price', 'siplah_unit_insurance_cost', 'siplah_unit_packaging_cost', 'siplah_item_metadata']);
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropIndex('transactions_siplah_transaction_index');
            $table->dropColumn(['siplah_transaction_id', 'siplah_marketplace', 'siplah_merchant_address', 'siplah_payment_date', 'siplah_dq_passed', 'siplah_backfilled', 'siplah_budget_mapping_rejected', 'siplah_partially_mapped']);
        });
    }
};
