<?php

namespace App\Services;

use App\Models\SpjFreshPackage;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Database\Eloquent\Collection;

final class SpjFreshPackageDocumentAdapter
{
    public function toLegacyPackage(SpjFreshPackage $freshPackage): SpjPackage
    {
        $freshTransaction = $freshPackage->transaction;
        $payload = $freshTransaction?->rawMirrorRow?->payload ?? [];
        $taxes = $freshTransaction?->tax_breakdown ?? [];

        $transaction = new Transaction;
        $transaction->setRawAttributes([
            'id' => $freshTransaction?->id,
            'fiscal_year_id' => $freshTransaction?->fiscal_year_id,
            'fund_source_id' => $freshTransaction?->fund_source_id,
            'id_kas_umum' => $payload['id_kas_umum'] ?? null,
            'source_key' => $freshTransaction?->source_key,
            'no_bukti' => $freshTransaction?->no_bukti,
            'transaction_date' => $freshTransaction?->transaction_date?->toDateString(),
            'description' => $freshTransaction?->description,
            'payment_description' => $freshTransaction?->payment_description ?: $freshTransaction?->description,
            'payment_method' => $freshTransaction?->payment_method,
            'payment_reference' => $freshTransaction?->payment_reference,
            'activity_code' => $freshTransaction?->activity_code,
            'activity_name' => $freshTransaction?->activity_name,
            'account_code' => $freshTransaction?->account_code,
            'account_name' => $payload['nama_rekening'] ?? $payload['account_name'] ?? null,
            'recipient_name' => $freshTransaction?->recipient_name,
            'receipt_recipient_name' => $freshTransaction?->effective_receipt_recipient_name,
            'gross_amount' => $freshTransaction?->gross_amount,
            'ppn' => $taxes['ppn'] ?? 0,
            'pph21' => $taxes['pph21'] ?? 0,
            'pph22' => $taxes['pph22'] ?? 0,
            'pph23' => $taxes['pph23'] ?? 0,
            'pph4' => $taxes['pph4'] ?? 0,
            'sspd' => $taxes['sspd'] ?? 0,
            'tax_total' => $freshTransaction?->tax_total,
            'net_amount' => $freshTransaction?->net_amount,
            'spj_category' => $freshTransaction?->spj_category,
            'is_siplah' => false,
            'event_name' => $payload['nama_acara'] ?? $payload['event_name'] ?? null,
            'event_location' => $payload['tempat_acara'] ?? $payload['event_location'] ?? null,
        ], true);

        $items = new Collection;
        foreach ($freshTransaction?->items ?? [] as $freshItem) {
            $item = new TransactionItem;
            $item->setRawAttributes([
                'id' => $freshItem->id,
                'item_description' => $freshItem->item_description,
                'description' => $freshItem->item_description,
                'quantity' => $freshItem->quantity,
                'unit' => $freshItem->unit,
                'unit_price' => $freshItem->unit_price,
                'amount' => $freshItem->amount,
                'account_code' => $freshItem->account_code,
                'account_name' => $freshItem->account_name,
            ], true);
            $items->add($item);
        }

        foreach (['goods', 'workers', 'participants', 'travels', 'honors', 'serviceRecipients', 'payments', 'goodsReceipts'] as $relation) {
            $transaction->setRelation($relation, new Collection);
        }
        $transaction->setRelation('items', $items);
        $transaction->setRelation('workOrder', null);

        $package = new SpjPackage;
        $package->setRawAttributes([
            'id' => $freshPackage->id,
            'transaction_id' => $freshTransaction?->id,
            'document_number' => $freshPackage->document_number,
            'quarter_code' => $freshPackage->quarter_code,
            'semester_code' => $freshPackage->semester_code,
            'phase_code' => $freshPackage->phase_code,
            'status' => $freshPackage->status,
        ], true);
        $package->setRelation('transaction', $transaction);
        $package->setRelation('documents', new Collection);

        return $package;
    }
}
