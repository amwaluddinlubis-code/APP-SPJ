<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->string('event_name', 180)->nullable();
            $table->string('event_location', 180)->nullable();
            $table->date('event_date')->nullable();
            $table->unsignedInteger('participant_count')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropColumn(['event_name', 'event_location', 'event_date', 'participant_count']);
        });
    }
};
