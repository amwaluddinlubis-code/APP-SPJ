<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arkas_rkas_items', function (Blueprint $table): void {
            $table->timestamp('source_created_at')->nullable()->after('amount');
            $table->timestamp('source_last_updated_at')->nullable()->after('source_created_at');
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->date('rkas_date')->nullable()->after('transaction_date')->index();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropIndex(['rkas_date']);
            $table->dropColumn('rkas_date');
        });

        Schema::table('arkas_rkas_items', function (Blueprint $table): void {
            $table->dropColumn(['source_created_at', 'source_last_updated_at']);
        });
    }
};
