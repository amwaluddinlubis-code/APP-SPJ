<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('school');

        if (! $schema->hasTable('arkas_import_rows') || ! $schema->hasColumn('arkas_import_rows', 'source_updated_at')) {
            return;
        }

        $schema->table('arkas_import_rows', function (Blueprint $table): void {
            $table->dropColumn('source_updated_at');
        });
    }

    public function down(): void
    {
        // Intentionally left empty: the removed column was unused and its
        // historical values cannot be restored safely after this cleanup.
    }
};
