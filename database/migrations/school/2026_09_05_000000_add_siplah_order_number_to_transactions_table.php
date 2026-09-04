<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('school')->table('transactions', function (Blueprint $table): void {
            $table->string('siplah_order_number', 100)->nullable()->after('is_siplah');
            $table->index(['fiscal_year_id', 'siplah_order_number'], 'transactions_siplah_order_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::connection('school')->table('transactions', function (Blueprint $table): void {
            $table->dropIndex('transactions_siplah_order_lookup_index');
            $table->dropColumn('siplah_order_number');
        });
    }
};
