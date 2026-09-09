<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arkas_import_runs', function (Blueprint $table): void {
            $table->unsignedInteger('records_new')->default(0)->after('records_written');
            $table->unsignedInteger('records_changed')->default(0)->after('records_new');
            $table->unsignedInteger('records_unchanged')->default(0)->after('records_changed');
            $table->unsignedInteger('records_removed')->default(0)->after('records_unchanged');
        });
    }

    public function down(): void
    {
        Schema::table('arkas_import_runs', function (Blueprint $table): void {
            $table->dropColumn([
                'records_new',
                'records_changed',
                'records_unchanged',
                'records_removed',
            ]);
        });
    }
};
