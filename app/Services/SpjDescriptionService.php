<?php

namespace App\Services;

use App\Models\Transaction;

/**
 * Single writer and formatter for SPJ narrative corrections/defaults.
 *
 * payment_description and item_description stay editable up to NUMBERED
 * (see docs/SPJ_DESIGN_DECISIONS.md §4.2/§4.4). HTTP entry points and
 * package defaults normalize through here so narrative rules cannot diverge.
 */
class SpjDescriptionService
{


    public function updatePaymentDescription(Transaction $transaction, ?string $description): void
    {
        $description = trim((string) $description);
        $transaction->forceFill([
            'payment_description' => $description !== '' ? $description : null,
        ])->save();
    }

    public function siplahPaymentDescription(Transaction $transaction): string
    {
        $response = data_get($transaction->siplah_metadata, 'siplahResponse', []);
        $marketplace = trim((string) data_get($response, 'marketplace_displayname'));
        $merchant = trim((string) data_get($response, 'merchant'));
        $invoice = trim((string) data_get($response, 'invoice_number'));

        if ($marketplace !== '') {
            $description = $merchant !== ''
                ? 'Pembelian barang di Merchant '.$merchant.' melalui '.$marketplace
                : 'Pembelian barang melalui '.$marketplace;

            return $description.($invoice !== '' ? ' berdasarkan invoice '.$invoice : '');
        }

        $items = collect(data_get($response, 'items', []));
        if ($items->isEmpty()) {
            $items = collect(data_get($response, 'transaction_items', []));
        }

        $itemNames = $items
            ->map(fn (mixed $item): string => trim((string) data_get($item, 'siplah_item_name')))
            ->filter()
            ->values();

        return $itemNames->isNotEmpty()
            ? 'Pembelian barang: '.$itemNames->take(3)->implode(', ')
            : 'Pembelian barang melalui SiPLah';
    }
}
