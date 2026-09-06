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
        Schema::connection('school')->table('transactions', function (Blueprint $table): void {
            $table->timestamp('source_created_at')->nullable()->after('transaction_date');
            $table->timestamp('source_last_updated_at')->nullable()->after('source_created_at');
            $table->index(
                ['fiscal_year_id', 'fund_source_id', 'transaction_date', 'source_created_at'],
                'transactions_bku_numbering_order_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('school')->table('transactions', function (Blueprint $table): void {
            $table->dropIndex('transactions_bku_numbering_order_index');
            $table->dropColumn(['source_created_at', 'source_last_updated_at']);
        });
    }
};
