<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('spj_service_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('npwp', 40)->nullable();
            $table->string('service_type', 180);
            $table->text('service_description')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('unit', 40)->default('unit');
            $table->decimal('rental_days', 12, 2)->default(1);
            $table->decimal('daily_rate', 18, 2)->default(0);
            $table->decimal('amount', 18, 2)->default(0);
            $table->date('usage_started_at')->nullable();
            $table->date('usage_completed_at')->nullable();
            $table->string('receipt_number', 100)->nullable();
            $table->string('payment_reference', 160)->nullable();
            $table->string('agreement_number', 100)->nullable();
            $table->date('agreement_date')->nullable();
            $table->boolean('is_receipt_recipient')->default(false);
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['transaction_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spj_service_recipients');
    }
};
