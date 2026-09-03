<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('background_operations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('fiscal_year_id')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 50)->index();
            $table->string('status', 20)->default('QUEUED')->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->text('message')->nullable();
            $table->json('result')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'fiscal_year_id', 'created_at'], 'background_operations_context_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('background_operations');
    }
};
