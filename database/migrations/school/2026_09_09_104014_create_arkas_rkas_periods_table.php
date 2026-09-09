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
        Schema::create('arkas_rkas_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('fund_source_id')->nullable();
            $table->string('source_rapbs_id', 100);
            $table->string('source_period_id', 30);
            $table->string('period_name')->nullable();
            $table->unsignedTinyInteger('month_number')->nullable();
            $table->unsignedTinyInteger('quarter_number')->nullable();
            $table->unsignedTinyInteger('semester_number')->nullable();
            $table->decimal('volume', 18, 4)->default(0);
            $table->decimal('amount', 18, 2)->default(0);
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique(['fiscal_year_id', 'fund_source_id', 'source_rapbs_id', 'source_period_id'], 'arkas_rkas_periods_unique');
            $table->index(['fiscal_year_id', 'fund_source_id', 'month_number']);
            $table->index(['fiscal_year_id', 'fund_source_id', 'quarter_number']);
            $table->index(['fiscal_year_id', 'fund_source_id', 'semester_number']);
            $table->foreign('fund_source_id')->references('id')->on('fund_sources')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arkas_rkas_periods');
    }
};
