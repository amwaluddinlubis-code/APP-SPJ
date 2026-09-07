<?php

namespace App\UseCases\Spj;

use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Services\FiscalPeriodWorkflowService;
use App\Services\OperationalAuditService;
use Illuminate\Http\RedirectResponse;

class CreateSpjDraftUseCase
{
    public function handle(string $transactionId): RedirectResponse
    {
        $transaction = Transaction::query()
            ->with('spjPackage')
            ->withCount('items')
            ->find($transactionId);

        if (! $transaction
            || $transaction->fiscal_year_id !== (int) session('active_fiscal_year_id')
            || (int) $transaction->fund_source_id !== (int) session('active_fund_source_id')) {
            return redirect()
                ->route('transactions.index')
                ->with('error', 'Transaksi tidak ditemukan pada sekolah, tahun anggaran, atau sumber dana yang sedang aktif.');
        }

        if ($transaction->items_count < 1) {
            return redirect()
                ->route('transactions.show', $transaction->id)
                ->with('error', 'Paket SPJ belum dapat dibuat karena transaksi belum memiliki rincian barang/jasa.');
        }

        if ($transaction->spjPackage) {
            return redirect()->route('spj.index', [
                'tab' => 'paket',
                'package_id' => $transaction->spjPackage->id,
            ]);
        }

        $package = SpjPackage::create([
            'transaction_id' => $transaction->id,
            'quarter_code' => $this->quarter($transaction),
            'semester_code' => $this->semester($transaction),
            'status' => 'DRAFT',
        ]);

        $quarter = (int) ceil((int) $transaction->transaction_date->format('n') / 3);
        if (app(FiscalPeriodWorkflowService::class)->isLateEntry($transaction->fiscal_year_id, $quarter)) {
            $package->forceFill(['is_late_entry' => true])->save();
        }

        app(OperationalAuditService::class)->record(
            $transaction->fiscal_year_id,
            'SPJ_PACKAGE',
            $package->id,
            'BUAT_DRAFT',
            'Draft paket SPJ dibuat dari transaksi '.$transaction->no_bukti,
        );

        return redirect()
            ->route('spj.index', ['tab' => 'paket', 'package_id' => $package->id])
            ->with('success', 'Draft paket SPJ dibuat. Lengkapi seluruh data dokumen di halaman Paket SPJ.');
    }

    private function quarter(Transaction $transaction): string
    {
        $month = (int) $transaction->transaction_date?->format('n');

        return 'TW-'.(int) ceil(max(1, $month) / 3);
    }

    private function semester(Transaction $transaction): string
    {
        return ((int) $transaction->transaction_date?->format('n') <= 6) ? 'SEM-I' : 'SEM-II';
    }
}
