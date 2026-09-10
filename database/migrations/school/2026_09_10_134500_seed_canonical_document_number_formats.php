<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('school')->hasTable('fiscal_years')
            || ! Schema::connection('school')->hasTable('document_number_formats')) {
            return;
        }

        $yearIds = DB::connection('school')->table('fiscal_years')->pluck('id');
        if ($yearIds->isEmpty()) {
            return;
        }

        $documentTypes = [
            'SPJ',
            'PESANAN',
            'BAP',
            'BAST',
            'SPK',
            'RAB',
            'SURAT_TUGAS_PERJALANAN_DINAS',
        ];
        $now = now();
        $rows = [];

        foreach ($yearIds as $yearId) {
            foreach ($documentTypes as $documentType) {
                $rows[] = [
                    'fiscal_year_id' => (int) $yearId,
                    'document_type' => $documentType,
                    'format_pattern' => '{SEQ}/'.$documentType.'/{SCHOOL}/{TW}/{YEAR}',
                    'reset_period' => 'YEAR',
                    'padding' => 4,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Unique(fiscal_year_id, document_type) + insertOrIgnore means a school
        // format that was already customized always wins over the canonical default.
        DB::connection('school')->table('document_number_formats')->insertOrIgnore($rows);
    }

    public function down(): void
    {
        // Do not delete formats on rollback: an operator may have customized a
        // seeded row after this migration ran, and numbering history may already
        // reference the resulting convention.
    }
};
