<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normalize legacy applicable_categories values used by document templates.
     *
     * Unknown values are preserved for manual review. The legacy value SEMUA
     * is normalized to an empty array because an empty mapping already means
     * the template applies to every SPJ category.
     */
    public function up(): void
    {
        $aliases = [
            'BELANJA_MODAL' => 'BARANG',
            'PERJALANAN_DINAS' => 'SPPD',
            'JASA_HONORARIUM' => 'HONOR_PEGAWAI',
            'UPAH' => 'PEMELIHARAAN',
            'LAINNYA' => 'JASA_LAINNYA',
            'JASA' => 'JASA_LAINNYA',
        ];

        $rows = DB::connection('school')->table('document_templates')
            ->select(['id', 'applicable_categories'])
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            if ($row->applicable_categories === null) {
                continue;
            }

            $categories = json_decode((string) $row->applicable_categories, true);
            if (! is_array($categories)) {
                continue;
            }

            $normalizedInput = collect($categories)
                ->map(fn ($category): string => strtoupper(trim((string) $category)))
                ->filter()
                ->values();

            if ($normalizedInput->contains('SEMUA')) {
                $normalized = [];
            } else {
                $normalized = $normalizedInput
                    ->map(fn (string $category): string => $aliases[$category] ?? $category)
                    ->unique()
                    ->values()
                    ->all();
            }

            DB::connection('school')->table('document_templates')
                ->where('id', $row->id)
                ->update([
                    'applicable_categories' => json_encode($normalized, JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // No-op by design. Legacy intent cannot be reconstructed reliably.
    }
};
