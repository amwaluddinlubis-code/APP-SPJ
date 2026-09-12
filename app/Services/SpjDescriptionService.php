<?php

namespace App\Services;

use App\Models\Transaction;

/**
 * Single writer for SPJ narrative corrections.
 *
 * payment_description and item_description stay editable up to NUMBERED
 * (see docs/SPJ_DESIGN_DECISIONS.md §4.2/§4.4). Both HTTP entry points —
 * Detail Transaksi and Isian Manual Paket — must normalize through here
 * so the two paths cannot diverge silently.
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
}
