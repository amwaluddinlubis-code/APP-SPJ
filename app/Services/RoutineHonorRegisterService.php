<?php

namespace App\Services;

use App\Models\SpjHonor;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RoutineHonorRegisterService
{
    /**
     * Menggabungkan honor rutin lintas Paket SPJ tanpa mengubah lifecycle/numbering Paket sumber.
     * Satu baris register mewakili penerima + jenis/jabatan + nilai honor satuan yang sama.
     *
     * @param  Collection<int, SpjHonor>  $honors
     * @return array{rows:Collection<int,array<string,mixed>>,summary:array{gross:float,tax:float,net:float}}
     */
    public function aggregate(Collection $honors): array
    {
        $rows = $honors
            ->groupBy(fn (SpjHonor $honor): string => $this->groupKey($honor))
            ->map(function (Collection $group): array {
                /** @var SpjHonor $first */
                $first = $group->first();
                $transactions = $group
                    ->map(fn (SpjHonor $honor) => $honor->item->transaction)
                    ->filter();
                $references = $transactions
                    ->map(function ($transaction): string {
                        $parts = array_filter([
                            trim((string) $transaction->no_bukti),
                            trim((string) $transaction->spjPackage?->document_number),
                        ]);

                        return implode(' / ', $parts);
                    })
                    ->filter()
                    ->unique()
                    ->values();
                $periods = $transactions
                    ->map(fn ($transaction) => $transaction->transaction_date?->translatedFormat('F Y'))
                    ->filter()
                    ->unique()
                    ->values();

                return [
                    'name' => (string) $first->name,
                    'position' => (string) $first->position,
                    'period' => $periods->implode(', '),
                    'occurrences' => $group->count(),
                    'honor_units' => (float) $group->sum(fn (SpjHonor $honor) => (float) $honor->honor_months),
                    'rate_per_unit' => (float) $first->rate_per_unit,
                    'gross' => (float) $group->sum(fn (SpjHonor $honor) => (float) $honor->gross_amount),
                    'tax' => (float) $group->sum(fn (SpjHonor $honor) => (float) $honor->tax_amount),
                    'net' => (float) $group->sum(fn (SpjHonor $honor) => (float) $honor->net_amount),
                    'package_references' => $references->implode('; '),
                    'is_routine' => $group->count() > 1,
                ];
            })
            ->sortBy(fn (array $row): string => Str::lower(trim($row['position'].' '.$row['name'])))
            ->values();

        return [
            'rows' => $rows,
            'summary' => [
                'gross' => (float) $rows->sum('gross'),
                'tax' => (float) $rows->sum('tax'),
                'net' => (float) $rows->sum('net'),
            ],
        ];
    }

    private function groupKey(SpjHonor $honor): string
    {
        return implode('|', [
            $this->normalize((string) $honor->name),
            $this->normalize((string) $honor->position),
            number_format((float) $honor->rate_per_unit, 2, '.', ''),
            number_format((float) $honor->gross_amount, 2, '.', ''),
        ]);
    }

    private function normalize(string $value): string
    {
        return Str::lower(preg_replace('/\s+/', ' ', trim($value)) ?? '');
    }
}
