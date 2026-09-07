<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'school';

    public function up(): void
    {
        Schema::connection('school')->table('spj_service_recipients', function (Blueprint $table): void {
            $table->decimal('tax_amount', 15, 2)->default(0)->after('amount');
            $table->decimal('net_amount', 15, 2)->default(0)->after('tax_amount');
        });
    }

    public function down(): void
    {
        Schema::connection('school')->table('spj_service_recipients', function (Blueprint $table): void {
            $table->dropColumn(['tax_amount', 'net_amount']);
        });
    }
};
