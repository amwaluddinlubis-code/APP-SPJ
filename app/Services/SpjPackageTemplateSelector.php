<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Models\SpjPackage;
use Illuminate\Support\Collection;

final class SpjPackageTemplateSelector
{
    /** @return Collection<int,DocumentTemplate> */
    public function forPackage(SpjPackage $package): Collection
    {
        $transaction = $package->transaction;
        $category = strtoupper((string) $transaction->spj_category);
        $isSiplah = (bool) $transaction->is_siplah;

        return DocumentTemplate::query()
            ->where([
                'fiscal_year_id' => $transaction->fiscal_year_id,
                'is_active' => true,
            ])
            ->orderBy('document_type')
            ->get()
            ->filter(fn (DocumentTemplate $template): bool => $this->isMappedToCategory($template, $category)
                && $this->isMappedToSiplahFlag($template, $isSiplah))
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

    private function isMappedToSiplahFlag(DocumentTemplate $template, bool $isSiplah): bool
    {
        return $template->is_siplah === null
            || (bool) $template->is_siplah === $isSiplah;
    }
}
