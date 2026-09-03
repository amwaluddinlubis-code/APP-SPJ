<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('schools')) {
            Schema::create('schools', function (Blueprint $table): void {
                $table->id();
                $table->string('npsn')->unique();
                $table->string('name');
                $table->text('address')->nullable();
                $table->string('district')->nullable();
                $table->string('regency')->nullable();
                $table->string('province')->nullable();
                $table->string('letterhead_path')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
                $table->string('role', 30)->default('OPERATOR');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table): void {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }

        if (! Schema::hasTable('school_databases')) {
            Schema::create('school_databases', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('school_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('database_path')->unique();
                $table->string('status', 30)->default('READY');
                $table->timestamp('last_migrated_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('arkas_sources')) {
            Schema::create('arkas_sources', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('school_id')->unique()->constrained()->cascadeOnDelete();
                $table->text('database_path');
                $table->text('bridge_path');
                $table->text('database_password');
                $table->text('last_identity')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('school_backups')) {
            Schema::create('school_backups', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('school_id')->constrained()->cascadeOnDelete();
                $table->string('file_path');
                $table->string('file_name');
                $table->unsignedBigInteger('file_size')->default(0);
                $table->string('reason', 30)->default('MANUAL');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table): void {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        }

        if (! Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table): void {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
        }

        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table): void {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (! Schema::hasTable('job_batches')) {
            Schema::create('job_batches', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('name');
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
            });
        }

        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table): void {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('school_backups');
        Schema::dropIfExists('arkas_sources');
        Schema::dropIfExists('school_databases');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('schools');
    }
};
