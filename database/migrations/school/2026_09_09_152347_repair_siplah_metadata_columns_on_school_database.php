<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('school');

        $transactionColumns = [
            'siplah_metadata' => fn (Blueprint $table) => $table->json('siplah_metadata')->nullable(),
            'siplah_transaction_id' => fn (Blueprint $table) => $table->string('siplah_transaction_id', 180)->nullable(),
            'siplah_marketplace' => fn (Blueprint $table) => $table->string('siplah_marketplace', 180)->nullable(),
            'siplah_merchant_address' => fn (Blueprint $table) => $table->text('siplah_merchant_address')->nullable(),
            'siplah_payment_date' => fn (Blueprint $table) => $table->timestamp('siplah_payment_date')->nullable(),
            'siplah_dq_passed' => fn (Blueprint $table) => $table->boolean('siplah_dq_passed')->nullable(),
            'siplah_backfilled' => fn (Blueprint $table) => $table->boolean('siplah_backfilled')->nullable(),
            'siplah_budget_mapping_rejected' => fn (Blueprint $table) => $table->boolean('siplah_budget_mapping_rejected')->nullable(),
            'siplah_partially_mapped' => fn (Blueprint $table) => $table->boolean('siplah_partially_mapped')->nullable(),
        ];

        foreach ($transactionColumns as $column => $definition) {
            if (! $schema->hasColumn('transactions', $column)) {
                $schema->table('transactions', $definition);
            }
        }

        $itemColumns = [
            'siplah_item_mpid' => fn (Blueprint $table) => $table->string('siplah_item_mpid', 80)->nullable(),
            'rkas_item_code' => fn (Blueprint $table) => $table->string('rkas_item_code', 80)->nullable(),
            'rkas_item_name' => fn (Blueprint $table) => $table->text('rkas_item_name')->nullable(),
            'siplah_item_name' => fn (Blueprint $table) => $table->text('siplah_item_name')->nullable(),
            'siplah_mapped_quantity' => fn (Blueprint $table) => $table->decimal('siplah_mapped_quantity', 14, 2)->nullable(),
            'siplah_quantity_received' => fn (Blueprint $table) => $table->decimal('siplah_quantity_received', 14, 2)->nullable(),
            'siplah_unit_dpp' => fn (Blueprint $table) => $table->decimal('siplah_unit_dpp', 18, 2)->nullable(),
            'siplah_unit_ppn' => fn (Blueprint $table) => $table->decimal('siplah_unit_ppn', 18, 2)->nullable(),
            'siplah_unit_price' => fn (Blueprint $table) => $table->decimal('siplah_unit_price', 18, 2)->nullable(),
            'siplah_unit_insurance_cost' => fn (Blueprint $table) => $table->decimal('siplah_unit_insurance_cost', 18, 2)->nullable(),
            'siplah_unit_packaging_cost' => fn (Blueprint $table) => $table->decimal('siplah_unit_packaging_cost', 18, 2)->nullable(),
            'siplah_item_metadata' => fn (Blueprint $table) => $table->json('siplah_item_metadata')->nullable(),
        ];

        foreach ($itemColumns as $column => $definition) {
            if (! $schema->hasColumn('transaction_items', $column)) {
                $schema->table('transaction_items', $definition);
            }
        }
    }

    public function down(): void
    {
        // Repair migration is intentionally irreversible to preserve synced metadata.
    }
};
