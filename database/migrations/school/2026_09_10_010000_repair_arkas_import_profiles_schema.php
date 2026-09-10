<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('arkas_import_profiles', 'source_updated_column')) {
            Schema::table('arkas_import_profiles', function (Blueprint $table): void {
                $table->string('source_updated_column', 120)->nullable()->after('fund_source_column');
            });
        }

        if (! Schema::hasColumn('arkas_import_profiles', 'source_columns')) {
            Schema::table('arkas_import_profiles', function (Blueprint $table): void {
                $table->json('source_columns')->nullable()->after('mapping');
            });
        }
    }

    public function down(): void
    {
        // Intentionally left empty: this repair only restores columns that the
        // application already expects and must not remove existing data.
    }
};
