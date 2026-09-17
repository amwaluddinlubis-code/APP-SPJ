<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection('school');
        $duplicates = $connection->table('fiscal_years')
            ->select(['year', 'fund_source_id'])
            ->whereNotNull('fund_source_id')
            ->groupBy(['year', 'fund_source_id'])
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $conflicts = $duplicates
                ->map(fn (object $duplicate): string => sprintf('%d/%d', $duplicate->year, $duplicate->fund_source_id))
                ->implode(', ');

            throw new RuntimeException(
                'Cannot scope fiscal_years uniqueness by fund_source_id because duplicate canonical identities exist: '.$conflicts,
            );
        }

        Schema::connection('school')->table('fiscal_years', function (Blueprint $table): void {
            $table->dropUnique(['year', 'fund_source']);
            $table->unique(
                ['year', 'fund_source_id'],
                'fiscal_years_year_fund_source_id_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::connection('school')->table('fiscal_years', function (Blueprint $table): void {
            $table->dropUnique('fiscal_years_year_fund_source_id_unique');
            $table->unique(['year', 'fund_source']);
        });
    }
};
