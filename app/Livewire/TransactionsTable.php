<?php

namespace App\Livewire;

use App\Models\FiscalYear;
use App\Models\SpjFreshTransaction;
use App\Services\SpjWorkflowFilterService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class TransactionsTable extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: null)]
    public ?int $month = null;

    #[Url(except: null)]
    public ?int $quarter = null;

    #[Url(except: null)]
    public ?int $semester = null;

    #[Url(except: 15)]
    public int|string $perPage = 15;

    public function mount(): void
    {
        $this->q = trim((string) request('q'));
        $this->status = trim((string) request('status'));
        $this->month = request()->integer('month') ?: null;
        $this->quarter = request()->integer('quarter') ?: null;
        $this->semester = request()->integer('semester') ?: null;
        $requestedPerPage = request('perPage');

        if ($requestedPerPage === 'all') {
            $this->perPage = 'all';
        } elseif (in_array((int) $requestedPerPage, [15, 25, 50, 100], true)) {
            $this->perPage = (int) $requestedPerPage;
        }
    }

    public function updating($property): void
    {
        if (in_array($property, ['q', 'status', 'month', 'quarter', 'semester', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['q', 'status', 'month', 'quarter', 'semester']);
        $this->resetPage();
    }

    public function getFilteredStatsProperty(): object
    {
        $gross = $this->rawSpendingQuery()
            ->selectRaw("COUNT(*) as count, COALESCE(SUM(CAST(COALESCE(json_extract(arkas_raw_mirror_rows.payload, '$.saldo'), 0) AS REAL)), 0) as gross")
            ->first();
        $tax = $this->filteredTaxTotal();

        $gross->tax = $tax;
        $gross->net = (float) $gross->gross - $tax;

        return $gross;
    }

    public function getStatusesProperty(): Collection
    {
        return app(SpjWorkflowFilterService::class)->labels();
    }

    public function getTransactionsProperty(): LengthAwarePaginator
    {
        $query = $this->filteredQuery()
            ->with('spjPackage', 'rawMirrorRow')
            ->withCount('items')
            ->addSelect([
                'source_items_count' => DB::connection('school')->table('arkas_raw_mirror_rows as related_raw')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('related_raw.mirror_table_id', 'arkas_raw_mirror_rows.mirror_table_id')
                    ->whereRaw("json_extract(related_raw.payload, '$.id_kas_nota') = json_extract(arkas_raw_mirror_rows.payload, '$.id_kas_nota')")
                    ->whereRaw("NULLIF(TRIM(CAST(json_extract(related_raw.payload, '$.kode_rekening') AS TEXT)), '') IS NOT NULL"),
            ]);

        $perPage = $this->perPage === 'all' ? 100 : (int) $this->perPage;
        $perPage = in_array($perPage, [15, 25, 50, 100], true) ? $perPage : 15;

        $paginator = $query
            ->orderByRaw("CASE WHEN source_status = 'DELETED' OR requires_reconciliation = 1 THEN 0 ELSE 1 END")
            ->orderByRaw("COALESCE((SELECT CASE status WHEN 'DRAFT' THEN 0 WHEN 'READY' THEN 2 WHEN 'NUMBERED' THEN 3 WHEN 'FINAL' THEN 4 ELSE 5 END FROM spj_fresh_packages WHERE spj_fresh_packages.spj_fresh_transaction_id = spj_fresh_transactions.id LIMIT 1), 1)")
            ->orderByRaw("json_extract(arkas_raw_mirror_rows.payload, '$.tanggal_transaksi')")
            ->orderBy('id')
            ->paginate($perPage);

        if ($paginator->total() > 0 && $paginator->currentPage() > $paginator->lastPage()) {
            $lastPage = $paginator->lastPage();
            $this->paginators['page'] = $lastPage;

            return $query->paginate($perPage, ['*'], 'page', $lastPage);
        }

        return $paginator;
    }

    public function render(): View
    {
        return view('livewire.transactions-table', [
            'filteredStats' => $this->filteredStats,
            'statuses' => $this->statuses,
            'transactions' => $this->transactions,
        ]);
    }

    /** @return array{status:string,label:string} */
    public function workStatusFor(SpjFreshTransaction $transaction): array
    {
        if ($transaction->source_status === 'DELETED') {
            return ['status' => 'SOURCE_MISSING', 'label' => 'Perlu Perhatian'];
        }
        if ($transaction->source_status === 'SOURCE_MISSING') {
            return ['status' => 'SOURCE_MISSING', 'label' => 'Perlu Perhatian'];
        }

        if ($transaction->requires_reconciliation) {
            return ['status' => 'RECONCILIATION', 'label' => 'Perlu Perhatian'];
        }

        $package = $transaction->spjPackage;
        $packageStatus = strtoupper((string) ($package?->status ?? ''));

        if (in_array($packageStatus, ['CANCELLED', 'CANCELED'], true)) {
            return ['status' => 'CANCELLED', 'label' => 'Dibatalkan'];
        }

        if ($package?->finalized_at || in_array($packageStatus, ['FINAL', 'ARCHIVED', 'ARSIP'], true)) {
            return ['status' => 'FINAL', 'label' => 'Final'];
        }

        if (filled($package?->document_number) || in_array($packageStatus, ['NUMBERED', 'BERNOMOR'], true)) {
            return ['status' => 'NUMBERED', 'label' => 'Sudah Bernomor'];
        }

        if ($packageStatus === 'READY') {
            return ['status' => 'READY', 'label' => 'Siap Dinomori'];
        }

        if ($package) {
            return ['status' => 'DRAFT', 'label' => 'Perlu Dilengkapi'];
        }

        return ['status' => 'BELUM_LENGKAP', 'label' => 'Belum Dikerjakan'];
    }

    private function baseQuery(): Builder
    {
        return SpjFreshTransaction::query()
            ->select('spj_fresh_transactions.*')
            ->join('arkas_raw_mirror_rows', 'arkas_raw_mirror_rows.id', '=', 'spj_fresh_transactions.raw_mirror_row_id')
            ->where('spj_fresh_transactions.fiscal_year_id', session('active_fiscal_year_id'))
            ->where('spj_fresh_transactions.fund_source_id', session('active_fund_source_id'))
            ->whereIn('spj_fresh_transactions.source_status', ['ACTIVE', 'SOURCE_MISSING']);
    }

    private function filteredQuery(): Builder
    {
        $activeYear = $this->activeYear();
        $query = clone $this->baseQuery();

        $this->applySpendingRowConstraint($query);

        if ($this->month) {
            $query->whereRaw("CAST(strftime('%m', json_extract(arkas_raw_mirror_rows.payload, '$.tanggal_transaksi')) AS INTEGER) = ?", [$this->month]);
        } elseif ($this->quarter) {
            $query->whereRaw("date(json_extract(arkas_raw_mirror_rows.payload, '$.tanggal_transaksi')) between ? and ?", [
                now()->setYear($activeYear->year)->setMonth(($this->quarter - 1) * 3 + 1)->startOfMonth()->toDateString(),
                now()->setYear($activeYear->year)->setMonth($this->quarter * 3)->endOfMonth()->toDateString(),
            ]);
        } elseif ($this->semester) {
            $query->whereRaw("date(json_extract(arkas_raw_mirror_rows.payload, '$.tanggal_transaksi')) between ? and ?", [
                now()->setYear($activeYear->year)->setMonth($this->semester === 1 ? 1 : 7)->startOfMonth()->toDateString(),
                now()->setYear($activeYear->year)->setMonth($this->semester === 1 ? 6 : 12)->endOfMonth()->toDateString(),
            ]);
        }

        if (trim($this->q) !== '') {
            $search = trim($this->q);
            $query->where(function (Builder $query) use ($search): void {
                $query->where('spj_fresh_transactions.source_key', 'like', "%{$search}%")
                    ->orWhere('spj_fresh_transactions.payment_description', 'like', "%{$search}%")
                    ->orWhereRaw("json_extract(arkas_raw_mirror_rows.payload, '$.no_bukti') like ?", ["%{$search}%"])
                    ->orWhereRaw("json_extract(arkas_raw_mirror_rows.payload, '$.uraian') like ?", ["%{$search}%"])
                    ->orWhereRaw("json_extract(arkas_raw_mirror_rows.payload, '$.kode_rekening') like ?", ["%{$search}%"])
                    ->orWhereRaw("json_extract(arkas_raw_mirror_rows.payload, '$.nama_penerima') like ?", ["%{$search}%"])
                    ->orWhereRaw("json_extract(arkas_raw_mirror_rows.payload, '$.penerima') like ?", ["%{$search}%"])
                    ->orWhereRaw("json_extract(arkas_raw_mirror_rows.payload, '$.kode_kegiatan') like ?", ["%{$search}%"]);
            });
        }

        if ($this->status !== '') {
            $state = app(SpjWorkflowFilterService::class)->stateForLabel($this->status);
            if ($state !== null) {
                app(SpjWorkflowFilterService::class)->apply($query, $state);
            }
        }

        return $query;
    }

    private function filteredTaxTotal(): float
    {
        $db = DB::connection('school');
        $parentIds = $this->rawSpendingQuery()
            ->selectRaw("json_extract(arkas_raw_mirror_rows.payload, '$.id_kas_umum') as parent_source_id")
            ->pluck('parent_source_id')
            ->filter()
            ->values()
            ->all();

        if ($parentIds === []) {
            return 0.0;
        }

        $query = $db->table('arkas_raw_mirror_rows as tax_raw')
            ->join('arkas_raw_mirror_tables as tax_table', 'tax_table.id', '=', 'tax_raw.mirror_table_id')
            ->where('tax_table.source_table', 'kas_umum')
            ->where('tax_table.status', 'ACTIVE')
            ->whereRaw("COALESCE(json_extract(tax_raw.payload, '$.soft_delete'), '0') != '1'")
            ->whereRaw("NULLIF(TRIM(CAST(json_extract(tax_raw.payload, '$.volume') AS TEXT)), '') IS NULL")
            ->whereRaw("(COALESCE(json_extract(tax_raw.payload, '$.is_ppn'), '0') = '1' OR COALESCE(json_extract(tax_raw.payload, '$.is_pph_21'), '0') = '1' OR COALESCE(json_extract(tax_raw.payload, '$.is_pph_22'), '0') = '1' OR COALESCE(json_extract(tax_raw.payload, '$.is_pph_23'), '0') = '1' OR COALESCE(json_extract(tax_raw.payload, '$.is_pph_4'), '0') = '1' OR COALESCE(json_extract(tax_raw.payload, '$.is_sspd'), '0') = '1')")
            ->whereIn(DB::raw("json_extract(tax_raw.payload, '$.parent_id_kas_umum')"), $parentIds);

        return (float) $query->sum(DB::raw("CAST(COALESCE(json_extract(tax_raw.payload, '$.saldo'), 0) AS REAL)")) / 2;
    }

    private function rawSpendingQuery(): QueryBuilder
    {
        $query = DB::connection('school')->table('arkas_raw_mirror_rows')
            ->join('arkas_raw_mirror_tables', 'arkas_raw_mirror_tables.id', '=', 'arkas_raw_mirror_rows.mirror_table_id')
            ->where('arkas_raw_mirror_tables.source_table', 'kas_umum')
            ->where('arkas_raw_mirror_tables.status', 'ACTIVE')
            ->whereRaw("COALESCE(json_extract(arkas_raw_mirror_rows.payload, '$.soft_delete'), '0') != '1'")
            ->whereRaw("substr(json_extract(arkas_raw_mirror_rows.payload, '$.tanggal_transaksi'), 1, 4) = ?", [(string) $this->activeYear()->year]);
        $this->applySpendingRowConstraint($query);
        $this->applyPeriodToRawQuery($query, 'arkas_raw_mirror_rows.payload');

        if (trim($this->q) !== '') {
            $search = trim($this->q);
            $query->where(function (QueryBuilder $query) use ($search): void {
                $query->whereRaw("json_extract(arkas_raw_mirror_rows.payload, '$.no_bukti') like ?", ["%{$search}%"])
                    ->orWhereRaw("json_extract(arkas_raw_mirror_rows.payload, '$.uraian') like ?", ["%{$search}%"])
                    ->orWhereRaw("json_extract(arkas_raw_mirror_rows.payload, '$.kode_rekening') like ?", ["%{$search}%"])
                    ->orWhereRaw("json_extract(arkas_raw_mirror_rows.payload, '$.penerima') like ?", ["%{$search}%"]);
            });
        }

        return $query;
    }

    private function applySpendingRowConstraint(Builder|QueryBuilder $query): void
    {
        $query->whereRaw("(
            upper(trim(CAST(json_extract(arkas_raw_mirror_rows.payload, '$.no_bukti') AS TEXT))) LIKE 'BPU%'
            OR upper(trim(CAST(json_extract(arkas_raw_mirror_rows.payload, '$.no_bukti') AS TEXT))) LIKE 'BNU%'
        )");

        // Volume is not consistently populated by ARKAS for BKU rows. Prefer
        // the account code while retaining volume for older source payloads.
        $query->whereRaw("(
            NULLIF(TRIM(CAST(json_extract(arkas_raw_mirror_rows.payload, '$.kode_rekening') AS TEXT)), '') IS NOT NULL
            OR NULLIF(TRIM(CAST(json_extract(arkas_raw_mirror_rows.payload, '$.volume') AS TEXT)), '') IS NOT NULL
            OR (
                NULLIF(TRIM(CAST(json_extract(arkas_raw_mirror_rows.payload, '$.no_bukti') AS TEXT)), '') IS NOT NULL
                AND NOT EXISTS (
                    SELECT 1
                    FROM arkas_raw_mirror_rows AS spending_probe
                    WHERE spending_probe.mirror_table_id = arkas_raw_mirror_rows.mirror_table_id
                      AND (
                          NULLIF(TRIM(CAST(json_extract(spending_probe.payload, '$.kode_rekening') AS TEXT)), '') IS NOT NULL
                          OR NULLIF(TRIM(CAST(json_extract(spending_probe.payload, '$.volume') AS TEXT)), '') IS NOT NULL
                      )
                )
            )
        )");
    }

    private function applyPeriodToRawQuery(QueryBuilder $query, string $payloadColumn): void
    {
        $activeYear = $this->activeYear();

        if ($this->month) {
            $query->whereRaw("CAST(strftime('%m', json_extract($payloadColumn, '$.tanggal_transaksi')) AS INTEGER) = ?", [$this->month]);
        } elseif ($this->quarter) {
            $query->whereRaw("date(json_extract($payloadColumn, '$.tanggal_transaksi')) between ? and ?", [
                now()->setYear($activeYear->year)->setMonth(($this->quarter - 1) * 3 + 1)->startOfMonth()->toDateString(),
                now()->setYear($activeYear->year)->setMonth($this->quarter * 3)->endOfMonth()->toDateString(),
            ]);
        } elseif ($this->semester) {
            $query->whereRaw("date(json_extract($payloadColumn, '$.tanggal_transaksi')) between ? and ?", [
                now()->setYear($activeYear->year)->setMonth($this->semester === 1 ? 1 : 7)->startOfMonth()->toDateString(),
                now()->setYear($activeYear->year)->setMonth($this->semester === 1 ? 6 : 12)->endOfMonth()->toDateString(),
            ]);
        }
    }

    private function activeYear(): FiscalYear
    {
        return FiscalYear::query()->findOrFail(session('active_fiscal_year_id'));
    }

    public function paymentMethodFor(SpjFreshTransaction $transaction): string
    {
        $current = strtolower((string) $transaction->payment_method);

        if (in_array($current, ['transfer_bank', 'siplah', 'tunai'], true)) {
            return $current;
        }

        if ($transaction->is_siplah) {
            return 'siplah';
        }

        $proofNumber = strtolower((string) $transaction->no_bukti);
        if (str_contains($proofNumber, 'non_tunai') || str_contains($proofNumber, 'non tunai') || str_starts_with($proofNumber, 'bnu')) {
            return 'transfer_bank';
        }

        if (str_contains($current, 'non tunai') || str_contains($current, 'transfer') || str_contains($current, 'cms')) {
            return 'transfer_bank';
        }

        return 'tunai';
    }
}
