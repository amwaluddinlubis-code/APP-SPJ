<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('school')->table('spj_documents', function (Blueprint $table): void {
            $table->dropUnique(['document_number']);
            $table->index('document_number', 'spj_documents_number_index');
        });
    }

    public function down(): void
    {
        Schema::connection('school')->table('spj_documents', function (Blueprint $table): void {
            $table->dropIndex('spj_documents_number_index');
            $table->unique('document_number');
        });
    }
};
