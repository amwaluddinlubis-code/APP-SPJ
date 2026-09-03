<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_period_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('quarter');
            $table->string('status', 30)->default('OPEN');
            $table->timestamp('numbered_at')->nullable();
            $table->foreignId('numbered_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->foreignId('reopened_by')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->timestamps();
            $table->unique(['fiscal_year_id', 'quarter']);
        });

        Schema::create('quarter_numbering_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_period_closure_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('quarter');
            $table->string('status', 30)->default('RUNNING');
            $table->json('document_types')->nullable();
            $table->unsignedInteger('numbered_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->text('error_message')->nullable();
            $table->foreignId('started_by')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['fiscal_year_id', 'quarter', 'status']);
        });

        Schema::create('transaction_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('scope_key', 80);
            $table->unsignedInteger('payment_sequence');
            $table->date('payment_date');
            $table->decimal('gross_amount', 18, 2);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('net_amount', 18, 2);
            $table->string('payment_method', 40)->nullable();
            $table->string('payment_reference', 160)->nullable();
            $table->string('status', 30)->default('DRAFT');
            $table->boolean('is_late_entry')->default(false);
            $table->timestamps();
            $table->unique(['transaction_id', 'scope_key']);
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('scope_key', 80);
            $table->unsignedInteger('receipt_sequence');
            $table->date('receipt_date');
            $table->string('status', 30)->default('DRAFT');
            $table->text('notes')->nullable();
            $table->boolean('is_late_entry')->default(false);
            $table->timestamps();
            $table->unique(['transaction_id', 'scope_key']);
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_received', 18, 4);
            $table->decimal('amount_received', 18, 2)->default(0);
            $table->timestamps();
            $table->unique(['goods_receipt_id', 'transaction_item_id']);
        });

        Schema::table('spj_packages', function (Blueprint $table) {
            $table->boolean('is_late_entry')->default(false)->after('status');
        });

        Schema::table('spj_documents', function (Blueprint $table) {
            $table->foreignId('replaces_document_id')->nullable()->after('document_template_id')->constrained('spj_documents')->nullOnDelete();
            $table->boolean('is_late_entry')->default(false)->after('status');
            $table->json('template_snapshot')->nullable()->after('snapshot');
            $table->string('template_hash', 64)->nullable()->after('template_snapshot');
            $table->string('rendered_hash', 64)->nullable()->after('template_hash');
        });
    }

    public function down(): void
    {
        Schema::table('spj_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replaces_document_id');
            $table->dropColumn(['is_late_entry', 'template_snapshot', 'template_hash', 'rendered_hash']);
        });
        Schema::table('spj_packages', fn (Blueprint $table) => $table->dropColumn('is_late_entry'));
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('transaction_payments');
        Schema::dropIfExists('quarter_numbering_runs');
        Schema::dropIfExists('fiscal_period_closures');
    }
};
