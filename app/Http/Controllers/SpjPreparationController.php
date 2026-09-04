<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\SpjDocumentRequirementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SpjPreparationController extends Controller
{
    public function __invoke(string $transactionId, SpjDocumentRequirementService $requirements): View|RedirectResponse
    {
        $transaction = Transaction::query()
            ->with([
                'items',
                'goods',
                'workOrder',
                'workers',
                'participants',
                'travels',
                'honors',
                'payments',
                'goodsReceipts',
                'spjPackage',
            ])
            ->find($transactionId);

        if (! $transaction
            || $transaction->fiscal_year_id !== (int) session('active_fiscal_year_id')
            || (int) $transaction->fund_source_id !== (int) session('active_fund_source_id')) {
            return redirect()
                ->route('transactions.index')
                ->with('error', 'Transaksi tidak ditemukan pada sekolah, tahun anggaran, atau sumber dana yang sedang aktif.');
        }

        $allRequirements = collect($requirements->forTransaction($transaction));
        $summary = $requirements->summary($transaction);
        $missingRequired = $allRequirements
            ->filter(fn (array $item): bool => $item['applicable'] && $item['required'] && ! $item['available'])
            ->values();
        $optionalIncomplete = $allRequirements
            ->filter(fn (array $item): bool => $item['applicable'] && ! $item['required'] && ! $item['available'])
            ->values();
        $completed = $allRequirements
            ->filter(fn (array $item): bool => $item['applicable'] && $item['available'])
            ->values();

        $actionUrl = function (array $item) use ($transaction): string {
            return match ($item['key']) {
                'transaction_details' => route('transactions.show', $transaction->id).'#rincian-transaksi',
                'a2', 'payment_evidence', 'siplah_order', 'vendor', 'invoice', 'internal_order',
                'goods_receipt', 'bap', 'bast', 'work_rab', 'work_spk', 'workers', 'travel', 'honor', 'participants'
                    => route('transactions.show', $transaction->id).'#modul-buat-spj',
                default => route('transactions.show', $transaction->id),
            };
        };

        return view('spj.prepare-workspace', compact(
            'transaction',
            'summary',
            'missingRequired',
            'optionalIncomplete',
            'completed',
            'actionUrl',
        ));
    }
}
