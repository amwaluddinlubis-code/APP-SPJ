<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            if (! Schema::hasColumn('transactions', 'pph4_rate')) {
                $table->decimal('pph4_rate', 8, 4)->nullable();
            }
            if (! Schema::hasColumn('transactions', 'sspd_rate')) {
                $table->decimal('sspd_rate', 8, 4)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $columns = collect(['pph4_rate', 'sspd_rate'])
                ->filter(fn (string $column): bool => Schema::hasColumn('transactions', $column))
                ->all();
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
