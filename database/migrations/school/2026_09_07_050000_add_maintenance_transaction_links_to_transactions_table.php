<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('school')->table('transactions', function (Blueprint $table): void {
            $table->foreignId('maintenance_material_transaction_id')
                ->nullable()
                ->after('participant_count')
                ->constrained('transactions')
                ->nullOnDelete();
            $table->foreignId('maintenance_labor_transaction_id')
                ->nullable()
                ->after('maintenance_material_transaction_id')
                ->constrained('transactions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('school')->table('transactions', function (Blueprint $table): void {
            $table->dropForeign(['maintenance_material_transaction_id']);
            $table->dropForeign(['maintenance_labor_transaction_id']);
            $table->dropColumn(['maintenance_material_transaction_id', 'maintenance_labor_transaction_id']);
        });
    }
};
