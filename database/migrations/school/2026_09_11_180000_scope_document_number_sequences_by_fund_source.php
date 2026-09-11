<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_number_sequences', function (Blueprint $table): void {
            $table->unsignedInteger('fund_source_id')->nullable()->after('fiscal_year_id');
        });

        DB::connection('school')->table('document_number_sequences')
            ->orderBy('id')
            ->get()
            ->each(function ($sequence): void {
                $fundSourceId = DB::connection('school')->table('fiscal_years')
                    ->where('id', $sequence->fiscal_year_id)
                    ->value('fund_source_id');
                DB::connection('school')->table('document_number_sequences')
                    ->where('id', $sequence->id)
                    ->update(['fund_source_id' => $fundSourceId]);
            });

        Schema::table('document_number_sequences', function (Blueprint $table): void {
            $table->dropUnique(['fiscal_year_id', 'format_name', 'period_key']);
            $table->unique(
                ['fiscal_year_id', 'fund_source_id', 'format_name', 'period_key'],
                'document_number_sequences_context_unique',
            );
            $table->index(['fiscal_year_id', 'fund_source_id'], 'document_number_sequences_context_index');
        });
    }

    public function down(): void
    {
        Schema::table('document_number_sequences', function (Blueprint $table): void {
            $table->dropUnique('document_number_sequences_context_unique');
            $table->dropIndex('document_number_sequences_context_index');
            $table->unique(['fiscal_year_id', 'format_name', 'period_key']);
            $table->dropColumn('fund_source_id');
        });
    }
};
