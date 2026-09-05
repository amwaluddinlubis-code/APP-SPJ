<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('school')->table('transactions', function (Blueprint $table): void {
            $table->dropColumn(['signatory_name', 'signatory_role']);
        });
    }

    public function down(): void
    {
        Schema::connection('school')->table('transactions', function (Blueprint $table): void {
            $table->string('signatory_name', 180)->nullable()->after('vendor_npwp');
            $table->string('signatory_role', 80)->nullable()->after('signatory_name');
        });
    }
};
