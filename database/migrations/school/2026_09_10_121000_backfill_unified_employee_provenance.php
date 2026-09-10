<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('school')->hasTable('employees')
            || ! Schema::connection('school')->hasColumn('employees', 'last_seen_arkas_at')
            || ! Schema::connection('school')->hasColumn('employees', 'last_seen_dapodik_at')) {
            return;
        }

        $db = DB::connection('school');
        $now = now();

        $db->table('employees')
            ->whereIn('source_type', ['PEGAWAI', 'PTK', 'ARKAS'])
            ->whereNull('last_seen_arkas_at')
            ->update([
                'last_seen_arkas_at' => $now,
                'last_known_active_arkas' => DB::raw('is_active'),
            ]);

        $db->table('employees')
            ->where('source_type', 'DAPODIK')
            ->whereNull('last_seen_dapodik_at')
            ->update([
                'last_seen_dapodik_at' => $now,
                'last_known_active_dapodik' => DB::raw('is_active'),
            ]);
    }

    public function down(): void
    {
        // Provenance may have been refreshed by a real synchronization after
        // this migration. Rolling it back must not erase valid sync history.
    }
};
