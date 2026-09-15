<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Models\SpjPackage;
use Illuminate\Support\Collection;

final class SpjPackageTemplateSelector
{
    /**
     * Document types listed here are exclusive to one is_siplah state.
     * Unlisted document types are shared by SiPlah and Non-SiPlah packages.
     *
     * @var array<string,bool>
     */
    private const IS_SIPLAH_DOCUMENT_MAP = [
        'SPJ_SURAT_PESANAN' => false,
        'SPJ_BA_PEMERIKSAAN' => false,
        'SPJ_BAST_PEMBELIAN' => false,
    ];

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
        $documentType = strtoupper(trim((string) $template->document_type));

        if (! array_key_exists($documentType, self::IS_SIPLAH_DOCUMENT_MAP)) {
            return true;
        }

        return self::IS_SIPLAH_DOCUMENT_MAP[$documentType] === $isSiplah;
    }
}
