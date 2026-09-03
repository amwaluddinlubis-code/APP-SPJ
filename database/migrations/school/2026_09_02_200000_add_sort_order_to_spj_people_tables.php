<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['spj_participants', 'spj_workers', 'spj_honors', 'spj_travels'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'sort_order')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unsignedInteger('sort_order')->default(0)->index();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sort_order')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('sort_order');
            });
        }
    }
};
