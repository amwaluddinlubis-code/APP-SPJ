<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TransactionSettlementService
{
    public function __construct(private FiscalPeriodWorkflowService $periods) {}

    /** @param array<string,mixed> $data */
    public function addPayment(Transaction $transaction, array $data): TransactionPayment
    {
        return DB::connection('school')->transaction(function () use ($transaction, $data): TransactionPayment {
            $gross = (float) $data['gross_amount'];
            $tax = (float) ($data['tax_amount'] ?? 0);
            if ($gross <= 0 || $tax < 0 || $tax > $gross) {
                throw new \InvalidArgumentException('Nilai pembayaran atau pajak tidak valid.');
            }
            $paymentDate = Carbon::parse($data['payment_date']);
            if ((int) $paymentDate->format('Y') !== (int) $transaction->fiscalYear->year) {
                throw new \RuntimeException('Tanggal pembayaran harus berada dalam tahun anggaran aktif.');
            }
            $latestReceipt = $transaction->goodsReceipts()->where('status', '!=', 'CANCELLED')->max('receipt_date');
            if ($latestReceipt && $paymentDate->lt(Carbon::parse($latestReceipt))) {
                throw new \RuntimeException('Tanggal pembayaran tidak boleh lebih awal daripada penerimaan barang terakhir.');
            }
            $paid = (float) $transaction->payments()->where('status', '!=', 'CANCELLED')->sum('gross_amount');
            if (round($paid + $gross, 2) > round((float) $transaction->gross_amount, 2)) {
                throw new \RuntimeException('Total pembayaran tidak boleh melebihi nilai transaksi.');
            }
            $sequence = ((int) $transaction->payments()->max('payment_sequence')) + 1;
            $quarter = (int) ceil((int) $transaction->transaction_date->format('n') / 3);

            return $transaction->payments()->create([
                ...$data,
                'scope_key' => 'PAYMENT:'.$sequence,
                'payment_sequence' => $sequence,
                'net_amount' => $gross - $tax,
                'is_late_entry' => $this->periods->isLateEntry($transaction->fiscal_year_id, $quarter),
            ]);
        });
    }

    /** @param array<string,mixed> $data @param array<int,array<string,mixed>> $items */
    public function addGoodsReceipt(Transaction $transaction, array $data, array $items): GoodsReceipt
    {
        return DB::connection('school')->transaction(function () use ($transaction, $data, $items): GoodsReceipt {
            if ($items === []) {
                throw new \InvalidArgumentException('Penerimaan harus memiliki minimal satu rincian barang.');
            }
            $receiptDate = Carbon::parse($data['receipt_date']);
            if ((int) $receiptDate->format('Y') !== (int) $transaction->fiscalYear->year) {
                throw new \RuntimeException('Tanggal penerimaan harus berada dalam tahun anggaran aktif.');
            }
            $earliestOrder = $transaction->goods()->whereNotNull('order_date')->min('order_date');
            if ($earliestOrder && $receiptDate->lt(Carbon::parse($earliestOrder))) {
                throw new \RuntimeException('Tanggal penerimaan tidak boleh lebih awal daripada tanggal pesanan.');
            }
            foreach ($items as $item) {
                $ordered = $transaction->items()->findOrFail($item['transaction_item_id']);
                $received = (float) $ordered->receiptItems()->whereHas('receipt', fn ($query) => $query->where('status', '!=', 'CANCELLED'))->sum('quantity_received');
                if ((float) $item['quantity_received'] <= 0 || round($received + (float) $item['quantity_received'], 4) > round((float) $ordered->quantity, 4)) {
                    throw new \RuntimeException('Jumlah barang diterima melebihi jumlah yang dipesan untuk '.$ordered->item_description.'.');
                }
            }
            $sequence = ((int) $transaction->goodsReceipts()->max('receipt_sequence')) + 1;
            $quarter = (int) ceil((int) $transaction->transaction_date->format('n') / 3);
            $receipt = $transaction->goodsReceipts()->create([
                ...$data,
                'scope_key' => 'RECEIPT:'.$sequence,
                'receipt_sequence' => $sequence,
                'is_late_entry' => $this->periods->isLateEntry($transaction->fiscal_year_id, $quarter),
            ]);
            $receipt->items()->createMany($items);

            return $receipt->load('items');
        });
    }
}
