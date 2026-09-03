<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('school')->table('transactions', function (Blueprint $table): void {
            $table->string('invoice_number', 80)->nullable();
            $table->date('invoice_date')->nullable();
            $table->string('invoice_status', 30)->nullable();
            $table->index(['fiscal_year_id', 'vendor_name', 'invoice_number'], 'transactions_invoice_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::connection('school')->table('transactions', function (Blueprint $table): void {
            $table->dropIndex('transactions_invoice_lookup_index');
            $table->dropColumn(['invoice_number', 'invoice_date', 'invoice_status']);
        });
    }
};
