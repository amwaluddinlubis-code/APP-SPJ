<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('school')->statement(<<<'SQL'
            CREATE VIEW IF NOT EXISTS activity_hierarchy_references AS
            SELECT
                program.fiscal_year_id,
                program.activity_code AS program_code,
                program.activity_name AS program_name,
                sub_program.activity_code AS sub_program_code,
                sub_program.activity_name AS sub_program_name,
                activity.activity_code AS activity_code,
                activity.activity_name AS activity_name,
                program.source_ref_code AS program_source_ref_code,
                sub_program.source_ref_code AS sub_program_source_ref_code,
                activity.source_ref_code AS activity_source_ref_code
            FROM activity_references AS program
            INNER JOIN activity_references AS sub_program
                ON sub_program.fiscal_year_id = program.fiscal_year_id
                AND program.activity_code = substr(sub_program.activity_code, 1, 3)
            INNER JOIN activity_references AS activity
                ON activity.fiscal_year_id = sub_program.fiscal_year_id
                AND sub_program.activity_code = substr(activity.activity_code, 1, 6)
            WHERE length(program.activity_code) = 3
                AND length(sub_program.activity_code) = 6
                AND length(activity.activity_code) = 9
        SQL);
    }

    public function down(): void
    {
        DB::connection('school')->statement('DROP VIEW IF EXISTS activity_hierarchy_references');
    }
};
