<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SpjWorkflowFilterService
{
    /** @return array<string, string> */
    public function options(): array
    {
        return [
            'attention' => 'Perlu Perhatian',
            'unprepared' => 'Belum Dikerjakan',
            'draft' => 'Perlu Dilengkapi',
            'ready' => 'Siap Dinomori',
            'numbered' => 'Sudah Bernomor',
        ];
    }

    public function stateForLabel(string $label): ?string
    {
        return collect($this->options())->search($label, true) ?: null;
    }

    public function labels(): Collection
    {
        return collect($this->options())->values();
    }

    public function apply(Builder $query, ?string $state): Builder
    {
        if (blank($state) || $state === 'all') {
            return $query;
        }

        if ($state === 'attention') {
            return $query->where(function (Builder $query): void {
                $query->where('requires_reconciliation', true)
                    ->orWhere('source_status', 'SOURCE_MISSING');
            });
        }

        $this->excludeSourceAttention($query);

        return match ($state) {
            'unprepared' => $query->doesntHave('spjPackage'),
            'draft' => $query->whereHas('spjPackage', fn (Builder $package) => $package->where('status', 'DRAFT')),
            'ready' => $query->whereHas('spjPackage', fn (Builder $package) => $package->where('status', 'READY')),
            'numbered' => $query->whereHas('spjPackage', fn (Builder $package) => $package->whereIn('status', ['NUMBERED', 'FINAL'])),
            default => $query,
        };
    }

    private function excludeSourceAttention(Builder $query): void
    {
        $query
            ->where(function (Builder $query): void {
                $query->whereNull('requires_reconciliation')
                    ->orWhere('requires_reconciliation', false);
            })
            ->where(function (Builder $query): void {
                $query->whereNull('source_status')
                    ->orWhere('source_status', '!=', 'SOURCE_MISSING');
            });
    }
}
