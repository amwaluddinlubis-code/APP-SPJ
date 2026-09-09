<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arkas_import_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('source_table', 120);
            $table->string('label', 160);
            $table->string('source_key_column', 120)->nullable();
            $table->string('year_column', 120)->nullable();
            $table->string('fund_source_column', 120)->nullable();
            $table->string('sync_mode', 30)->default('upsert');
            $table->boolean('is_enabled')->default(true);
            $table->json('mapping')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique('source_table');
        });

        Schema::create('arkas_import_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('profile_id')->constrained('arkas_import_profiles')->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30);
            $table->unsignedInteger('records_read')->default(0);
            $table->unsignedInteger('records_written')->default(0);
            $table->text('message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['profile_id', 'fiscal_year_id']);
        });

        Schema::create('arkas_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('profile_id')->constrained('arkas_import_profiles')->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_key', 160);
            $table->json('payload');
            $table->string('payload_hash', 64);
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamps();
            $table->unique(['profile_id', 'fiscal_year_id', 'source_key'], 'arkas_import_rows_unique');
            $table->index(['profile_id', 'fiscal_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arkas_import_rows');
        Schema::dropIfExists('arkas_import_runs');
        Schema::dropIfExists('arkas_import_profiles');
    }
};
