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
        Schema::table('arkas_import_profiles', function (Blueprint $table): void {
            $table->string('target_domain', 40)->default('raw')->after('source_table');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('arkas_import_profiles', function (Blueprint $table): void {
            $table->dropColumn('target_domain');
        });
    }
};
