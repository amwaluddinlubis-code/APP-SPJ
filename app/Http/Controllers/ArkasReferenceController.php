<?php

namespace App\Http\Controllers;

use App\Models\FiscalYear;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Read-only ARKAS references used by budgeting and report preparation. */
class ArkasReferenceController extends Controller
{
    public function __invoke(Request $request): View
    {
        $yearId = (int) session('active_fiscal_year_id');
        $fundSourceId = (int) session('active_fund_source_id');
        $year = FiscalYear::query()->find($yearId);
        $budgetIds = $this->approvedBudgetIds($year, $fundSourceId);
        $rapbs = $this->rawRows('rapbs')?->filter(fn (array $row): bool => isset($budgetIds[(string) ($row['ID_ANGGARAN'] ?? '')]) && (string) ($row['SOFT_DELETE'] ?? '0') !== '1') ?? collect();
        $referenceRows = $this->rawRows('ref_kode') ?? collect();
        $names = $referenceRows->mapWithKeys(function (array $row): array {
            $code = trim((string) ($row['ID_KODE'] ?? ''), '.');
            $name = trim((string) ($row['URAIAN_KODE'] ?? ''));

            return $code !== '' && $name !== '' ? [$code => $name] : [];
        });
        $refById = $referenceRows->keyBy(fn (array $row): string => (string) ($row['ID_REF_KODE'] ?? ''));
        $activities = $rapbs->map(function (array $row) use ($refById): ?array {
            $reference = $refById->get((string) ($row['ID_REF_KODE'] ?? ''), []);
            $code = trim((string) ($reference['ID_KODE'] ?? $row['KODE_KEGIATAN'] ?? ''), '.');

            return $code === '' ? null : ['code' => $code, 'name' => (string) ($reference['URAIAN_KODE'] ?? 'Kegiatan belum diisi')];
        })->filter()->unique('code')->sort(fn (array $left, array $right): int => strnatcasecmp($left['code'], $right['code']))->values();
        $programs = $this->hierarchyRows($activities, $names, 1);
        $subprograms = $this->hierarchyRows($activities, $names, 2);
        $accounts = $this->accountRows($rapbs);
        $datasets = compact('programs', 'subprograms', 'activities', 'accounts');
        $type = (string) $request->query('type', 'programs');
        if (! isset($datasets[$type])) {
            $type = 'programs';
        }
        $search = trim((string) $request->query('q', ''));
        $rows = $datasets[$type]->when($search !== '', fn (Collection $items): Collection => $items->filter(fn (array $row): bool => str_contains(mb_strtolower($row['code'].' '.$row['name']), mb_strtolower($search))))->values();
        $perPage = in_array((int) $request->query('perPage', 25), [25, 50, 100], true) ? (int) $request->query('perPage', 25) : 25;
        $page = max(1, (int) $request->query('page', 1));
        $paginator = new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page, ['path' => $request->url(), 'query' => $request->query()]);
        $fundName = (string) (DB::connection('school')->table('fund_sources')->where('id', $fundSourceId)->value('name') ?: $year?->fund_source ?: 'Sumber Dana');

