<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Single identity resolution for employees across ARKAS and Dapodik feeds.
 *
 * One person must occupy exactly one row per school database, keyed by
 * national identifiers (NUPTK > NIP > NIK > normalized name). The feed a row
 * was first seen in is kept as origin (source_type); per-feed sightings are
 * tracked via last_seen_* timestamps so partial operators (ARKAS-only or
 * Dapodik-only) never lose people synced solely by the other feed.
 */
class EmployeeIdentityService
{
    public const STALE_DAYS = 400;

    public function normalize(string $value): string
    {
        /** @var string $normalized */
        $normalized = Str::of($value)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->value();

        return $normalized;
    }

    public function findMatch(?string $nuptk, ?string $nip, ?string $nik, string $name): ?Employee
    {
        if (filled($nuptk)) {
            $found = Employee::query()->where('nuptk', trim($nuptk))->first();
            if ($found instanceof Employee) {
                return $found;
            }
        }

        if (filled($nip)) {
            $found = Employee::query()->where('nip', trim($nip))->first();
            if ($found instanceof Employee) {
                return $found;
            }
        }

        if (filled($nik)) {
            $found = Employee::query()->where('nik', trim($nik))->first();
            if ($found instanceof Employee) {
                return $found;
            }
        }

        $normalized = $this->normalize($name);
        if ($normalized === '') {
            return null;
        }

        return Employee::query()->where('normalized_name', $normalized)->first();
    }

    public function effectiveActive(?bool $arkas, ?bool $dapodik): bool
    {
        return ($arkas ?? true) && ($dapodik ?? true);
    }

    /**
     * Fill missing normalized_name values so cross-source matching can find rows.
     */
    public function backfillNormalizedNames(bool $dryRun = false): int
    {
        $affected = 0;

        Employee::query()
            ->where(function ($query): void {
                $query->whereNull('normalized_name')->orWhere('normalized_name', '');
            })
            ->chunkById(200, function (Collection $rows) use (&$affected, $dryRun): void {
                foreach ($rows as $row) {
                    $affected++;
                    if (! $dryRun) {
                        $row->forceFill(['normalized_name' => $this->normalize((string) $row->name)])->save();
                    }
                }
            });

        return $affected;
    }

    /**
     * @return array{groups:int, merged:int, backfilled:int, dry_run:bool, pairs:array<int, array{keep:int, drop:array<int, int>, names:array<int, string>}>}
     */
    public function fuseDuplicates(bool $dryRun = true): array
    {
        $backfilled = $this->backfillNormalizedNames($dryRun);
        $pairs = [];
        $merged = 0;
        $pendingMerges = [];

        foreach ($this->duplicateGroups() as $group) {
            $ordered = $group->sortBy('id')->values();
            $canonical = $ordered->first(fn (Employee $row) => $row->last_seen_dapodik_at !== null) ?? $ordered->first();
            $losers = $ordered->where('id', '!==', $canonical->id)->values();

            $pairs[] = [
                'keep' => $canonical->id,
                'drop' => $losers->pluck('id')->all(),
                'names' => $ordered->map(fn (Employee $row) => '['.$row->id.' '.$row->source_type.'] '.($row->name ?? '-'))->all(),
            ];

            if (! $dryRun) {
                $pendingMerges[] = [$canonical->id, $losers->pluck('id')->all()];
            }
        }

        if (! $dryRun && $pendingMerges !== []) {
            DB::connection('school')->transaction(function () use ($pendingMerges): void {
                foreach ($pendingMerges as [$keepId, $dropIds]) {
                    $canonical = Employee::query()->findOrFail($keepId);
                    $losers = Employee::query()->whereIn('id', $dropIds)->get();
                    $this->mergeInto($canonical, $losers);
                }
            });
            $merged = array_sum(array_map(fn (array $merge) => count($merge[1]), $pendingMerges));
        }

        return [
            'groups' => count($pairs),
            'merged' => $dryRun ? 0 : $merged,
            'backfilled' => $backfilled,
            'dry_run' => $dryRun,
            'pairs' => $pairs,
        ];
    }

    /**
     * Deactivate rows unseen by the just-finished sync whose other-feed sighting
     * is missing or stale. Operator-locked rows are never swept.
     *
     * @param  array<int, int>  $seenIds
     */
    public function sweepAfterSync(array $seenIds, string $source, Carbon $now): int
    {
        if ($seenIds === [] || ! in_array($source, ['arkas', 'dapodik'], true)) {
            return 0;
        }

        $other = $source === 'arkas' ? 'dapodik' : 'arkas';
        $staleBefore = $now->copy()->subDays(self::STALE_DAYS);

        return Employee::query()
            ->where('operator_locked', false)
            ->where(function ($query): void {
                $query->whereNotNull('last_seen_arkas_at')->orWhereNotNull('last_seen_dapodik_at');
            })
            ->whereNotIn('id', $seenIds)
            ->where(function ($query) use ($other, $staleBefore): void {
                $query->whereNull("last_seen_{$other}_at")->orWhere("last_seen_{$other}_at", '<', $staleBefore);
            })
            ->update(['is_active' => false]);
    }

