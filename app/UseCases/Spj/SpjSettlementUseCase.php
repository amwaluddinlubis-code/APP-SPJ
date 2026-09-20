<?php

namespace App\UseCases\Spj;

use App\Models\Transaction;
use App\Services\OperationalAuditService;
use App\Services\SpjReadPathSelector;
use App\Services\SpjV2SettlementService;
use App\Services\TransactionSettlementService;
use App\Support\ActiveSpjContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SpjSettlementUseCase
{
    public function __construct(
        private readonly TransactionSettlementService $settlements,
        private readonly OperationalAuditService $audit,
        private readonly ActiveSpjContext $context,
        private readonly SpjReadPathSelector $readPaths,
        private readonly SpjV2SettlementService $v2Settlement,
    ) {}

    public function storePayment(Request $request, string $transactionId): RedirectResponse
    {
        $transaction = Transaction::query()->with('spjPackage')->findOrFail($transactionId);
        if ($transaction->spjPackage?->status === 'FINAL') {
            $selection = $this->readPaths->select(
                DB::connection('school'),
                $this->context->fiscalYearId(),
                (int) ($this->context->fundSourceId() ?? 0),
            );
            if ($selection['requested'] === SpjReadPathSelector::V2) {
                $result = $this->v2Settlement->settle($transaction->spjPackage);

                return back()->with(
                    $result['status'] === 'SETTLED' ? 'success' : 'error',
                    $result['reason'],
                );
            }
        }
        abort_unless($this->context->matchesTransaction($transaction), 404);
        if ($transaction->spjPackage && ! $transaction->spjPackage->isEditable()) {
            return back()->with('error', 'Pembayaran tidak dapat diubah karena paket SPJ sudah dikunci. Batalkan nomor dan buka paket untuk koreksi terlebih dahulu.');
        }
        $data = $request->validate([
            'payment_date' => ['required', 'date'], 'gross_amount' => ['required', 'numeric', 'gt:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'], 'payment_method' => ['nullable', 'string', 'max:40'],
            'payment_reference' => ['nullable', 'string', 'max:160'],
        ]);
        $payment = $this->settlements->addPayment($transaction, $data);
        $this->audit->record(
            $transaction->fiscal_year_id,
            'TRANSACTION',
            $transaction->id,
            'TAMBAH_PEMBAYARAN',
            'Tahap pembayaran '.$payment->scope_key.' ditambahkan pada transaksi '.$transaction->no_bukti.': bruto '.$payment->gross_amount.'.'
        );

        return back()->with('success', 'Tahap pembayaran berhasil ditambahkan.');
    }

    public function storeGoodsReceipt(Request $request, string $transactionId): RedirectResponse
    {
        $transaction = Transaction::query()->with('spjPackage')->findOrFail($transactionId);
        abort_unless($this->context->matchesTransaction($transaction), 404);
        if ($transaction->spjPackage && ! $transaction->spjPackage->isEditable()) {
            return back()->with('error', 'Penerimaan barang tidak dapat diubah karena paket SPJ sudah dikunci. Batalkan nomor dan buka paket untuk koreksi terlebih dahulu.');
        }
        $data = $request->validate([
            'receipt_date' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'], 'items.*.transaction_item_id' => ['required', 'integer'],
            'items.*.quantity_received' => ['required', 'numeric', 'gt:0'], 'items.*.amount_received' => ['nullable', 'numeric', 'min:0'],
        ]);
        $items = $data['items'];
        unset($data['items']);
        $receipt = $this->settlements->addGoodsReceipt($transaction, $data, $items);
        $this->audit->record(
            $transaction->fiscal_year_id,
            'TRANSACTION',
            $transaction->id,
            'TAMBAH_PENERIMAAN',
            'Tahap penerimaan barang '.$receipt->scope_key.' ditambahkan pada transaksi '.$transaction->no_bukti.'.'
        );

        return back()->with('success', 'Tahap penerimaan barang berhasil ditambahkan.');
    }
}
