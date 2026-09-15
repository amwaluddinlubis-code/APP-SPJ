<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Models\SpjPackage;
use Illuminate\Support\Collection;

final class SpjPackageTemplateSelector
{
    /** @var array<int,string> */
    private const SIPLAH_EXCLUDED_DOCUMENT_TYPES = [
        'SPJ_SURAT_PESANAN',
        'SPJ_BA_PEMERIKSAAN',
        'SPJ_BAST_PEMBELIAN',
    ];

    public function __construct(
        private readonly SpjProcurementPolicyService $procurementPolicy,
    ) {}

    /** @return Collection<int,DocumentTemplate> */
    public function forPackage(SpjPackage $package): Collection
    {
        $transaction = $package->transaction;
        $category = strtoupper((string) $transaction->spj_category);
        $isSiplah = $this->procurementPolicy->isSiplah($transaction);

        return DocumentTemplate::query()
            ->where([
                'fiscal_year_id' => $transaction->fiscal_year_id,
                'is_active' => true,
            ])
            ->orderBy('document_type')
            ->get()
            ->filter(fn (DocumentTemplate $template): bool => $this->isMappedToCategory($template, $category)
                && $this->isMappedToProcurementChannel($template, $isSiplah))
            ->values();
    }

    /** @return Collection<int,DocumentTemplate> */
    public function spreadsheetsForPackage(SpjPackage $package): Collection
    {
        return $this->forPackage($package)
            ->filter(fn (DocumentTemplate $template): bool => strtolower((string) $template->format) === 'xlsx')
            ->values();
    }

    private function isMappedToCategory(DocumentTemplate $template, string $category): bool
    {
        $categories = $template->applicable_categories ?? [];

        return $categories === []
            || in_array('SEMUA', $categories, true)
            || in_array($category, $categories, true);
    }

    private function isMappedToProcurementChannel(DocumentTemplate $template, bool $isSiplah): bool
    {
        return ! $isSiplah
            || ! in_array(
                strtoupper((string) $template->document_type),
                self::SIPLAH_EXCLUDED_DOCUMENT_TYPES,
                true,
            );
    }
}
