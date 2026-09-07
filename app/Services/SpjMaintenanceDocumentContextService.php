<?php

namespace App\Services;

use App\Models\SpjPackage;
use App\Models\Transaction;

class SpjMaintenanceDocumentContextService
{
    /**
     * For PEMELIHARAAN documents, expose material items and labor workers from
     * the two linked BKU transactions as one in-memory document context.
     * No source/manual records are modified here.
     */
    public function apply(SpjPackage $package): SpjPackage
    {
        $transaction = $package->transaction;

        if (strtoupper((string) $transaction->spj_category) !== 'PEMELIHARAAN') {
            return $package;
        }

        $transaction->loadMissing([
            'items',
            'workOrder',
            'workers',
            'maintenanceMaterialTransaction.items',
            'maintenanceLaborTransaction.workOrder',
            'maintenanceLaborTransaction.workers',
        ]);

        $material = $this->materialTransaction($transaction);
        $labor = $this->laborTransaction($transaction);

        if ($material && $material->relationLoaded('items')) {
            $transaction->setRelation('items', $material->items);
        }

        if ($labor) {
            if ($labor->relationLoaded('workOrder')) {
                $transaction->setRelation('workOrder', $labor->workOrder);
            }
            if ($labor->relationLoaded('workers')) {
                $transaction->setRelation('workers', $labor->workers);
            }
        }

        $transaction->setAttribute('maintenance_material_source_id', $material?->id);
        $transaction->setAttribute('maintenance_labor_source_id', $labor?->id);
        $transaction->setAttribute(
            'maintenance_combined_amount',
            (float) ($material?->gross_amount ?? 0) + (float) ($labor?->gross_amount ?? 0)
        );

        return $package;
    }

    private function materialTransaction(Transaction $transaction): ?Transaction
    {
        if ($transaction->maintenanceMaterialTransaction) {
            return $transaction->maintenanceMaterialTransaction;
        }

        // Current transaction is the material side when it points to labor.
        return $transaction->maintenance_labor_transaction_id ? $transaction : null;
    }

    private function laborTransaction(Transaction $transaction): ?Transaction
    {
        if ($transaction->maintenanceLaborTransaction) {
            return $transaction->maintenanceLaborTransaction;
        }

        // Current transaction is the labor side when it points to material.
        return $transaction->maintenance_material_transaction_id ? $transaction : null;
    }
}
