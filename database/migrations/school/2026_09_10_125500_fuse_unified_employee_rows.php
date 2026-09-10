<?php

use App\Services\EmployeeIdentityService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('school')->hasTable('employees')
            || ! Schema::connection('school')->hasColumn('employees', 'last_seen_arkas_at')
            || ! Schema::connection('school')->hasColumn('employees', 'last_seen_dapodik_at')
            || ! Schema::connection('school')->hasColumn('employees', 'operator_locked')) {
            return;
        }

        // Existing school databases may already contain separate ARKAS/PTK and
        // Dapodik rows for the same person. Reuse the canonical identity service
        // so upgrade-time consolidation follows the same strong-identifier rules
        // as future synchronizations (NUPTK/NIP/NIK/Dapodik ID; never name-only).
        app(EmployeeIdentityService::class)->fuseDuplicates(false);
    }

    public function down(): void
    {
        // A merged employee is a canonical identity. Splitting it again would
        // manufacture historical rows and lose the meaning of later CRUD edits.
    }
};
