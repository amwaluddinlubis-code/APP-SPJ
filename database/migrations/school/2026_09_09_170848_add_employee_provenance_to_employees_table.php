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
        Schema::connection('school')->table('employees', function (Blueprint $table): void {
            $table->dateTime('last_seen_arkas_at')->nullable()->after('last_synced_at');
            $table->dateTime('last_seen_dapodik_at')->nullable()->after('last_seen_arkas_at');
            $table->boolean('last_known_active_arkas')->nullable()->after('last_seen_dapodik_at');
            $table->boolean('last_known_active_dapodik')->nullable()->after('last_known_active_arkas');
            $table->boolean('operator_locked')->default(false)->after('last_known_active_dapodik');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('school')->table('employees', function (Blueprint $table): void {
            $table->dropColumn(['last_seen_arkas_at', 'last_seen_dapodik_at', 'last_known_active_arkas', 'last_known_active_dapodik', 'operator_locked']);
        });
    }
};
