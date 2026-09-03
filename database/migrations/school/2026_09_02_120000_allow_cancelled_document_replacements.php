<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('school')->table('spj_documents', function (Blueprint $table): void {
            $table->dropUnique(['spj_package_id', 'document_type', 'scope_key']);
            $table->index(['spj_package_id', 'document_type', 'scope_key'], 'spj_documents_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::connection('school')->table('spj_documents', function (Blueprint $table): void {
            $table->dropIndex('spj_documents_lookup_index');
            $table->unique(['spj_package_id', 'document_type', 'scope_key']);
        });
    }
};
