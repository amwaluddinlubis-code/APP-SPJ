<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spj_v2_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('spj_package_id')->unique()->constrained('spj_packages')->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignId('effective_fiscal_year_id')->constrained('fiscal_years')->restrictOnDelete();
            $table->foreignId('effective_fund_source_id')->constrained('fund_sources')->restrictOnDelete();
            $table->unsignedBigInteger('source_id');
            $table->unsignedTinyInteger('effective_quarter');
            $table->decimal('gross_amount', 18, 2);
            $table->decimal('paid_amount', 18, 2);
            $table->decimal('tax_amount', 18, 2);
            $table->decimal('net_amount', 18, 2);
            $table->string('status', 30)->default('SETTLED');
            $table->json('snapshot');
            $table->timestamp('settled_at');
            $table->foreignId('settled_by')->nullable();
            $table->timestamps();
            $table->index(['effective_fiscal_year_id', 'effective_fund_source_id', 'effective_quarter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spj_v2_settlements');
    }
};
