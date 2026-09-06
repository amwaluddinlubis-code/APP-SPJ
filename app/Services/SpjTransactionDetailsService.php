<?php

namespace App\Services;

use App\Models\SpjMaintenance;
use App\Models\Transaction;

class SpjTransactionDetailsService
{
    /**
     * Copy category-specific form values to their dedicated SPJ relations.
     *
     * Legacy transaction columns remain populated during the transition so
     * previously generated documents and incomplete historical records work.
     *
     * @param  array<string, mixed>  $details
     */
    public function synchronize(Transaction $transaction, array $details): void
    {
        $category = strtoupper((string) $transaction->spj_category);

        if (in_array($category, ['BARANG', 'KONSUMSI'], true)) {
            $this->synchronizeGoods($transaction, $details);
        }

        if ($category === 'PEMELIHARAAN') {
            $this->synchronizeWorkOrder($transaction, $details);
        }

        if ($category === 'SPPD') {
            $this->synchronizeTravel($transaction, $details);
        }

        if ($category === 'KONSUMSI') {
            $this->synchronizeParticipants($transaction, $details);
        }

        if ($category === 'HONOR_PEGAWAI') {
            $this->synchronizeHonors($transaction, $details);
        }

        if ($category === 'JASA_LAINNYA') {
            $this->synchronizeServiceRecipients($transaction, $details);
        }
    }

    /** @param array<string, mixed> $details */
    private function synchronizeGoods(Transaction $transaction, array $details): void
    {
        $purchaseDetails = [
            'order_number' => $details['order_number'] ?? null,
            'order_date' => $details['order_date'] ?? null,
            'bap_number' => $details['bap_number'] ?? null,
            'bap_date' => $details['bap_date'] ?? null,
            'bast_number' => $details['bast_number'] ?? null,
            'bast_date' => $details['bast_date'] ?? null,
        ];

        if (collect($purchaseDetails)->filter(fn ($value) => filled($value))->isNotEmpty()) {
            foreach ($transaction->items as $item) {
                $item->goods()->updateOrCreate([], $purchaseDetails);
            }
        }
    }

    /** @param array<string, mixed> $details */
    private function synchronizeWorkOrder(Transaction $transaction, array $details): void
    {
        if (blank($details['work_description'] ?? null)) {
            return;
        }

        $maintenance = SpjMaintenance::query()->firstOrCreate(
            ['fiscal_year_id' => $transaction->fiscal_year_id, 'name' => 'Transaksi '.$transaction->no_bukti],
            ['description' => $details['work_description'], 'default_location' => $details['work_location'] ?? null]
        );
        $transaction->workOrder()->updateOrCreate([], [
            'maintenance_id' => $maintenance->id,
            'expense_type' => 'UPAH',
            'work_description' => $details['work_description'],
            'work_location' => $details['work_location'] ?? null,
            'work_started_at' => $details['work_started_at'] ?? null,
            'work_completed_at' => $details['work_completed_at'] ?? null,
            'spk_number' => $details['spk_number'] ?? null,
            'spk_date' => $details['spk_date'] ?? null,
            'rab_number' => $details['rab_number'] ?? null,
            'rab_date' => $details['rab_date'] ?? null,
            'work_started_at' => $details['work_started_at'] ?? null,
            'work_completed_at' => $details['work_completed_at'] ?? null,
        ]);

        $workOrder = $transaction->workOrder()->first();
        if (! $workOrder || ! array_key_exists('workers', $details)) {
            return;
        }

        $workOrder->workers()->delete();
        foreach ($details['workers'] ?? [] as $sortOrder => $worker) {
            if (blank($worker['name'] ?? null)) {
                continue;
            }

            $days = (float) ($worker['work_days'] ?? 0);
            $rate = (float) ($worker['daily_rate'] ?? 0);
            $workOrder->workers()->create([
                'name' => trim($worker['name']),
                'job_description' => blank($worker['job_description'] ?? null) ? null : trim($worker['job_description']),
                'work_days' => $days,
                'daily_rate' => $rate,
                'amount' => $days * $rate,
                'is_receipt_recipient' => (bool) ($worker['is_receipt_recipient'] ?? false),
                'notes' => blank($worker['notes'] ?? null) ? null : trim($worker['notes']),
                'sort_order' => $sortOrder,
            ]);
        }
    }

