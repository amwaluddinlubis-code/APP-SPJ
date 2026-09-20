<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('central_reference_rows', function (Blueprint $table): void {
            $table->id();
            $table->string('source_table', 80);
            $table->string('natural_key', 512);
            $table->string('version_key', 512);
            $table->string('arkas_release', 80);
            $table->text('semantic_payload');
            $table->string('source_dump', 120)->nullable();
            $table->string('source_row_key', 512)->nullable();
            $table->timestamps();
            $table->unique(['source_table', 'natural_key', 'version_key'], 'central_reference_identity_unique');
            $table->index(['source_table', 'arkas_release']);
        });

        Schema::create('central_reference_quarantines', function (Blueprint $table): void {
            $table->id();
            $table->string('source_table', 80);
            $table->string('status', 50);
            $table->string('context_key', 1024);
            $table->string('reason', 500);
            $table->text('row_payload');
            $table->string('payload_hash', 64);
            $table->timestamps();
            $table->unique(['source_table', 'status', 'context_key', 'payload_hash'], 'central_reference_quarantine_unique');
        });

        Schema::create('central_code_variants', function (Blueprint $table): void {
            $table->id();
            $table->string('source_code', 180);
            $table->string('variant_key', 64);
            $table->string('arkas_release', 80);
            $table->string('parent_code', 180)->nullable();
            $table->text('description')->nullable();
            $table->string('level_code', 80)->nullable();
            $table->string('variant_type', 80)->nullable();
            $table->timestamps();
            $table->unique(['source_code', 'variant_key', 'arkas_release'], 'central_code_variant_unique');
        });

        Schema::create('central_code_applicabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('variant_id')->constrained('central_code_variants')->cascadeOnDelete();
            $table->string('tenant_key', 120);
            $table->string('source_code', 180);
            $table->string('arkas_release', 80);
            $table->string('fiscal_year', 30);
            $table->string('fund_source', 120);
            $table->string('education_level', 120);
            $table->text('applicability_payload')->nullable();
            $table->timestamps();
            $table->unique(['tenant_key', 'source_code', 'arkas_release', 'fiscal_year', 'fund_source', 'education_level'], 'central_code_applicability_unique');
        });

        Schema::create('central_reference_extensions', function (Blueprint $table): void {
            $table->id();
            $table->string('source_table', 80);
            $table->string('tenant_key', 120);
            $table->string('natural_key', 512);
            $table->string('version_key', 512);
            $table->text('payload');
            $table->timestamps();
            $table->unique(['source_table', 'tenant_key', 'natural_key', 'version_key'], 'central_reference_extension_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('central_reference_extensions');
        Schema::dropIfExists('central_code_applicabilities');
        Schema::dropIfExists('central_code_variants');
        Schema::dropIfExists('central_reference_quarantines');
        Schema::dropIfExists('central_reference_rows');
    }
};
