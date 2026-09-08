<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'school';

    public function up(): void
    {
        DB::connection('school')->transaction(function (): void {
            $this->normalizeLegacyPrintedPackages();
            $this->backfillServiceRecipientTaxAndNet();
        });
    }

    public function down(): void
    {
        // Data normalization is intentionally irreversible. Rolling back schema
        // must not recreate legacy lifecycle states or erase derived tax/net data.
    }

    private function normalizeLegacyPrintedPackages(): void
    {
        if (! Schema::connection('school')->hasTable('spj_packages')) {
            return;
        }

        DB::connection('school')
            ->table('spj_packages')
            ->where('status', 'DICETAK')
            ->whereNotNull('document_number')
            ->where('document_number', '<>', '')
            ->whereNotNull('numbered_at')
            ->update(['status' => 'NUMBERED']);
    }

    private function backfillServiceRecipientTaxAndNet(): void
    {
        if (! Schema::connection('school')->hasTable('spj_service_recipients')
            || ! Schema::connection('school')->hasTable('transactions')
            || ! Schema::connection('school')->hasColumn('spj_service_recipients', 'tax_amount')
            || ! Schema::connection('school')->hasColumn('spj_service_recipients', 'net_amount')) {
            return;
        }

        $transactionIds = DB::connection('school')
            ->table('spj_service_recipients')
            ->select('transaction_id')
            ->distinct()
            ->pluck('transaction_id');

        foreach ($transactionIds as $transactionId) {
            $transaction = DB::connection('school')
                ->table('transactions')
                ->where('id', $transactionId)
                ->first(['id', 'spj_category', 'tax_total', 'net_amount']);

            if (! $transaction || strtoupper((string) $transaction->spj_category) !== 'JASA_LAINNYA') {
                continue;
            }

            $recipients = DB::connection('school')
                ->table('spj_service_recipients')
                ->where('transaction_id', $transactionId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'amount']);

            if ($recipients->isEmpty()) {
                continue;
            }

            $gross = (float) $recipients->sum(fn (object $recipient): float => (float) $recipient->amount);
            $sourceTax = (float) $transaction->tax_total;
            $sourceNet = (float) $transaction->net_amount;
            $allocatedTax = 0.0;
            $allocatedNet = 0.0;
            $lastIndex = $recipients->count() - 1;

            foreach ($recipients as $index => $recipient) {
                $ratio = $gross > 0 ? (float) $recipient->amount / $gross : 0.0;
                $tax = $index === $lastIndex
                    ? round($sourceTax - $allocatedTax, 2)
                    : round($sourceTax * $ratio, 2);
                $net = $index === $lastIndex
                    ? round($sourceNet - $allocatedNet, 2)
                    : round($sourceNet * $ratio, 2);

                $allocatedTax += $tax;
                $allocatedNet += $net;

                DB::connection('school')
                    ->table('spj_service_recipients')
                    ->where('id', $recipient->id)
                    ->update([
                        'tax_amount' => $tax,
                        'net_amount' => $net,
                    ]);
            }
        }
    }
};
