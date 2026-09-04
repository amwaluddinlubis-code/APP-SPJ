<?php

namespace App\UseCases\Spj;

use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Services\FiscalPeriodWorkflowService;
use App\Services\OperationalAuditService;
use App\Services\SpjTransactionDetailsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SpjPackageUseCase
{
    public function prepare(Request $request, string $transactionId): RedirectResponse
    {
        $transaction = Transaction::query()->with('spjPackage')->withCount('items')->find($transactionId);
        if (! $transaction || $transaction->fiscal_year_id !== (int) session('active_fiscal_year_id') || (int) $transaction->fund_source_id !== (int) session('active_fund_source_id')) {
            return back()->with('error', 'Transaksi tidak ditemukan pada tahun anggaran aktif.');
        }
        if ($transaction->items_count < 1) {
            return back()->with('error', 'Paket belum dapat dibuat karena transaksi belum memiliki rincian barang/jasa.');
        }
        if ($transaction->spjPackage && ! $transaction->spjPackage->isEditable()) {
            return back()->with('error', 'Paket sudah bernomor atau final. Batalkan penomoran dan buka paket terlebih dahulu sebelum memperbaiki data.');
        }

        $submittedCategory = strtoupper((string) $request->input('spj_category'));
        if (! in_array($submittedCategory, ['PEMELIHARAAN', 'UPAH', 'HONOR_PEGAWAI', 'JASA_HONORARIUM'], true)) {
            $request->request->remove('workers');
        }
        if (! in_array($submittedCategory, ['SPPD', 'PERJALANAN_DINAS'], true)) {
            $request->request->remove('travels');
        }
        if ($submittedCategory !== 'KONSUMSI') {
            $request->request->remove('participants');
        }

        $data = $request->validate($this->prepareRules($request, $transaction));
        $data['spj_category'] = $this->canonicalCategory($data['spj_category'] ?? null);
        $this->validateTaxMatchesBku($transaction, $data);

        if (filled($data['spj_category'] ?? null)) {
            $transaction->update(collect($data)->only([
                'spj_category', 'payment_description', 'payment_reference', 'payment_method',
                'receipt_recipient_name', 'signatory_name', 'signatory_role', 'vendor_name', 'vendor_owner', 'vendor_npwp',
                'siplah_order_number', 'invoice_number', 'invoice_date', 'invoice_status',
                'ppn_rate', 'pph21_rate', 'pph22_rate', 'pph23_rate', 'pph4_rate', 'sspd_rate',
            ])->all());
            $this->updateTaxAmounts($transaction);
            $transaction->load('items');
            app(SpjTransactionDetailsService::class)->synchronize($transaction, $data);
            $transaction->refresh();
        }

        if (blank($transaction->spj_category)) {
            return back()->with('error', 'Pilih kategori SPJ pada halaman transaksi terlebih dahulu.');
        }

        $transaction->load('items');
        if ($transaction->items->contains(fn ($item) => blank($item->item_description))) {
            return back()->with('error', 'Lengkapi uraian barang/jasa untuk SPJ pada setiap detail transaksi terlebih dahulu.');
        }

        $package = SpjPackage::firstOrCreate(
            ['transaction_id' => $transaction->id],
            ['quarter_code' => $this->quarter($transaction), 'semester_code' => $this->semester($transaction), 'status' => 'DRAFT']
        );
        $quarter = (int) ceil((int) $transaction->transaction_date->format('n') / 3);
        if (app(FiscalPeriodWorkflowService::class)->isLateEntry($transaction->fiscal_year_id, $quarter)) {
            $package->forceFill(['is_late_entry' => true])->save();
        }
        app(OperationalAuditService::class)->record($transaction->fiscal_year_id, 'SPJ_PACKAGE', $package->id, 'SIAPKAN_PAKET', 'Paket SPJ disiapkan untuk bukti '.$transaction->no_bukti);

        return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $package->id])->with('success', 'Paket dokumen SPJ telah disiapkan.');
    }

    public function updateDetails(string $packageId, Request $request): RedirectResponse
    {
        $package = SpjPackage::query()->with('transaction')->find($packageId);
        if (! $package || $package->transaction->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Paket dokumen tidak ditemukan pada tahun anggaran aktif.');
        }
        if (! $package->isEditable()) {
            return back()->with('error', 'Paket sudah bernomor atau final. Buka kembali paket melalui administrator sebelum mengubah data.');
        }

        $data = $request->validate($this->updateRules($request, $package));
        $this->validateTaxMatchesBku($package->transaction, $data);
        $workers = $data['workers'] ?? [];

        $package->transaction->fill(collect($data)->only([
            'payment_description', 'payment_reference', 'payment_method',
            'receipt_recipient_name', 'signatory_name', 'signatory_role', 'vendor_name', 'vendor_owner', 'vendor_npwp',
            'siplah_order_number', 'invoice_number', 'invoice_date', 'invoice_status',
            'ppn_rate', 'pph21_rate', 'pph22_rate', 'pph23_rate', 'pph4_rate', 'sspd_rate',
        ])->all())->save();
        $this->updateTaxAmounts($package->transaction);
        $package->transaction->load('items');
        app(SpjTransactionDetailsService::class)->synchronize($package->transaction, $data);

        $workOrder = $package->transaction->workOrder;
        $workOrder?->workers()->delete();
        $receiptRecipient = null;
        foreach ($workers as $sortOrder => $worker) {
            if (! $workOrder || blank($worker['name'] ?? null)) {
                continue;
            }
            $days = (float) ($worker['work_days'] ?? 0);
            $rate = (float) ($worker['daily_rate'] ?? 0);
            $isRecipient = (bool) ($worker['is_receipt_recipient'] ?? false);
            $workOrder->workers()->create([
                'name' => $worker['name'],
                'job_description' => $worker['job_description'] ?? null,
                'work_days' => $days,
                'daily_rate' => $rate,
                'amount' => $days * $rate,
                'is_receipt_recipient' => $isRecipient,
                'notes' => $worker['notes'] ?? null,
                'sort_order' => $sortOrder,
            ]);
            if ($isRecipient && ! $receiptRecipient) {
                $receiptRecipient = $worker['name'];
            }
        }
        if ($receiptRecipient) {
            $package->transaction->forceFill([
                'receipt_recipient_name' => $receiptRecipient,
                'signatory_name' => $package->transaction->signatory_name ?: $receiptRecipient,
            ])->save();
        }

        app(OperationalAuditService::class)->record($package->transaction->fiscal_year_id, 'SPJ_PACKAGE', $package->id, 'PERBARUI_ISIAN', 'Isian manual paket '.$package->transaction->no_bukti.' diperbarui.');

        return back()->with('success', 'Isian manual paket SPJ berhasil disimpan.');
    }

    private function prepareRules(Request $request, Transaction $transaction): array
    {
        $maximumDocumentDate = $transaction->transaction_date->format('Y-m-d');

        return [
            'spj_category' => ['required', 'in:BARANG,KONSUMSI,PEMELIHARAAN,JASA_LAINNYA,SPPD,HONOR_PEGAWAI,BELANJA_MODAL,PERJALANAN_DINAS,JASA_HONORARIUM,UPAH,LAINNYA'],
            'payment_description' => ['required', 'string', 'max:4000'],
            'payment_reference' => ['nullable', 'string', 'max:160'],
            'payment_method' => ['required', 'in:transfer_bank,siplah,tunai'],
            'receipt_recipient_name' => ['required', 'string', 'max:255'],
            'order_number' => ['nullable', 'string', 'max:80'],
            'order_date' => ['required_if:spj_category,KONSUMSI', 'nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'bap_number' => ['nullable', 'string', 'max:80'],
            'bap_date' => ['required_if:spj_category,KONSUMSI', 'nullable', 'date', 'after_or_equal:order_date', 'before_or_equal:'.$maximumDocumentDate],
            'bast_number' => ['nullable', 'string', 'max:80'],
            'bast_date' => ['required_if:spj_category,KONSUMSI', 'nullable', 'date', 'after_or_equal:bap_date', 'before_or_equal:'.$maximumDocumentDate],
            'invoice_number' => ['nullable', 'string', 'max:80'],
            'invoice_date' => ['nullable', 'date', 'after_or_equal:bast_date', 'before_or_equal:'.$maximumDocumentDate],
            'invoice_status' => ['nullable', 'string', 'max:30'],
            'work_description' => ['nullable', 'string', 'max:4000'],
            'work_location' => ['nullable', 'string', 'max:180'],
            'work_started_at' => ['nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'work_completed_at' => ['nullable', 'date', 'after_or_equal:work_started_at', 'before_or_equal:'.$maximumDocumentDate],
            'spk_number' => ['nullable', 'string', 'max:80'],
            'spk_date' => ['nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'rab_number' => ['nullable', 'string', 'max:80'],
            'rab_date' => ['nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'signatory_name' => ['nullable', 'string', 'max:180'],
            'signatory_role' => ['nullable', 'string', 'max:80'],
            'vendor_name' => ['nullable', 'string', 'max:180'],
            'vendor_owner' => ['nullable', 'string', 'max:180'],
            'vendor_npwp' => ['nullable', 'string', 'max:32'],
            'siplah_order_number' => ['nullable', 'string', 'max:100'],
            'ppn_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pph21_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pph22_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pph23_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pph4_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sspd_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'event_name' => ['required_if:spj_category,KONSUMSI', 'nullable', 'string', 'max:180'],
            'event_location' => ['required_if:spj_category,KONSUMSI', 'nullable', 'string', 'max:180'],
            'event_date' => ['required_if:spj_category,KONSUMSI', 'nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'participant_count' => ['required_if:spj_category,KONSUMSI', 'nullable', 'integer', 'min:1', function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                if (strtoupper((string) $request->input('spj_category')) !== 'KONSUMSI') {
                    return;
                }
                $portions = collect($request->input('participants', []))->sum(fn (array $row): int => (int) ($row['portions'] ?? 0));
                if ((int) $value !== $portions) {
                    $fail("Jumlah peserta harus sama dengan total porsi ({$portions}).");
                }
            }],
            'participants' => ['nullable', 'array'],
            'participants.*.name' => ['nullable', 'string', 'max:180'],
            'participants.*.position' => ['nullable', 'string', 'max:180'],
            'participants.*.portions' => ['nullable', 'numeric', 'min:1', function (string $attribute, mixed $value, \Closure $fail): void {
                if (abs((float) $value - round((float) $value)) > 0.000001) {
                    $fail('Jumlah porsi harus berupa bilangan bulat tanpa koma atau desimal.');
                }
            }],
            'workers' => ['required_if:spj_category,HONOR_PEGAWAI', 'nullable', 'array', 'min:1', function (string $attribute, mixed $value, \Closure $fail) use ($request, $transaction): void {
                $category = strtoupper((string) $request->input('spj_category'));
                if (! in_array($category, ['HONOR_PEGAWAI', 'JASA_HONORARIUM'], true) || ! is_array($value)) {
                    return;
                }
                $detailTotal = collect($value)->sum(fn (array $recipient): float => (float) ($recipient['work_days'] ?? 0) * (float) ($recipient['daily_rate'] ?? 0));
                $transactionTotal = (float) $transaction->gross_amount;
                if (abs($detailTotal - $transactionTotal) > 0.01) {
                    $fail(sprintf(
                        'Total rincian honor Rp %s tidak sama dengan nilai bruto transaksi Rp %s. Periksa jumlah penerima, Bulan/Kali, atau Tarif.',
                        number_format($detailTotal, 0, ',', '.'),
                        number_format($transactionTotal, 0, ',', '.'),
                    ));
                }
            }],
            'workers.*.name' => ['required_if:spj_category,HONOR_PEGAWAI', 'nullable', 'string', 'max:180'],
            'workers.*.job_description' => ['required_if:spj_category,HONOR_PEGAWAI', 'nullable', 'string', 'max:255'],
            'workers.*.work_days' => ['required_if:spj_category,HONOR_PEGAWAI', 'nullable', 'numeric', 'min:1', function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                if ($request->input('spj_category') === 'HONOR_PEGAWAI' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $fail('Bulan/Kali harus berupa bilangan bulat tanpa koma atau desimal.');
                }
            }],
            'workers.*.daily_rate' => ['required_if:spj_category,HONOR_PEGAWAI', 'nullable', 'numeric', 'min:1'],
            'workers.*.is_receipt_recipient' => ['nullable', 'boolean'],
            'workers.*.notes' => ['nullable', 'string', 'max:2000'],
            'travels' => ['nullable', 'array'],
            'travels.*.traveler_name' => ['required_with:travels', 'string', 'max:180'],
            'travels.*.destination' => ['nullable', 'string', 'max:180'],
            'travels.*.purpose' => ['nullable', 'string', 'max:4000'],
            'travels.*.departure_date' => ['nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'travels.*.assignment_letter_number' => ['nullable', 'string', 'max:255'],
            'travels.*.assignment_letter_date' => ['nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'travels.*.return_date' => ['nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'travels.*.transport_mode' => ['nullable', 'string', 'max:80'],
            'travels.*.amount' => ['nullable', 'numeric', 'min:0'],
            'travels.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function updateRules(Request $request, SpjPackage $package): array
    {
        $maximumDocumentDate = $package->transaction->transaction_date->format('Y-m-d');

        return [
            'payment_description' => ['nullable', 'string', 'max:4000'],
            'payment_reference' => ['nullable', 'string', 'max:160'],
            'payment_method' => ['nullable', 'in:transfer_bank,siplah,tunai'],
            'receipt_recipient_name' => ['nullable', 'string', 'max:255'],
            'spj_category' => ['nullable', 'string', 'max:40'],
            'invoice_number' => ['nullable', 'string', 'max:80'],
            'invoice_date' => ['nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'invoice_status' => ['nullable', 'string', 'max:30'],
            'siplah_order_number' => ['nullable', 'string', 'max:100'],
            'work_description' => ['nullable', 'string', 'max:4000'],
            'work_location' => ['nullable', 'string', 'max:180'],
            'work_started_at' => ['nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'work_completed_at' => ['nullable', 'date', 'after_or_equal:work_started_at', 'before_or_equal:'.$maximumDocumentDate],
            'spk_number' => ['nullable', 'string', 'max:80'],
            'spk_date' => ['nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'rab_number' => ['nullable', 'string', 'max:80'],
            'rab_date' => ['nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'signatory_name' => ['nullable', 'string', 'max:180'],
            'signatory_role' => ['nullable', 'string', 'max:80'],
            'event_name' => ['required_if:spj_category,KONSUMSI', 'nullable', 'string', 'max:180'],
            'event_location' => ['required_if:spj_category,KONSUMSI', 'nullable', 'string', 'max:180'],
            'event_date' => ['required_if:spj_category,KONSUMSI', 'nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'participant_count' => ['required_if:spj_category,KONSUMSI', 'nullable', 'integer', 'min:1', function (string $attribute, mixed $value, \Closure $fail) use ($request, $package): void {
                $category = strtoupper((string) ($request->input('spj_category') ?: $package->transaction->spj_category));
                if ($category !== 'KONSUMSI') {
                    return;
                }
                $portions = collect($request->input('participants', []))->sum(fn (array $row): int => (int) ($row['portions'] ?? 0));
                if ((int) $value !== $portions) {
                    $fail("Jumlah peserta harus sama dengan total porsi ({$portions}).");
                }
            }],
            'participants' => ['nullable', 'array'],
            'participants.*.name' => ['nullable', 'string', 'max:180'],
            'participants.*.position' => ['nullable', 'string', 'max:180'],
            'participants.*.portions' => ['nullable', 'numeric', 'min:1', function (string $attribute, mixed $value, \Closure $fail): void {
                if (abs((float) $value - round((float) $value)) > 0.000001) {
                    $fail('Jumlah porsi harus berupa bilangan bulat tanpa koma atau desimal.');
                }
            }],
            'workers' => ['nullable', 'array', function (string $attribute, mixed $value, \Closure $fail) use ($request, $package): void {
                $category = strtoupper((string) ($request->input('spj_category') ?: $package->transaction->spj_category));
                if (! in_array($category, ['HONOR_PEGAWAI', 'JASA_HONORARIUM'], true) || ! is_array($value)) {
                    return;
                }
                $detailTotal = collect($value)->sum(fn (array $recipient): float => (float) ($recipient['work_days'] ?? 0) * (float) ($recipient['daily_rate'] ?? 0));
                $transactionTotal = (float) $package->transaction->gross_amount;
                if (abs($detailTotal - $transactionTotal) > 0.01) {
                    $fail(sprintf(
                        'Total rincian honor Rp %s tidak sama dengan nilai bruto transaksi Rp %s.',
                        number_format($detailTotal, 0, ',', '.'),
                        number_format($transactionTotal, 0, ',', '.'),
                    ));
                }
            }],
            'workers.*.name' => ['nullable', 'string', 'max:180'],
            'workers.*.job_description' => ['nullable', 'string', 'max:255'],
            'workers.*.work_days' => ['nullable', 'numeric', 'min:0'],
            'workers.*.daily_rate' => ['nullable', 'numeric', 'min:0'],
            'workers.*.is_receipt_recipient' => ['nullable', 'boolean'],
            'workers.*.notes' => ['nullable', 'string', 'max:2000'],
            'travels' => ['nullable', 'array'],
            'travels.*.traveler_name' => ['required_with:travels', 'string', 'max:180'],
            'travels.*.destination' => ['nullable', 'string', 'max:180'],
            'travels.*.purpose' => ['nullable', 'string', 'max:4000'],
            'travels.*.departure_date' => ['nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'travels.*.assignment_letter_number' => ['nullable', 'string', 'max:255'],
            'travels.*.assignment_letter_date' => ['nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'travels.*.return_date' => ['nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'travels.*.transport_mode' => ['nullable', 'string', 'max:80'],
            'travels.*.amount' => ['nullable', 'numeric', 'min:0'],
            'travels.*.notes' => ['nullable', 'string', 'max:2000'],
            'vendor_name' => ['nullable', 'string', 'max:180'],
            'vendor_owner' => ['nullable', 'string', 'max:180'],
            'vendor_npwp' => ['nullable', 'string', 'max:32'],
            'ppn_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pph21_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pph22_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pph23_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pph4_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sspd_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    private function canonicalCategory(?string $category): ?string
    {
        return [
            'BELANJA_MODAL' => 'BARANG',
            'PERJALANAN_DINAS' => 'SPPD',
            'JASA_HONORARIUM' => 'HONOR_PEGAWAI',
            'UPAH' => 'PEMELIHARAAN',
            'LAINNYA' => 'JASA_LAINNYA',
        ][$category ?? ''] ?? $category;
    }

    private function updateTaxAmounts(Transaction $transaction): void
    {
        $rates = [
            'ppn' => (float) ($transaction->ppn_rate ?? 0),
            'pph21' => (float) ($transaction->pph21_rate ?? 0),
            'pph22' => (float) ($transaction->pph22_rate ?? 0),
            'pph23' => (float) ($transaction->pph23_rate ?? 0),
            'pph4' => (float) ($transaction->pph4_rate ?? 0),
            'sspd' => (float) ($transaction->sspd_rate ?? 0),
        ];
        if (array_sum($rates) <= 0) {
            return;
        }
        $gross = (float) $transaction->gross_amount;
        $amounts = array_map(fn (float $rate): float => round($gross * $rate / 100, 2), $rates);
        $transaction->forceFill([
            ...$amounts,
            'tax_total' => array_sum($amounts),
            'net_amount' => $gross - array_sum($amounts),
        ])->save();
    }

    private function validateTaxMatchesBku(Transaction $transaction, array $data): void
    {
        $category = strtoupper((string) ($data['spj_category'] ?? $transaction->spj_category));
        if ($category !== 'KONSUMSI') {
            return;
        }

        $amountByRate = [
            'ppn_rate' => 'ppn',
            'pph21_rate' => 'pph21',
            'pph22_rate' => 'pph22',
            'pph23_rate' => 'pph23',
            'pph4_rate' => 'pph4',
            'sspd_rate' => 'sspd',
        ];
        $gross = (float) $transaction->gross_amount;
        $rateTotal = 0.0;
        foreach ($amountByRate as $rateField => $amountField) {
            if (array_key_exists($rateField, $data)) {
                $rateTotal += (float) $data[$rateField];

                continue;
            }
            if ($transaction->{$rateField} !== null) {
                $rateTotal += (float) $transaction->{$rateField};

                continue;
            }
            $rateTotal += $gross > 0 ? (float) $transaction->{$amountField} / $gross * 100 : 0;
        }
        $calculatedTax = round($gross * $rateTotal / 100, 2);
        $bkuTax = (float) $transaction->tax_total;
        if (abs($calculatedTax - $bkuTax) > 0.01) {
            throw ValidationException::withMessages([
                'sspd_rate' => sprintf(
                    'Total pajak hasil tarif Rp %s tidak sama dengan pajak BKU Rp %s. Sesuaikan tarif berdasarkan rincian BKU.',
                    number_format($calculatedTax, 0, ',', '.'),
                    number_format($bkuTax, 0, ',', '.')
                ),
            ]);
        }
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
