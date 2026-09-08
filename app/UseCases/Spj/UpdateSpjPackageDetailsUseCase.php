<?php

namespace App\UseCases\Spj;

use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Services\OperationalAuditService;
use App\Services\SpjTransactionDetailsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UpdateSpjPackageDetailsUseCase
{
    public function handle(string $packageId, Request $request): RedirectResponse
    {
        $package = SpjPackage::query()->with('transaction')->find($packageId);
        if (! $package
            || $package->transaction->fiscal_year_id !== (int) session('active_fiscal_year_id')
            || (int) $package->transaction->fund_source_id !== (int) session('active_fund_source_id')) {
            return redirect()
                ->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])
                ->with('error', 'Paket dokumen tidak ditemukan pada konteks sekolah, tahun anggaran, atau sumber dana aktif.');
        }

        if (! $package->isEditable()) {
            return back()->with('error', 'Paket sudah bernomor atau final. Buka kembali paket melalui administrator sebelum mengubah data.');
        }

        $data = $request->validate($this->rules($request, $package), $this->purchaseDateMessages());
        $data['spj_category'] = $this->canonicalCategory($data['spj_category'] ?? $package->transaction->spj_category);
        $primaryRecipient = $this->primaryRecipientName($data);

        if (in_array($data['spj_category'], ['KONSUMSI', 'PEMELIHARAAN', 'JASA_LAINNYA', 'SPPD', 'HONOR_PEGAWAI'], true)
            && $this->categoryHasRows($data['spj_category'], $data)
            && blank($primaryRecipient)) {
            throw ValidationException::withMessages([
                'primary_recipient_index' => 'Pilih satu Penerima Utama pada tabel rincian.',
            ]);
        }

        // Nilai pajak (PPN/PPh/SSPD), tax_total, dan net_amount adalah data sumber transaksi.
        // Paket SPJ tidak boleh mengubah atau menghitung ulang nilai tersebut.
        $package->transaction->fill(collect($data)->only([
            'spj_category',
            'payment_description',
            'payment_reference',
            'payment_method',
            'receipt_recipient_name',
            'vendor_name',
            'vendor_owner',
            'vendor_npwp',
            'siplah_order_number',
            'invoice_number',
            'invoice_date',
            'invoice_status',
        ])->all())->save();

        $package->transaction->load('items');
        app(SpjTransactionDetailsService::class)->synchronize($package->transaction, $data);

        $receiptRecipient = $primaryRecipient
            ?: $package->transaction->workOrder?->workers()->where('is_receipt_recipient', true)->value('name');
        if ($receiptRecipient) {
            $package->transaction->forceFill([
                'receipt_recipient_name' => $receiptRecipient,
            ])->save();
        }

        app(OperationalAuditService::class)->record(
            $package->transaction->fiscal_year_id,
            'SPJ_PACKAGE',
            $package->id,
            'PERBARUI_ISIAN',
            'Isian manual paket '.$package->transaction->no_bukti.' diperbarui tanpa mengubah pajak transaksi.',
        );

        return back()->with('success', 'Isian Paket SPJ berhasil disimpan. Nilai PPN, PPh, dan SSPD tetap mengikuti transaksi/BKU.');
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(Request $request, SpjPackage $package): array
    {
        $transaction = $package->transaction;
        $maximumDocumentDate = $transaction->transaction_date->format('Y-m-d');
        $isSiplah = (bool) $transaction->is_siplah
            || strtolower((string) $request->input('payment_method')) === 'siplah';

        return [
            'payment_description' => ['nullable', 'string', 'max:4000'],
            'payment_reference' => [$isSiplah ? 'required' : 'nullable', 'string', 'max:160'],
            'payment_method' => ['nullable', 'in:transfer_bank,siplah,tunai'],
            'receipt_recipient_name' => ['nullable', 'string', 'max:255'],
            'spj_category' => ['nullable', 'in:BARANG,KONSUMSI,PEMELIHARAAN,JASA_LAINNYA,SPPD,HONOR_PEGAWAI'],
            'primary_recipient_group' => ['nullable', 'in:workers,participants,travels,service_recipients'],
            'primary_recipient_index' => ['nullable', 'integer', 'min:0'],

            // Nomor internal otomatis tetap diterima untuk kompatibilitas caller lama,
            // tetapi form Paket SPJ tidak lagi merender input nomor tersebut.
            'order_number' => ['nullable', 'string', 'max:80'],
            'order_date' => ['nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'bap_number' => ['nullable', 'string', 'max:80'],
            'bap_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'bast_number' => ['nullable', 'string', 'max:80'],
            'bast_date' => ['nullable', 'date', 'after_or_equal:bap_date'],
            'invoice_number' => [$isSiplah ? 'required' : 'nullable', 'string', 'max:80'],
            'invoice_date' => [$isSiplah ? 'required' : 'nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
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

            ...$this->serviceRecipientRules($request, $transaction, $maximumDocumentDate),

            'event_name' => ['required_if:spj_category,KONSUMSI', 'nullable', 'string', 'max:180'],
            'event_location' => ['required_if:spj_category,KONSUMSI', 'nullable', 'string', 'max:180'],
            'event_date' => ['required_if:spj_category,KONSUMSI', 'nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'participant_count' => ['required_if:spj_category,KONSUMSI', 'nullable', 'integer', 'min:1', function (string $attribute, mixed $value, \Closure $fail) use ($request, $package): void {
                $category = strtoupper((string) ($request->input('spj_category') ?: $package->transaction->spj_category));
                if ($category !== 'KONSUMSI') {
                    return;
                }

                $portions = collect($request->input('participants', []))
                    ->sum(fn (array $row): int => (int) ($row['portions'] ?? 0));
                if ((int) $value !== $portions) {
                    $fail("Jumlah peserta harus sama dengan total porsi ({$portions}).");
                }
            }],
            'participants' => ['nullable', 'array'],
            'participants.*.name' => ['nullable', 'string', 'max:180'],
            'participants.*.position' => ['nullable', 'string', 'max:180'],
            'participants.*.portions' => ['nullable', 'integer', 'min:1'],

            'workers' => ['nullable', 'array', function (string $attribute, mixed $value, \Closure $fail) use ($request, $package): void {
                $category = strtoupper((string) ($request->input('spj_category') ?: $package->transaction->spj_category));
                if (! in_array($category, ['HONOR_PEGAWAI'], true) || ! is_array($value)) {
                    return;
                }

                $detailTotal = collect($value)
                    ->sum(fn (array $recipient): float => (float) ($recipient['work_days'] ?? 0) * (float) ($recipient['daily_rate'] ?? 0));
                $transactionTotal = (float) $package->transaction->gross_amount;
                if (abs($detailTotal - $transactionTotal) > 0.01) {
                    $fail(sprintf(
                        'Total rincian honor %s tidak sama dengan nilai bruto transaksi %s.',
                        number_format($detailTotal, 0, ',', '.'),
                        number_format($transactionTotal, 0, ',', '.'),
                    ));
                }
            }],
            'workers.*.name' => ['nullable', 'string', 'max:180'],
            'workers.*.job_description' => ['nullable', 'string', 'max:255'],
            'workers.*.work_days' => ['nullable', 'integer', 'min:0'],
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
        ];
    }

    /** @return array<string, array<int, mixed>> */
    private function serviceRecipientRules(Request $request, Transaction $transaction, string $maximumDocumentDate): array
    {
        return [
            'service_recipients' => ['nullable', 'array', function (string $attribute, mixed $value, \Closure $fail) use ($request, $transaction): void {
                $category = strtoupper((string) $request->input('spj_category'));
                if ($category !== 'JASA_LAINNYA' || ! is_array($value)) {
                    return;
                }

                $recipients = collect($value)->filter(fn (array $recipient): bool => filled($recipient['name'] ?? null));
                if ($recipients->isEmpty()) {
                    $fail('Jasa Lainnya memerlukan minimal satu penerima pembayaran.');
                    return;
                }

                $total = $recipients->sum(
                    fn (array $recipient): float => (float) ($recipient['quantity'] ?? 0)
                        * (float) ($recipient['rental_days'] ?? 0)
                        * (float) ($recipient['daily_rate'] ?? 0),
                );
                if (abs($total - (float) $transaction->gross_amount) > 0.01) {
                    $fail(sprintf(
                        'Total penerima jasa %s tidak sama dengan nilai bruto transaksi %s.',
                        number_format($total, 0, ',', '.'),
                        number_format((float) $transaction->gross_amount, 0, ',', '.'),
                    ));
                }
            }],
            'service_recipients.*.name' => ['nullable', 'string', 'max:180'],
            'service_recipients.*.npwp' => ['nullable', 'string', 'max:40'],
            'service_recipients.*.service_type' => ['nullable', 'string', 'max:180'],
            'service_recipients.*.service_description' => ['nullable', 'string', 'max:4000'],
            'service_recipients.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'service_recipients.*.unit' => ['nullable', 'string', 'max:40'],
            'service_recipients.*.rental_days' => ['nullable', 'integer', 'min:0'],
            'service_recipients.*.daily_rate' => ['nullable', 'numeric', 'min:0'],
            'service_recipients.*.usage_started_at' => ['nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'service_recipients.*.usage_completed_at' => ['nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'service_recipients.*.receipt_number' => ['nullable', 'string', 'max:100'],
            'service_recipients.*.payment_reference' => ['nullable', 'string', 'max:160'],
            'service_recipients.*.agreement_number' => ['nullable', 'string', 'max:100'],
            'service_recipients.*.agreement_date' => ['nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'service_recipients.*.is_receipt_recipient' => ['nullable', 'boolean'],
            'service_recipients.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @param array<string, mixed> $data */
    private function primaryRecipientName(array $data): ?string
    {
        $group = $data['primary_recipient_group'] ?? null;
        $index = isset($data['primary_recipient_index']) ? (int) $data['primary_recipient_index'] : null;
        if (! $group || $index === null) {
            return null;
        }

        $row = $data[$group][$index] ?? null;
        if (! is_array($row)) {
            return null;
        }

        $name = match ($group) {
            'travels' => $row['traveler_name'] ?? null,
            default => $row['name'] ?? null,
        };

        return filled($name) ? trim((string) $name) : null;
    }

    /** @param array<string, mixed> $data */
    private function categoryHasRows(string $category, array $data): bool
    {
        $group = match ($category) {
            'KONSUMSI' => 'participants',
            'PEMELIHARAAN', 'HONOR_PEGAWAI' => 'workers',
            'SPPD' => 'travels',
            'JASA_LAINNYA' => 'service_recipients',
            default => null,
        };

        if (! $group) {
            return false;
        }

        return collect($data[$group] ?? [])->contains(function ($row) use ($group): bool {
            if (! is_array($row)) {
                return false;
            }
            return filled($group === 'travels' ? ($row['traveler_name'] ?? null) : ($row['name'] ?? null));
        });
    }

    private function purchaseDateMessages(): array
    {
        return [
            'order_date.before_or_equal' => 'Tanggal Pesanan harus lebih kecil atau sama dengan Tanggal Transaksi.',
            'bap_date.after_or_equal' => 'Tanggal BAP harus lebih besar atau sama dengan Tanggal Pesanan.',
            'bast_date.after_or_equal' => 'Tanggal BAST harus lebih besar atau sama dengan Tanggal BAP.',
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
}
