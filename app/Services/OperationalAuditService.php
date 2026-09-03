<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class OperationalAuditService
{
    public function record(?int $yearId, string $entityType, string|int|null $entityId, string $action, string $description): void
    {
        if (! Schema::connection('school')->hasTable('operational_audit_logs')) {
            Log::warning('Operational audit table is unavailable.', ['action' => $action]);

            return;
        }

        DB::connection('school')->table('operational_audit_logs')->insert([
            'fiscal_year_id' => $yearId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'description' => $description,
            'user_id' => auth()->id(),
            'created_at' => now(),
        ]);
    }
}
