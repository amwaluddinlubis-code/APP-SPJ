<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('school');
        $db = DB::connection('school');

        if (! $schema->hasColumn('arkas_bku_rows', 'source_rapbs_period_id')) {
            $schema->table('arkas_bku_rows', function (Blueprint $table): void {
                $table->string('source_rapbs_period_id', 100)->nullable()->after('source_kas_id');
                $table->index(['fiscal_year_id', 'fund_source_id', 'source_rapbs_period_id'], 'arkas_bku_period_lookup_index');
            });
        }

        // Populate rows imported before this normalized column existed.
        if ($schema->hasColumn('arkas_bku_rows', 'source_rapbs_period_id')) {
            $db->table('arkas_bku_rows')
                ->whereNull('source_rapbs_period_id')
                ->update([
                    'source_rapbs_period_id' => DB::raw("NULLIF(COALESCE(json_extract(payload, '$.ID_RAPBS_PERIODE'), json_extract(payload, '$.id_rapbs_periode')), '')"),
                ]);
        }

        if (! $schema->hasColumn('arkas_rkas_periods', 'source_rapbs_period_id')) {
            $schema->table('arkas_rkas_periods', function (Blueprint $table): void {
                $table->string('source_rapbs_period_id', 100)->nullable()->after('source_period_id');
                $table->index(['fiscal_year_id', 'fund_source_id', 'source_rapbs_period_id'], 'arkas_rkas_period_lookup_index');
            });
        }
        if ($schema->hasColumn('arkas_rkas_periods', 'source_rapbs_period_id')) {
            $db->table('arkas_rkas_periods')->whereNull('source_rapbs_period_id')->update([
                'source_rapbs_period_id' => DB::raw("NULLIF(COALESCE(json_extract(payload, '$.id_rapbs_periode'), json_extract(payload, '$.ID_RAPBS_PERIODE')), '')"),
            ]);
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('school');
        if ($schema->hasColumn('arkas_bku_rows', 'source_rapbs_period_id')) {
            $schema->table('arkas_bku_rows', function (Blueprint $table): void {
                $table->dropIndex('arkas_bku_period_lookup_index');
                $table->dropColumn('source_rapbs_period_id');
            });
        }
        if ($schema->hasColumn('arkas_rkas_periods', 'source_rapbs_period_id')) {
            $schema->table('arkas_rkas_periods', function (Blueprint $table): void {
                $table->dropIndex('arkas_rkas_period_lookup_index');
                $table->dropColumn('source_rapbs_period_id');
            });
        }
    }
};
