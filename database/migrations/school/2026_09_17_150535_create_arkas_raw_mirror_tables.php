<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arkas_raw_mirror_tables', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('source_id');
            $table->string('source_table', 120);
            $table->json('schema');
            $table->string('schema_hash', 64);
            $table->unsignedBigInteger('row_count')->default(0);
            $table->string('status', 30)->default('ACTIVE');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['source_id', 'source_table'], 'arkas_raw_mirror_source_table_unique');
            $table->index(['source_id', 'status']);
        });

        Schema::create('arkas_raw_mirror_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mirror_table_id')->constrained('arkas_raw_mirror_tables')->cascadeOnDelete();
            $table->string('source_key', 160);
            $table->unsignedInteger('ordinal')->default(0);
            $table->json('payload');
            $table->string('payload_hash', 64);
            $table->timestamps();
            $table->unique(['mirror_table_id', 'source_key', 'ordinal'], 'arkas_raw_mirror_rows_identity_unique');
            $table->index(['mirror_table_id', 'payload_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arkas_raw_mirror_rows');
        Schema::dropIfExists('arkas_raw_mirror_tables');
    }
};
