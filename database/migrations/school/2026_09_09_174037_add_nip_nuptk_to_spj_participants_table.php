<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('school')->table('spj_participants', function (Blueprint $table): void {
            $table->string('nip', 40)->nullable()->after('position');
            $table->string('nuptk', 40)->nullable()->after('nip');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('school')->table('spj_participants', function (Blueprint $table): void {
            $table->dropColumn(['nip', 'nuptk']);
        });
    }
};
