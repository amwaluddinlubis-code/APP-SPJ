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
        Schema::table('arkas_import_rows', function (Blueprint $table): void {
            $table->string('parent_source_key', 160)->nullable()->after('source_key');
            $table->string('relation_type', 80)->nullable()->after('parent_source_key');
            $table->index(['profile_id', 'fiscal_year_id', 'parent_source_key'], 'arkas_import_rows_parent_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('arkas_import_rows', function (Blueprint $table): void {
            $table->dropIndex('arkas_import_rows_parent_index');
            $table->dropColumn(['parent_source_key', 'relation_type']);
        });
    }
};
