<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('school')->table('transactions', function (Blueprint $table): void {
            $table->index(['fiscal_year_id', 'fund_source_id', 'transaction_date', 'id'], 'transactions_context_date_index');
            $table->index(['fiscal_year_id', 'fund_source_id', 'status', 'id'], 'transactions_context_status_index');
            $table->index(['fiscal_year_id', 'fund_source_id', 'spj_category', 'transaction_date'], 'transactions_context_category_date_index');
        });

        Schema::connection('school')->table('spj_packages', function (Blueprint $table): void {
            $table->index(['status', 'document_number', 'id'], 'spj_packages_status_number_index');
        });
    }

    public function down(): void
    {
        Schema::connection('school')->table('transactions', function (Blueprint $table): void {
            $table->dropIndex('transactions_context_date_index');
            $table->dropIndex('transactions_context_status_index');
            $table->dropIndex('transactions_context_category_date_index');
        });

        Schema::connection('school')->table('spj_packages', function (Blueprint $table): void {
            $table->dropIndex('spj_packages_status_number_index');
        });
    }
};