        return view('arkas.references.index', [
            'rows' => $paginator,
            'counts' => array_map(fn (Collection $items): int => $items->count(), $datasets),
            'type' => $type,
            'search' => $search,
            'contextLabel' => $fundName.' - '.($year?->year ?? '—'),
        ]);
    }

    /** @return array<string, bool> */
    private function approvedBudgetIds(?FiscalYear $year, int $fundSourceId): array
    {
        if (! $year) {
            return [];
        }
        $rows = $this->rawRows('anggaran')?->filter(fn (array $row): bool => (string) ($row['TAHUN_ANGGARAN'] ?? '') === (string) $year->year
            && (int) ($row['ID_REF_SUMBER_DANA'] ?? 0) === $fundSourceId
            && (string) ($row['IS_APPROVE'] ?? '0') === '1'
            && (string) ($row['IS_AKTIF'] ?? '0') === '1'
            && (string) ($row['SOFT_DELETE'] ?? '0') !== '1') ?? collect();
        if ($rows->isEmpty()) {
            return [];
        }
        $revision = (int) $rows->max(fn (array $row): int => (int) ($row['IS_REVISI'] ?? 0));
        $rows = $rows->filter(fn (array $row): bool => (int) ($row['IS_REVISI'] ?? 0) === $revision);
        $lastUpdate = (string) $rows->max(fn (array $row): string => (string) ($row['LAST_UPDATE'] ?? ''));

        return $rows->filter(fn (array $row): bool => (string) ($row['LAST_UPDATE'] ?? '') === $lastUpdate)->mapWithKeys(fn (array $row): array => [(string) ($row['ID_ANGGARAN'] ?? '') => true])->all();
    }

    /** @param Collection<int, array{code:string,name:string}> $activities */
    private function hierarchyRows(Collection $activities, Collection $names, int $depth): Collection
    {
        return $activities->map(function (array $activity) use ($names, $depth): array {
            $parts = explode('.', $activity['code']);
            $code = implode('.', array_slice($parts, 0, $depth));

            return ['code' => $code, 'name' => $names->get($code, $depth === 1 ? 'Program' : 'Subprogram')];
        })->unique('code')->sort(fn (array $left, array $right): int => strnatcasecmp($left['code'], $right['code']))->values();
    }

    /** @param Collection<int, array<string, mixed>> $rapbs */
    private function accountRows(Collection $rapbs): Collection
    {
        $activeCodes = $rapbs->map(fn (array $row): string => trim((string) ($row['KODE_REKENING'] ?? ''), '.'))->filter()->unique()->flip();
        $raw = $this->rawRows('ref_rekening');
        if ($raw?->isNotEmpty()) {
            return $raw->map(function (array $row): array {
                $code = trim((string) ($row['KODE_REKENING'] ?? $row['KODE'] ?? $row['ID_REKENING'] ?? ''), '.');

                return [
                    'code' => $code,
                    'name' => trim((string) ($row['NAMA_REKENING'] ?? $row['URAIAN_REKENING'] ?? $row['URAIAN'] ?? $row['NAMA'] ?? $row['DESCRIPTION'] ?? 'Rekening')),
                ];
            })
                ->filter(fn (array $row): bool => $row['code'] !== '' && ($activeCodes->isEmpty() || $activeCodes->has($row['code'])))
                ->unique('code')->sort(fn (array $left, array $right): int => strnatcasecmp($left['code'], $right['code']))->values();
        }

        return $rapbs->map(fn (array $row): array => ['code' => trim((string) ($row['KODE_REKENING'] ?? ''), '.'), 'name' => 'Rekening ARKAS'])
            ->filter(fn (array $row): bool => $row['code'] !== '')
            ->unique('code')->sort(fn (array $left, array $right): int => strnatcasecmp($left['code'], $right['code']))->values();
    }

    /** @return Collection<int, array<string, mixed>>|null */
    private function rawRows(string $sourceTable): ?Collection
    {
        $db = DB::connection('school');
        if (! $db->getSchemaBuilder()->hasTable('arkas_raw_mirror_tables')) {
            return null;
        }
        $mirror = $db->table('arkas_raw_mirror_tables')->where('source_table', $sourceTable)->where('status', 'ACTIVE')->where('row_count', '>', 0)->first();
        if (! $mirror) {
            return null;
        }

        return $db->table('arkas_raw_mirror_rows')->where('mirror_table_id', $mirror->id)->get()->map(function (object $row): array {
            $payload = json_decode((string) $row->payload, true);

            return is_array($payload) ? array_change_key_case($payload, CASE_UPPER) : [];
        })->filter(fn (array $row): bool => $row !== [])->values();
    }
}
