<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('school')->create('transaction_source_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->unsignedBigInteger('source_event_id')->nullable();
            $table->string('resolution', 64);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at');
            $table->string('source_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['transaction_id', 'resolved_at'], 'source_reconciliation_tx_resolved_idx');
            $table->index('source_event_id', 'source_reconciliation_event_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('school')->dropIfExists('transaction_source_reconciliations');
    }
};
