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
        if (! Schema::hasColumn('transactions', 'siplah_metadata')) {
            Schema::table('transactions', function (Blueprint $table): void {
                $table->json('siplah_metadata')->nullable()->after('siplah_order_number');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('transactions', 'siplah_metadata')) {
            Schema::table('transactions', function (Blueprint $table): void {
                $table->dropColumn('siplah_metadata');
            });
        }
    }
};
