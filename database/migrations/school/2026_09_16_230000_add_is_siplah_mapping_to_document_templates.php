<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('school')->table('document_templates', function (Blueprint $table): void {
            $table->boolean('is_siplah')->nullable();
        });

        DB::connection('school')
            ->table('document_templates')
            ->whereIn('document_type', [
                'SPJ_SURAT_PESANAN',
                'SPJ_BA_PEMERIKSAAN',
                'SPJ_BAST_PEMBELIAN',
            ])
            ->update(['is_siplah' => false]);
    }

    public function down(): void
    {
        Schema::connection('school')->table('document_templates', function (Blueprint $table): void {
            $table->dropColumn('is_siplah');
        });
    }
};
