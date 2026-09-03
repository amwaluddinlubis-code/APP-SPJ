<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('transactions', 'receipt_recipient_name')) {
            Schema::table('transactions', function (Blueprint $table): void {
                $table->string('receipt_recipient_name', 255)->nullable()->after('spj_recipient_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('transactions', 'receipt_recipient_name')) {
            Schema::table('transactions', function (Blueprint $table): void {
                $table->dropColumn('receipt_recipient_name');
            });
        }
    }
};
