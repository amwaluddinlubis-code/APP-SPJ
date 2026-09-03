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
        Schema::table('spj_travels', function (Blueprint $table) {
            $table->string('assignment_letter_number', 255)->nullable()->unique()->after('purpose');
            $table->date('assignment_letter_date')->nullable()->after('assignment_letter_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spj_travels', function (Blueprint $table) {
            $table->dropUnique(['assignment_letter_number']);
            $table->dropColumn(['assignment_letter_number', 'assignment_letter_date']);
        });
    }
};
