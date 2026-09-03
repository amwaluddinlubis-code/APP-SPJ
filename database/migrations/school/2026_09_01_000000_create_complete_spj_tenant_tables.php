<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_hierarchies', function (Blueprint $table) {
            $table->id();
            $table->string('account_code')->unique();
            $table->string('account_name');
            $table->unsignedTinyInteger('level');
            $table->timestamps();
        });

        Schema::create('fund_sources', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('code', 80);
            $table->string('name', 180);
            $table->boolean('is_hidden')->default(false);
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->string('fund_source')->default('BOSP');
            $table->unsignedInteger('fund_source_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['year', 'fund_source']);
            $table->index(['year', 'fund_source_id']);
            $table->foreign('fund_source_id')->references('id')->on('fund_sources')->restrictOnDelete();
        });

        Schema::create('account_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->string('account_code', 40);
            $table->text('account_name')->nullable();
            $table->boolean('is_honor')->default(false);
            $table->boolean('is_ppn')->default(false);
            $table->boolean('is_pph21')->default(false);
            $table->boolean('is_pph22')->default(false);
            $table->boolean('is_pph23')->default(false);
            $table->boolean('is_pph4')->default(false);
            $table->boolean('is_sspd')->default(false);
            $table->boolean('is_buku')->default(false);
            $table->string('spj_category', 40)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique(['fiscal_year_id', 'account_code']);
        });

        Schema::create('activity_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->string('source_ref_code', 100)->nullable();
            $table->string('activity_code', 40);
            $table->text('activity_name')->nullable();
            $table->timestamps();
            $table->unique(['fiscal_year_id', 'activity_code']);
        });

        Schema::create('arkas_periods', function (Blueprint $table) {
            $table->id();
            $table->string('source_period_id', 30)->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('arkas_rkas_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('fund_source_id')->nullable();
            $table->string('source_rapbs_id');
            $table->string('activity_code', 30)->nullable();
            $table->text('activity_name')->nullable();
            $table->string('account_code', 40)->nullable();
            $table->text('description')->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->json('payload');
            $table->timestamps();
            $table->unique(['fiscal_year_id', 'source_rapbs_id']);
            $table->index(['fiscal_year_id', 'fund_source_id']);
            $table->foreign('fund_source_id')->references('id')->on('fund_sources')->restrictOnDelete();
        });

        Schema::create('arkas_bku_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('fund_source_id')->nullable();
            $table->string('source_kas_id');
            $table->string('parent_kas_id')->nullable();
            $table->string('category', 30)->nullable();
            $table->string('no_bukti', 40)->nullable();
            $table->date('transaction_date')->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->json('payload');
            $table->timestamps();
            $table->unique(['fiscal_year_id', 'source_kas_id']);
            $table->index(['fiscal_year_id', 'fund_source_id']);
            $table->foreign('fund_source_id')->references('id')->on('fund_sources')->restrictOnDelete();
        });

        Schema::create('business_partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('npwp', 40)->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_business_entity')->default(false);
            $table->boolean('is_arkas_synced')->default(true);
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique(['name', 'npwp']);
        });

        Schema::create('document_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->string('format_name', 40);
            $table->string('period_key', 40);
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['fiscal_year_id', 'format_name', 'period_key']);
        });

        Schema::create('document_number_formats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 40);
            $table->string('format_pattern', 255);
            $table->string('reset_period', 20)->default('YEAR');
            $table->unsignedTinyInteger('padding')->default(4);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['fiscal_year_id', 'document_type']);
        });

        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 40);
            $table->string('name');
            $table->string('format', 10);
            $table->string('file_path');
            $table->boolean('is_active')->default(true);
            $table->json('applicable_categories')->nullable();
            $table->timestamps();
            $table->unique(['fiscal_year_id', 'document_type', 'format']);
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 20);
            $table->string('source_key', 100);
            $table->string('name');
            $table->string('nip', 40)->nullable();
            $table->string('nik', 40)->nullable();
            $table->string('nuptk', 40)->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('employment_status', 60)->nullable();
            $table->string('staff_type', 100)->nullable();
            $table->string('position', 100)->nullable();
            $table->string('npwp', 40)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique(['source_type', 'source_key']);
        });

        Schema::create('operational_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entity_type', 40);
            $table->string('entity_id', 80)->nullable();
            $table->string('action', 80);
            $table->string('description', 500);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('school_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('principal_name')->nullable();
            $table->string('principal_nip', 40)->nullable();
            $table->string('treasurer_name')->nullable();
            $table->string('treasurer_nip', 40)->nullable();
            $table->string('principal_email')->nullable();
            $table->string('principal_phone', 40)->nullable();
            $table->string('treasurer_email')->nullable();
            $table->string('treasurer_phone', 40)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 30)->default('ARKAS');
            $table->string('status', 20);
            $table->unsignedInteger('records_read')->default(0);
            $table->unsignedInteger('records_written')->default(0);
            $table->text('message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('fund_source_id')->nullable();
            $table->string('id_kas_umum')->nullable()->index();
            $table->string('no_bukti', 40)->index();
            $table->date('transaction_date')->index();
            $table->text('description')->nullable();
            $table->text('payment_description')->nullable();
            $table->string('spj_category', 40)->nullable();
            $table->string('payment_method', 40)->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('activity_code', 30)->nullable();
            $table->text('activity_name')->nullable();
            $table->string('account_code', 40)->nullable();
            $table->text('account_name')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('spj_recipient_name', 255)->nullable();
            $table->string('receipt_recipient_name', 255)->nullable();
            $table->string('vendor_name', 180)->nullable();
            $table->string('vendor_owner', 180)->nullable();
            $table->string('vendor_npwp', 32)->nullable();
            $table->string('signatory_name', 180)->nullable();
            $table->string('signatory_role', 80)->nullable();

            $table->decimal('gross_amount', 18, 2)->default(0);
            $table->decimal('ppn', 18, 2)->default(0);
            $table->decimal('ppn_rate', 8, 4)->nullable();
            $table->decimal('pph21', 18, 2)->default(0);
            $table->decimal('pph21_rate', 8, 4)->nullable();
            $table->decimal('pph22', 18, 2)->default(0);
            $table->decimal('pph22_rate', 8, 4)->nullable();
            $table->decimal('pph23', 18, 2)->default(0);
            $table->decimal('pph23_rate', 8, 4)->nullable();
            $table->decimal('pph4', 18, 2)->default(0);
            $table->decimal('sspd', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('net_amount', 18, 2)->default(0);

            $table->boolean('is_siplah')->default(false);
            $table->string('status', 30)->default('DRAFT');
            $table->string('source_key', 64)->nullable();
            $table->string('source_hash', 64)->nullable();
            $table->string('source_status', 30)->default('ACTIVE');
            $table->foreignId('last_seen_sync_run_id')->nullable()->constrained('sync_runs')->nullOnDelete();
            $table->timestamp('source_missing_since')->nullable();
            $table->boolean('requires_reconciliation')->default(false);
            $table->timestamps();

            $table->unique(['fiscal_year_id', 'no_bukti']);
            $table->unique(['fiscal_year_id', 'source_key']);
            $table->index(['fiscal_year_id', 'fund_source_id']);
            $table->index(['fiscal_year_id', 'fund_source_id', 'source_status']);
            $table->foreign('fund_source_id')->references('id')->on('fund_sources')->restrictOnDelete();
        });

        Schema::create('transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('source_item_id')->nullable();
            $table->text('description');
            $table->text('item_description')->nullable();
            $table->decimal('quantity', 14, 2)->default(1);
            $table->string('unit', 30)->nullable();
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('source_status', 30)->default('ACTIVE');
            $table->foreignId('last_seen_sync_run_id')->nullable()->constrained('sync_runs')->nullOnDelete();
            $table->timestamp('source_missing_since')->nullable();
            $table->timestamps();
            $table->unique(['transaction_id', 'source_item_id']);
        });

        Schema::create('spj_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('document_number')->nullable()->unique();
            $table->string('quarter_code', 12)->nullable();
            $table->string('semester_code', 12)->nullable();
            $table->string('phase_code', 12)->nullable();
            $table->string('status', 30)->default('DRAFT');
            $table->timestamp('numbered_at')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->unsignedBigInteger('finalized_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('unlocked_at')->nullable();
            $table->unsignedBigInteger('unlocked_by')->nullable();
            $table->text('unlock_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('spj_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spj_package_id')->constrained('spj_packages')->cascadeOnDelete();
            $table->foreignId('document_template_id')->nullable()->constrained('document_templates')->nullOnDelete();
            $table->string('document_type', 40);
            $table->string('scope_key', 80)->default('MAIN');
            $table->string('document_number')->nullable()->unique();
            $table->unsignedInteger('sequence_number')->nullable();
            $table->date('document_date')->nullable();
            $table->date('event_date')->nullable();
            $table->string('status', 30)->default('DRAFT');
            $table->json('snapshot')->nullable();
            $table->timestamp('numbered_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->unsignedBigInteger('finalized_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->unique(['spj_package_id', 'document_type', 'scope_key']);
            $table->index(['document_type', 'status', 'document_date']);
        });

        Schema::create('spj_goods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('order_number', 80)->nullable();
            $table->date('order_date')->nullable();
            $table->string('bap_number', 80)->nullable();
            $table->date('bap_date')->nullable();
            $table->string('bast_number', 80)->nullable();
            $table->date('bast_date')->nullable();
            $table->timestamps();
        });

        Schema::create('spj_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_item_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('position', 180)->nullable();
            $table->decimal('portions', 10, 2)->default(1);
            $table->timestamps();
        });

        Schema::create('spj_travels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('traveler_name', 180)->nullable();
            $table->string('destination', 180)->nullable();
            $table->text('purpose')->nullable();
            $table->date('departure_date')->nullable();
            $table->date('return_date')->nullable();
            $table->string('transport_mode', 80)->nullable();
            $table->unsignedInteger('participant_count')->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('spj_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->string('default_location', 180)->nullable();
            $table->string('status', 30)->default('DRAFT');
            $table->timestamps();
        });

        Schema::create('spj_work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_id')->constrained('spj_maintenances')->cascadeOnDelete();
            $table->foreignId('transaction_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('expense_type', 20); // MATERIAL | UPAH
            $table->text('work_description');
            $table->string('work_location', 180)->nullable();
            $table->string('spk_number', 80)->nullable();
            $table->date('spk_date')->nullable();
            $table->string('rab_number', 80)->nullable();
            $table->date('rab_date')->nullable();
            $table->date('work_started_at')->nullable();
            $table->date('work_completed_at')->nullable();
            $table->timestamps();
            $table->index(['maintenance_id', 'expense_type']);
        });

        Schema::create('spj_workers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('spj_work_orders')->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('nik', 32)->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->string('job_description', 255)->nullable();
            $table->decimal('work_days', 10, 2)->default(0);
            $table->decimal('daily_rate', 18, 2)->default(0);
            $table->decimal('amount', 18, 2)->default(0);
            $table->boolean('is_receipt_recipient')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('spj_honors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_item_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('nip', 40)->nullable();
            $table->string('nik', 32)->nullable();
            $table->string('npwp', 40)->nullable();
            $table->string('position', 180)->nullable();
            $table->string('golongan', 20)->nullable();
            $table->decimal('honor_months', 10, 2)->default(1);
            $table->decimal('rate_per_unit', 18, 2)->default(0);
            $table->decimal('gross_amount', 18, 2)->default(0);
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('net_amount', 18, 2)->default(0);
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account', 80)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spj_honors');
        Schema::dropIfExists('spj_workers');
        Schema::dropIfExists('spj_work_orders');
        Schema::dropIfExists('spj_maintenances');
        Schema::dropIfExists('spj_travels');
        Schema::dropIfExists('spj_participants');
        Schema::dropIfExists('spj_goods');
        Schema::dropIfExists('spj_documents');
        Schema::dropIfExists('spj_packages');
        Schema::dropIfExists('transaction_items');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('sync_runs');
        Schema::dropIfExists('school_profiles');
        Schema::dropIfExists('operational_audit_logs');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('document_templates');
        Schema::dropIfExists('document_number_formats');
        Schema::dropIfExists('document_number_sequences');
        Schema::dropIfExists('business_partners');
        Schema::dropIfExists('arkas_bku_rows');
        Schema::dropIfExists('arkas_rkas_items');
        Schema::dropIfExists('arkas_periods');
        Schema::dropIfExists('activity_references');
        Schema::dropIfExists('account_references');
        Schema::dropIfExists('fiscal_years');
        Schema::dropIfExists('fund_sources');
        Schema::dropIfExists('account_hierarchies');
    }
};
