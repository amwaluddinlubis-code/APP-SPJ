<?php

namespace App\UseCases\Spj;

use App\Models\SpjFreshPackage;
use App\Models\SpjFreshTransaction;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Services\FiscalPeriodWorkflowService;
use App\Services\OperationalAuditService;
use App\Support\ActiveSpjContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

class CreateSpjDraftUseCase
{
    public function __construct(
        private readonly FiscalPeriodWorkflowService $periods,
        private readonly OperationalAuditService $audit,
        private readonly ActiveSpjContext $context,
    ) {}

    public function handle(string $transactionId): RedirectResponse
    {
        $transaction = Transaction::query()
            ->with([
                'spjPackage',
                'items:id,transaction_id,item_description',
            ])
            ->withCount('items')
            ->forSourceIdentifier($transactionId)
            ->first();

        if (! $transaction) {
            return $this->handleFreshTransaction($transactionId);
        }

        if (! $this->context->matchesTransaction($transaction)) {
            return redirect()
                ->route('transactions.index')
                ->with('error', 'Transaksi tidak ditemukan pada sekolah, tahun anggaran, atau sumber dana yang sedang aktif.');
        }

        if ($transaction->items_count < 1) {
            return redirect()
                ->route('transactions.show', $transaction->id)
                ->with('error', 'Paket SPJ belum dapat dibuka karena transaksi belum memiliki rincian barang/jasa.');
        }

        $missingDescriptions = $transaction->items
            ->filter(fn ($item): bool => blank(trim((string) $item->item_description)))
            ->count();

        if ($missingDescriptions > 0) {
            return redirect()
                ->to(route('transactions.show', $transaction->id).'#rincian-transaksi')
                ->with(
                    'error',
                    $missingDescriptions.' uraian item SPJ belum tersimpan. Lengkapi dan simpan seluruh Uraian Barang/Jasa untuk SPJ terlebih dahulu sebelum melihat Paket SPJ.'
                );
        }

        if ($transaction->spjPackage) {
            return redirect()->route('spj.index', [
                'tab' => 'paket',
                'package_id' => $transaction->spjPackage->id,
            ]);
        }

        $package = SpjPackage::firstOrCreate(
            ['transaction_id' => $transaction->id],
            [
                'quarter_code' => $this->quarter($transaction),
                'semester_code' => $this->semester($transaction),
                'status' => 'DRAFT',
            ],
        );

        if ($package->wasRecentlyCreated) {
            $quarter = (int) ceil((int) $transaction->transaction_date->format('n') / 3);
            if ($this->periods->isLateEntry($transaction->fiscal_year_id, $quarter)) {
                $package->forceFill(['is_late_entry' => true])->save();
            }

            $this->audit->record(
                $transaction->fiscal_year_id,
                'SPJ_PACKAGE',
                $package->id,
                'BUAT_DRAFT',
                'Draft paket SPJ dibuat dari transaksi '.$transaction->no_bukti,
            );
        }

        return redirect()
            ->route('spj.index', ['tab' => 'paket', 'package_id' => $package->id])
            ->with('success', $package->wasRecentlyCreated
                ? 'Draft paket SPJ dibuat. Lengkapi seluruh data dokumen di halaman Paket SPJ.'
                : 'Paket SPJ yang sudah ada dibuka kembali.');
    }

    private function handleFreshTransaction(string $transactionId): RedirectResponse
    {
        $transaction = SpjFreshTransaction::query()
            ->with(['items', 'spjPackage'])
            ->forSpjContext($this->context)
            ->where('source_table', 'kas_umum')
            ->where('source_key', $transactionId)
            ->first();

        if (! $transaction) {
            return redirect()
                ->route('transactions.index')
                ->with('error', 'Transaksi tidak ditemukan pada sekolah, tahun anggaran, atau sumber dana yang sedang aktif.');
        }

        if ($transaction->items->isEmpty()) {
            return redirect()
                ->route('transactions.show', $transaction->source_key)
                ->with('error', 'Paket SPJ belum dapat dibuka karena transaksi belum memiliki rincian barang/jasa.');
        }

        $missingDescriptions = $transaction->items
            ->filter(fn ($item): bool => blank(trim((string) $item->item_description)))
            ->count();

        if ($missingDescriptions > 0) {
            return redirect()
                ->to(route('transactions.show', $transaction->source_key).'#rincian-transaksi')
                ->with('error', $missingDescriptions.' uraian item SPJ belum tersimpan. Lengkapi dan simpan seluruh Uraian Barang/Jasa untuk SPJ terlebih dahulu sebelum melihat Paket SPJ.');
        }

        $package = $transaction->spjPackage ?: SpjFreshPackage::firstOrCreate(
            ['spj_fresh_transaction_id' => $transaction->id],
            [
                'quarter_code' => $this->quarterForDate($transaction->transaction_date),
                'semester_code' => $this->semesterForDate($transaction->transaction_date),
                'status' => 'DRAFT',
            ],
        );

        if ($package->wasRecentlyCreated) {
            $this->audit->record(
                $transaction->fiscal_year_id,
                'SPJ_FRESH_PACKAGE',
                $package->id,
                'BUAT_DRAFT',
                'Draft paket SPJ fresh dibuat dari transaksi '.$transaction->source_key,
            );
        }

        return redirect()
            ->route('spj.index', ['tab' => 'paket', 'package_id' => $package->id])
            ->with('success', $package->wasRecentlyCreated
                ? 'Draft paket SPJ dibuat dari data ARKAS. Lengkapi isian paket.'
                : 'Paket SPJ yang sudah ada dibuka kembali.');
    }

    private function quarter(Transaction $transaction): string
    {
        $month = (int) $transaction->transaction_date?->format('n');

        return 'TW-'.(int) ceil(max(1, $month) / 3);
    }

    private function quarterForDate(?Carbon $date): string
    {
        $month = (int) $date?->format('n');

        return 'TW-'.(int) ceil(max(1, $month) / 3);
    }

    private function semesterForDate(?Carbon $date): string
    {
        return ((int) $date?->format('n') <= 6) ? 'SEM-I' : 'SEM-II';
    }

    private function semester(Transaction $transaction): string
    {
        return ((int) $transaction->transaction_date?->format('n') <= 6) ? 'SEM-I' : 'SEM-II';
    }
}
