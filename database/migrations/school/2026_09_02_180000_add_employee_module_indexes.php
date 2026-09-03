<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('school')->table('employees', function (Blueprint $table): void {
            $table->index(['source_type', 'is_active', 'name'], 'employees_source_active_name_idx');
            $table->index('nip', 'employees_nip_idx');
            $table->index('nik', 'employees_nik_idx');
            $table->index('nuptk', 'employees_nuptk_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('school')->table('employees', function (Blueprint $table): void {
            $table->dropIndex('employees_source_active_name_idx');
            $table->dropIndex('employees_nip_idx');
            $table->dropIndex('employees_nik_idx');
            $table->dropIndex('employees_nuptk_idx');
        });
    }
};
