<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertNoDuplicateIdentity('arkas_rkas_items', 'source_rapbs_id');
        $this->assertNoDuplicateIdentity('arkas_bku_rows', 'source_kas_id');
        $this->assertNoDuplicateIdentity('transactions', 'no_bukti');
        $this->assertNoDuplicateIdentity('transactions', 'source_key');

        Schema::connection('school')->table('arkas_rkas_items', function (Blueprint $table): void {
            $table->dropUnique(['fiscal_year_id', 'source_rapbs_id']);
            $table->unique(
                ['fiscal_year_id', 'fund_source_id', 'source_rapbs_id'],
                'arkas_rkas_items_context_identity_unique',
            );
        });

        Schema::connection('school')->table('arkas_bku_rows', function (Blueprint $table): void {
            $table->dropUnique(['fiscal_year_id', 'source_kas_id']);
            $table->unique(
                ['fiscal_year_id', 'fund_source_id', 'source_kas_id'],
                'arkas_bku_rows_context_identity_unique',
            );
        });

        Schema::connection('school')->table('transactions', function (Blueprint $table): void {
            $table->dropUnique(['fiscal_year_id', 'no_bukti']);
            $table->dropUnique(['fiscal_year_id', 'source_key']);
            $table->unique(
                ['fiscal_year_id', 'fund_source_id', 'no_bukti'],
                'transactions_context_no_bukti_unique',
            );
            $table->unique(
                ['fiscal_year_id', 'fund_source_id', 'source_key'],
                'transactions_context_source_key_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::connection('school')->table('transactions', function (Blueprint $table): void {
            $table->dropUnique('transactions_context_source_key_unique');
            $table->dropUnique('transactions_context_no_bukti_unique');
            $table->unique(['fiscal_year_id', 'source_key']);
            $table->unique(['fiscal_year_id', 'no_bukti']);
        });

        Schema::connection('school')->table('arkas_bku_rows', function (Blueprint $table): void {
            $table->dropUnique('arkas_bku_rows_context_identity_unique');
            $table->unique(['fiscal_year_id', 'source_kas_id']);
        });

        Schema::connection('school')->table('arkas_rkas_items', function (Blueprint $table): void {
            $table->dropUnique('arkas_rkas_items_context_identity_unique');
            $table->unique(['fiscal_year_id', 'source_rapbs_id']);
        });
    }

    private function assertNoDuplicateIdentity(string $table, string $identityColumn): void
    {
        $duplicates = DB::connection('school')->table($table)
            ->select(['fiscal_year_id', 'fund_source_id', $identityColumn])
            ->whereNotNull('fund_source_id')
            ->whereNotNull($identityColumn)
            ->groupBy(['fiscal_year_id', 'fund_source_id', $identityColumn])
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $conflicts = $duplicates
                ->map(fn (object $duplicate): string => sprintf(
                    '%s/%s/%s',
                    $duplicate->fiscal_year_id,
                    $duplicate->fund_source_id,
                    $duplicate->{$identityColumn},
                ))
                ->implode(', ');

            throw new RuntimeException(
                "Cannot scope {$table}.{$identityColumn} by fund_source_id because duplicate identities exist: {$conflicts}",
            );
        }
    }
};
