<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('school')->hasTable('document_number_formats')) {
            return;
        }

        DB::connection('school')->table('document_number_formats')
            ->where('format_pattern', 'like', '%{ROMAN_MONTH}%')
            ->orderBy('id')
            ->get(['id', 'format_pattern'])
            ->each(function ($format): void {
                DB::connection('school')->table('document_number_formats')
                    ->where('id', $format->id)
                    ->update([
                        'format_pattern' => str_replace('{ROMAN_MONTH}', '{TW}', (string) $format->format_pattern),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        // Tidak dikembalikan otomatis karena {TW} adalah format canonical baru.
        // Rollback tidak boleh menebak apakah {TW} berasal dari konfigurasi operator
        // atau hasil migrasi {ROMAN_MONTH}.
    }
};
