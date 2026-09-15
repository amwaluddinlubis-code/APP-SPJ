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
        $category = strtoupper((string) $package->transaction->spj_category);

        return DocumentTemplate::query()
            ->where([
                'fiscal_year_id' => $package->transaction->fiscal_year_id,
                'is_active' => true,
            ])
            ->orderBy('document_type')
            ->get()
            ->filter(fn (DocumentTemplate $template): bool => $this->isMappedToCategory($template, $category))
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
}