    /** @param array<string, mixed> $details */
    private function synchronizeTravel(Transaction $transaction, array $details): void
    {
        $travels = $details['travels'] ?? [];
        if ($travels !== []) {
            $transaction->travels()->delete();
            foreach ($travels as $sortOrder => $travel) {
                if (blank($travel['traveler_name'] ?? null)) {
                    continue;
                }
                $transaction->travels()->create([
                    'traveler_name' => trim($travel['traveler_name']),
                    'destination' => $travel['destination'] ?? null,
                    'purpose' => $travel['purpose'] ?? null,
                    'assignment_letter_number' => $travel['assignment_letter_number'] ?? null,
                    'assignment_letter_date' => $travel['assignment_letter_date'] ?? null,
                    'departure_date' => $travel['departure_date'] ?? null,
                    'return_date' => $travel['return_date'] ?? null,
                    'transport_mode' => $travel['transport_mode'] ?? null,
                    'amount' => (float) ($travel['amount'] ?? 0),
                    'notes' => $travel['notes'] ?? null,
                    'sort_order' => $sortOrder,
                ]);
            }

            return;
        }

        $transaction->travels()->updateOrCreate(
            ['id' => $transaction->travels()->value('id')],
            [
                'traveler_name' => $transaction->signatory_name ?: $transaction->recipient_name,
                'destination' => $details['work_location'] ?? null,
                'purpose' => $details['work_description'] ?? $transaction->payment_description,
                'assignment_letter_number' => null,
                'assignment_letter_date' => $details['work_started_at'] ?? null,
                'departure_date' => $details['work_started_at'] ?? null,
                'return_date' => $details['work_completed_at'] ?? null,
                'amount' => $transaction->gross_amount,
                'sort_order' => 0,
            ]
        );
    }

    /** @param array<string, mixed> $details */
    private function synchronizeParticipants(Transaction $transaction, array $details): void
    {
        $transaction->forceFill([
            'event_name' => $details['event_name'] ?? null,
            'event_location' => $details['event_location'] ?? null,
            'event_date' => $details['event_date'] ?? null,
            'participant_count' => (int) ($details['participant_count'] ?? 0),
        ])->save();

        $item = $transaction->items->first();
        if (! $item) {
            return;
        }

        $item->participants()->delete();
        foreach ($details['participants'] ?? [] as $sortOrder => $participant) {
            if (blank($participant['name'] ?? null)) {
                continue;
            }
            $item->participants()->create([
                'name' => trim($participant['name']),
                'position' => blank($participant['position'] ?? null) ? null : trim($participant['position']),
                'portions' => (float) ($participant['portions'] ?? 1),
                'sort_order' => $sortOrder,
            ]);
        }
    }

    /** @param array<string, mixed> $details */
    private function synchronizeHonors(Transaction $transaction, array $details): void
    {
        $item = $transaction->items->first();
        if (! $item) {
            return;
        }

        $item->honors()->delete();
        foreach ($details['workers'] ?? [] as $sortOrder => $recipient) {
            if (blank($recipient['name'] ?? null)) {
                continue;
            }

            $units = max(1, (float) ($recipient['work_days'] ?? 1));
            $rate = (float) ($recipient['daily_rate'] ?? 0);
            $gross = $units * $rate;
            $taxRate = (float) ($transaction->pph21_rate ?? 0);
            $tax = round($gross * $taxRate / 100, 2);
            $item->honors()->create([
                'name' => trim($recipient['name']),
                'position' => blank($recipient['job_description'] ?? null) ? null : trim($recipient['job_description']),
                'honor_months' => $units,
                'rate_per_unit' => $rate,
                'gross_amount' => $gross,
                'tax_rate' => $taxRate,
                'tax_amount' => $tax,
                'net_amount' => $gross - $tax,
                'sort_order' => $sortOrder,
            ]);
        }
    }

    /** @param array<string, mixed> $details */
    private function synchronizeServiceRecipients(Transaction $transaction, array $details): void
    {
        if (! array_key_exists('service_recipients', $details)) {
            return;
        }

        $transaction->serviceRecipients()->delete();
        foreach ($details['service_recipients'] ?? [] as $sortOrder => $recipient) {
            if (blank($recipient['name'] ?? null)) {
                continue;
            }

            $quantity = max(0, (float) ($recipient['quantity'] ?? 0));
            $days = max(0, (float) ($recipient['rental_days'] ?? 0));
            $rate = max(0, (float) ($recipient['daily_rate'] ?? 0));
            $transaction->serviceRecipients()->create([
                'name' => trim($recipient['name']),
                'npwp' => blank($recipient['npwp'] ?? null) ? null : trim($recipient['npwp']),
                'service_type' => blank($recipient['service_type'] ?? null) ? 'Jasa sewa harian' : trim($recipient['service_type']),
                'service_description' => blank($recipient['service_description'] ?? null) ? null : trim($recipient['service_description']),
                'quantity' => $quantity,
                'unit' => blank($recipient['unit'] ?? null) ? 'unit' : trim($recipient['unit']),
                'rental_days' => $days,
                'daily_rate' => $rate,
                'amount' => $quantity * $days * $rate,
                'usage_started_at' => $recipient['usage_started_at'] ?? null,
                'usage_completed_at' => $recipient['usage_completed_at'] ?? null,
                'receipt_number' => blank($recipient['receipt_number'] ?? null) ? null : trim($recipient['receipt_number']),
                'payment_reference' => blank($recipient['payment_reference'] ?? null) ? null : trim($recipient['payment_reference']),
                'agreement_number' => blank($recipient['agreement_number'] ?? null) ? null : trim($recipient['agreement_number']),
                'agreement_date' => $recipient['agreement_date'] ?? null,
                'is_receipt_recipient' => (bool) ($recipient['is_receipt_recipient'] ?? false),
                'notes' => blank($recipient['notes'] ?? null) ? null : trim($recipient['notes']),
                'sort_order' => $sortOrder,
            ]);
        }
    }
}
