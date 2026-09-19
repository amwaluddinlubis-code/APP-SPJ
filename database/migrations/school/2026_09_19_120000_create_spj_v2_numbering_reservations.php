<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spj_v2_numbering_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('spj_package_id')->constrained('spj_packages')->restrictOnDelete();
            $table->foreignId('effective_fiscal_year_id')->constrained('fiscal_years')->restrictOnDelete();
            $table->foreignId('fund_source_id')->constrained('fund_sources')->restrictOnDelete();
            $table->unsignedTinyInteger('effective_quarter');
            $table->string('document_type', 40);
            $table->string('scope_key', 80)->default('MAIN');
            $table->string('period_key', 40);
            $table->string('context_key', 255);
            $table->string('intent_key', 180)->unique();
            $table->unsignedInteger('sequence_number');
            $table->string('status', 20)->default('RESERVED');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['effective_fiscal_year_id', 'fund_source_id', 'document_type', 'period_key', 'sequence_number'],
                'spj_v2_reservations_sequence_unique',
            );
            $table->unique(
                ['spj_package_id', 'document_type', 'scope_key'],
                'spj_v2_reservations_package_identity_unique',
            );
            $table->index(['context_key', 'status'], 'spj_v2_reservations_context_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spj_v2_numbering_reservations');
    }
};
