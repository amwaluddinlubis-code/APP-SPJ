<?php

namespace App\Livewire;

use App\Services\DocumentTemplateLibraryService;
use App\Services\SpjDocumentTypeRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class DocumentTemplateList extends Component
{
    use WithPagination;

    public string $status = 'all';

    public string $category = '';

    public array $mappingCategories = [];

    public array $mappingScopes = [];

    public array $mappingActive = [];

    public function mount(?string $status = null, ?string $category = null): void
    {
        $this->status = in_array($status, ['all', 'active', 'inactive'], true) ? (string) $status : 'all';
        $this->category = in_array($category, SpjDocumentTypeRegistry::categories(), true) ? (string) $category : '';
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingCategory(): void
    {
        $this->resetPage();
    }

    public function saveMapping(string $templateId): void
    {
        $categories = SpjDocumentTypeRegistry::categories();
        $validated = validator([
            'is_active' => (bool) ($this->mappingActive[$templateId] ?? false),
            'applicable_categories' => $this->mappingCategories[$templateId] ?? [],
            'siplah_scope' => $this->mappingScopes[$templateId] ?? 'all',
        ], [
            'is_active' => ['boolean'],
            'applicable_categories' => ['nullable', 'array'],
            'applicable_categories.*' => ['string', 'in:'.implode(',', $categories)],
            'siplah_scope' => ['nullable', 'string', 'in:all,siplah,non_siplah'],
        ])->validate();

        $saved = app(DocumentTemplateLibraryService::class)->updateMapping(
            $templateId,
            (bool) $validated['is_active'],
            $validated['applicable_categories'] ?? [],
            match ($validated['siplah_scope'] ?? 'all') {
                'siplah' => true,
                'non_siplah' => false,
                default => null,
            },
        );

        if (! $saved) {
            $this->dispatch('app-notify', type: 'error', message: 'Template tidak ditemukan.');

            return;
        }

        $this->dispatch('app-notify', type: 'success', message: 'Pemetaan template berhasil diperbarui.');
    }

    public function render(): View
    {
        $catalog = app(DocumentTemplateLibraryService::class)->catalog([
            'status' => $this->status,
            'category' => $this->category !== '' ? $this->category : null,
        ]);

        foreach ($catalog['templates'] as $template) {
            $templateId = (string) $template->id;
            $this->mappingCategories[$templateId] ??= $template->applicable_categories ?? [];
            $this->mappingScopes[$templateId] ??= $template->is_siplah === null
                ? 'all'
                : ($template->is_siplah ? 'siplah' : 'non_siplah');
            $this->mappingActive[$templateId] ??= (bool) $template->is_active;
        }

        return view('livewire.document-template-list', [
            'templates' => $catalog['templates'],
            'validationResults' => $catalog['validationResults'],
            'categories' => SpjDocumentTypeRegistry::categories(),
            'categoryOptions' => collect(SpjDocumentTypeRegistry::categories())
                ->map(fn (string $category): array => [
                    'value' => $category,
                    'label' => ucwords(strtolower(str_replace('_', ' ', $category))),
                ])->values()->all(),
            'documentTypes' => SpjDocumentTypeRegistry::options(),
        ]);
    }
}
