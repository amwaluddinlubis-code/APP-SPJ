<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spj_fresh_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('fund_source_id');
            $table->unsignedBigInteger('source_id');
            $table->string('source_table', 120);
            $table->string('source_key', 160);
            $table->foreignId('raw_mirror_row_id')->nullable()->constrained('arkas_raw_mirror_rows')->nullOnDelete();
            $table->string('spj_category', 40)->nullable();
            $table->text('payment_description')->nullable();
            $table->string('payment_method', 40)->nullable();
            $table->string('payment_reference', 160)->nullable();
            $table->string('receipt_recipient_name', 200)->nullable();
            $table->string('source_status', 30)->default('ACTIVE');
            $table->boolean('requires_reconciliation')->default(false);
            $table->timestamp('source_missing_since')->nullable();
            $table->timestamps();
            $table->unique(['fiscal_year_id', 'fund_source_id', 'source_id', 'source_table', 'source_key'], 'spj_fresh_transactions_source_unique');
            $table->index(['fiscal_year_id', 'fund_source_id']);
            $table->index(['source_id', 'source_table']);
        });

        Schema::create('spj_fresh_transaction_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('spj_fresh_transaction_id')->constrained('spj_fresh_transactions')->cascadeOnDelete();
            $table->string('source_table', 120);
            $table->string('source_key', 160);
            $table->foreignId('raw_mirror_row_id')->nullable()->constrained('arkas_raw_mirror_rows')->nullOnDelete();
            $table->text('item_description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['spj_fresh_transaction_id', 'source_table', 'source_key'], 'spj_fresh_items_source_unique');
        });

        Schema::create('spj_fresh_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('spj_fresh_transaction_id')->unique()->constrained('spj_fresh_transactions')->cascadeOnDelete();
            $table->string('quarter_code', 20)->nullable();
            $table->string('semester_code', 20)->nullable();
            $table->string('phase_code', 20)->nullable();
            $table->string('status', 30)->default('DRAFT');
            $table->string('document_number', 120)->nullable();
            $table->timestamp('numbered_at')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->unsignedBigInteger('finalized_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamps();
            $table->index(['status', 'quarter_code']);
        });

        Schema::create('spj_fresh_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('spj_fresh_package_id')->constrained('spj_fresh_packages')->cascadeOnDelete();
            $table->string('document_type', 80);
            $table->string('scope_key', 160)->nullable();
            $table->string('document_number', 120)->nullable();
            $table->unsignedInteger('sequence_number')->nullable();
            $table->date('document_date')->nullable();
            $table->string('status', 30)->default('DRAFT');
            $table->json('snapshot')->nullable();
            $table->json('template_snapshot')->nullable();
            $table->string('template_hash', 64)->nullable();
            $table->string('rendered_hash', 64)->nullable();
            $table->timestamp('numbered_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->unsignedBigInteger('finalized_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->unique(['spj_fresh_package_id', 'document_type', 'scope_key'], 'spj_fresh_documents_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spj_fresh_documents');
        Schema::dropIfExists('spj_fresh_packages');
        Schema::dropIfExists('spj_fresh_transaction_items');
        Schema::dropIfExists('spj_fresh_transactions');
    }
};
