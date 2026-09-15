<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('school')
            ->table('document_templates')
            ->whereIn('document_type', [
                'SURAT_PESANAN',
                'BAP',
                'BAST',
            ])
            ->whereNull('is_siplah')
            ->update(['is_siplah' => false]);
    }

    public function down(): void
    {
        // Data-only backfill. Do not erase later operator mapping during rollback.
    }
};
