<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection('school');
        $targetColumns = ['spj_package_id', 'document_type', 'scope_key'];
        $indexes = $connection->select("PRAGMA index_list('spj_documents')");

        foreach ($indexes as $index) {
            if (! (bool) $index->unique) {
                continue;
            }

            $escapedName = str_replace("'", "''", (string) $index->name);
            $columns = collect($connection->select("PRAGMA index_info('{$escapedName}')"))
                ->sortBy('seqno')
                ->pluck('name')
                ->values()
                ->all();

            if ($columns === $targetColumns) {
                Schema::connection('school')->table('spj_documents', function (Blueprint $table) use ($index): void {
                    $table->dropUnique((string) $index->name);
                });

                break;
            }
        }

        $remainingIndexes = $connection->select("PRAGMA index_list('spj_documents')");
        if (! collect($remainingIndexes)->contains(fn ($index): bool => $index->name === 'spj_documents_lookup_index')) {
            Schema::connection('school')->table('spj_documents', function (Blueprint $table): void {
                $table->index(['spj_package_id', 'document_type', 'scope_key'], 'spj_documents_lookup_index');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('school')->table('spj_documents', function (Blueprint $table): void {
            $table->dropIndex('spj_documents_lookup_index');
            $table->unique(['spj_package_id', 'document_type', 'scope_key']);
        });
    }
};