    /**
     * Group rows sharing any identity key (union-find, so transitive links
     * like same dapodik_id with different spellings land in one group).
     *
     * @return Collection<int, Collection<int, Employee>>
     */
    private function duplicateGroups(): Collection
    {
        $rows = Employee::query()->get();
        $parent = [];
        foreach ($rows as $row) {
            $parent[$row->id] = $row->id;
        }

        $find = function (int $id) use (&$parent): int {
            while ($parent[$id] !== $id) {
                $parent[$id] = $parent[$parent[$id]];
                $id = $parent[$id];
            }

            return $id;
        };

        $owners = [];
        foreach ($rows as $row) {
            foreach ($this->identityKeys($row) as $key) {
                if (isset($owners[$key])) {
                    $rootA = $find($row->id);
                    $rootB = $find($owners[$key]);
                    if ($rootA !== $rootB) {
                        $parent[$rootB] = $rootA;
                    }
                } else {
                    $owners[$key] = $row->id;
                }
            }
        }

        $byRoot = [];
        foreach ($rows as $row) {
            $byRoot[$find($row->id)][] = $row;
        }

        return collect($byRoot)
            ->filter(fn (array $group) => count($group) > 1)
            ->values()
            ->map(fn (array $group) => collect($group));
    }

    /**
     * @return array<int, string>
     */
    private function identityKeys(Employee $row): array
    {
        $keys = [];

        foreach (['nuptk' => $row->nuptk, 'dapodik' => $row->dapodik_id, 'nip' => $row->nip, 'nik' => $row->nik] as $kind => $value) {
            if (filled($value)) {
                $keys[] = $kind.':'.trim((string) $value);
            }
        }

        $normalized = filled($row->normalized_name) ? $row->normalized_name : $this->normalize((string) $row->name);
        if ($normalized !== '') {
            $keys[] = 'name:'.$normalized;
        }

        return $keys;
    }

    /**
     * @param  Collection<int, Employee>  $losers
     */
    private function mergeInto(Employee $canonical, Collection $losers): void
    {
        $donors = collect([$canonical])->concat($losers);

        // Gather donor values while loser rows still exist in memory.
        $values = [];
        foreach (['name', 'nip', 'nik', 'nuptk', 'gender', 'employment_status', 'staff_type', 'position', 'npwp', 'bank_name', 'bank_account', 'birth_place', 'birth_date', 'religion', 'last_education', 'last_study_field', 'rank_group', 'is_primary_school', 'dapodik_id'] as $column) {
            if (! filled($canonical->getAttribute($column))) {
                $donor = $donors->first(fn (Employee $row) => filled($row->getAttribute($column)));
                if ($donor instanceof Employee) {
                    $values[$column] = $donor->getAttribute($column);
                }
            }
        }

        $provenance = [
            'normalized_name' => $this->normalize((string) ($values['name'] ?? $canonical->name)),
            'last_seen_arkas_at' => $donors->map(fn (Employee $row) => $row->last_seen_arkas_at)->filter()->max(),
            'last_seen_dapodik_at' => $donors->map(fn (Employee $row) => $row->last_seen_dapodik_at)->filter()->max(),
            'last_known_active_arkas' => $donors->first(fn (Employee $row) => $row->last_known_active_arkas !== null)?->last_known_active_arkas,
            'last_known_active_dapodik' => $donors->first(fn (Employee $row) => $row->last_known_active_dapodik !== null)?->last_known_active_dapodik,
            'last_synced_at' => $donors->map(fn (Employee $row) => $row->last_synced_at)->filter()->max(),
            'operator_locked' => $donors->contains(fn (Employee $row) => (bool) $row->operator_locked),
        ];

        // Delete losers first so their UNIQUE slots (dapodik_id) are free
        // before the canonical row adopts those values.
        Employee::query()->whereIn('id', $losers->pluck('id')->all())->delete();

        foreach ($values as $column => $value) {
            $canonical->setAttribute($column, $value);
        }
        $canonical->forceFill($provenance);

        $canonical->is_active = $this->effectiveActive(
            $canonical->last_known_active_arkas,
            $canonical->last_known_active_dapodik
        );
        $canonical->save();
    }
}
