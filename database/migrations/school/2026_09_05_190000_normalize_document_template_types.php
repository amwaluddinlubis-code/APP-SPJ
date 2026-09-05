<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normalize legacy aliases that have an unambiguous canonical document type.
     *
     * Collision policy: when a canonical row already exists for the same
     * fiscal year + format, keep that canonical row and leave the legacy row
     * in place but deactivate it. This avoids deleting a user-supplied file or
     * violating the document_templates unique key.
     */
    public function up(): void
    {
        $aliases = [
            'KUITANSI' => 'KUITANSI_A2',
            'CHECKLIST' => 'SPJ_CHECKLIST',
            'INVOICE_PESANAN' => 'INVOICE',
            'SPK' => 'SPK_PEMELIHARAAN',
        ];

        $templates = DB::connection('school')->table('document_templates');

        foreach ($aliases as $legacy => $canonical) {
            $rows = (clone $templates)
                ->where('document_type', $legacy)
                ->orderBy('id')
                ->get();

            foreach ($rows as $row) {
                $collision = (clone $templates)
                    ->where('fiscal_year_id', $row->fiscal_year_id)
                    ->where('document_type', $canonical)
                    ->where('format', $row->format)
                    ->exists();

                if ($collision) {
                    (clone $templates)->where('id', $row->id)->update([
                        'is_active' => false,
                        'updated_at' => now(),
                    ]);

                    continue;
                }

                (clone $templates)->where('id', $row->id)->update([
                    'document_type' => $canonical,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Data normalization is intentionally not reversed. We cannot reliably
     * distinguish a row that originated from a legacy alias from a row that
     * was created directly with the canonical code after this migration.
     */
    public function down(): void
    {
        // No-op by design.
    }
};
