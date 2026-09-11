<?php

namespace App\Services;

use App\Models\DocumentNumberFormat;
use App\Models\SpjDocument;
use App\Models\SpjPackage;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SpjDocumentNumberService
{
    public function __construct(private readonly SpjNumberingPolicyService $policy) {}

    /**
     * Terbitkan nomor domain paket berdasarkan tanggal peristiwanya.
     * Nomor yang sudah tersedia tidak pernah ditimpa.
     *
     * @param  array<int, string>|null  $onlyDocumentTypes
     * @return array{created:int,skipped:int,documents:Collection<int, SpjDocument>}
     */
    public function assignAutomaticNumbers(SpjPackage $package, string $schoolCode, ?string $npsn = null, ?array $onlyDocumentTypes = null): array
    {
        $package->load('transaction');
        $package->transaction?->load(['goods', 'workOrder', 'travels']);
        $transaction = $package->transaction;
        $documents = collect();
        $created = 0;
        $skipped = 0;
        $selectedTypes = $onlyDocumentTypes === null
            ? null
            : collect($onlyDocumentTypes)->map(fn (string $type): string => strtoupper(trim($type)))->filter()->values();
        $shouldAssign = fn (string $type): bool => ($selectedTypes === null || $selectedTypes->contains($type))
            && $this->policy->isAutomaticDocumentEligible($transaction, $type);

        $assign = function (string $type, CarbonInterface $date, string $scopeKey = 'MAIN') use ($package, $schoolCode, $npsn, $documents, &$created, &$skipped): SpjDocument {
            $alreadyNumbered = $package->documents()
                ->where(['document_type' => $type, 'scope_key' => $scopeKey])
                ->where('status', '!=', 'CANCELLED')
                ->whereNotNull('document_number')
                ->exists();
            $document = $this->assign($package, $type, $date, $schoolCode, $scopeKey, npsn: $npsn);
            $documents->push($document);
            $alreadyNumbered ? $skipped++ : $created++;

            return $document;
        };

        if ($shouldAssign('SPJ') && $transaction->transaction_date) {
            $assign('SPJ', Carbon::parse($transaction->transaction_date));
        }

        foreach ([
            'PESANAN' => ['date' => 'order_date', 'number' => 'order_number'],
            'BAP' => ['date' => 'bap_date', 'number' => 'bap_number'],
            'BAST' => ['date' => 'bast_date', 'number' => 'bast_number'],
        ] as $type => $mapping) {
            if (! $shouldAssign($type)) {
                continue;
            }
            $date = $transaction->goods->pluck($mapping['date'])->filter()->sort()->first();
            if (! $date) {
                continue;
            }
            $existing = $transaction->goods->pluck($mapping['number'])->filter()->first();
            if ($existing) {
                $transaction->goods()->whereNull($mapping['number'])->update([$mapping['number'] => $existing]);
                $skipped++;
                continue;
            }
            $document = $assign($type, Carbon::parse($date));
            $transaction->goods()->whereNull($mapping['number'])->update([$mapping['number'] => $document->document_number]);
        }

        $workOrder = $transaction->workOrder;
        foreach ([
            'SPK' => ['date' => 'spk_date', 'number' => 'spk_number'],
            'RAB' => ['date' => 'rab_date', 'number' => 'rab_number'],
        ] as $type => $mapping) {
            if (! $shouldAssign($type) || ! $workOrder?->{$mapping['date']}) {
                continue;
            }
            if (filled($workOrder->{$mapping['number']})) {
                $skipped++;
                continue;
            }
            $document = $assign($type, Carbon::parse($workOrder->{$mapping['date']}));
            $workOrder->forceFill([$mapping['number'] => $document->document_number])->save();
        }

        if ($shouldAssign('SURAT_TUGAS_PERJALANAN_DINAS')) {
            $travels = $transaction->travels
                ->sortBy(fn ($travel): string => Carbon::parse($travel->assignment_letter_date ?: $travel->departure_date ?: '9999-12-31')->format('Y-m-d').'-'.str_pad((string) ($travel->sort_order ?? 0), 8, '0', STR_PAD_LEFT).'-'.str_pad((string) $travel->id, 12, '0', STR_PAD_LEFT));
            foreach ($travels as $travel) {
                $eventDate = $travel->assignment_letter_date ?: $travel->departure_date;
                if (! $eventDate) {
                    continue;
                }
                if (filled($travel->assignment_letter_number)) {
                    $skipped++;
                    continue;
                }
                $document = $assign('SURAT_TUGAS_PERJALANAN_DINAS', Carbon::parse($eventDate), 'TRAVEL-'.$travel->id);
                $travel->forceFill([
                    'assignment_letter_number' => $document->document_number,
                    'assignment_letter_date' => $travel->assignment_letter_date ?: $eventDate,
                ])->save();
            }
        }

        return compact('created', 'skipped', 'documents');
    }

    public function assign(
        SpjPackage $package,
        string $documentType,
        CarbonInterface $documentDate,
        string $schoolCode,
        string $scopeKey = 'MAIN',
        ?int $templateId = null,
        ?string $npsn = null,
    ): SpjDocument {
        $documentType = strtoupper(trim($documentType));
        $documentDate = $this->canonicalDocumentDate($package, $documentType, $documentDate);

        return DB::connection('school')->transaction(function () use ($package, $documentType, $documentDate, $schoolCode, $scopeKey, $templateId, $npsn): SpjDocument {
            $identity = [
                'spj_package_id' => $package->id,
                'document_type' => $documentType,
                'scope_key' => $scopeKey,
            ];
            $activeDocument = SpjDocument::query()
                ->where($identity)
                ->where('status', '!=', 'CANCELLED')
                ->latest('id')
                ->first();
            if ($activeDocument?->document_number) {
                return $activeDocument;
            }

            // CANCELLED adalah pembatalan bisnis individual dan nomornya tetap
            // menjadi histori permanen. Identity baru harus mendapat sequence
            // berikutnya; hanya rollback numbering yang boleh menghapus record
            // numbering dan menurunkan sequence.
            $document = $activeDocument ?? new SpjDocument($identity);
            $yearId = (int) $package->transaction->fiscal_year_id;
            $fundSourceId = $package->transaction->fund_source_id === null ? null : (int) $package->transaction->fund_source_id;
            $format = $this->policy->formatFor($yearId, $documentType);
            $periodKey = $this->periodKey($format->reset_period, $documentDate);
            $sequenceKey = [
                'fiscal_year_id' => $yearId,
                'fund_source_id' => $fundSourceId,
                'format_name' => $documentType,
                'period_key' => $periodKey,
            ];
            $sequence = DB::connection('school')->table('document_number_sequences')
                ->where($sequenceKey)
                ->lockForUpdate()->first();
            $next = ((int) ($sequence->last_number ?? 0)) + 1;
            if ($sequence) {
                DB::connection('school')->table('document_number_sequences')->where('id', $sequence->id)
                    ->update(['last_number' => $next, 'updated_at' => now()]);
            } else {
                DB::connection('school')->table('document_number_sequences')->insert($sequenceKey + [
                    'last_number' => $next,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $number = $this->renderNumber($format, $documentType, $next, $documentDate, $schoolCode, $npsn);
            $document->fill([
                'document_template_id' => $templateId,
                'document_number' => $number,
                'sequence_number' => $next,
                'document_date' => $documentDate,
                'event_date' => $documentDate,
                'status' => 'NUMBERED',
                'is_late_entry' => (bool) $package->is_late_entry,
                'numbered_at' => now(),
                'replaces_document_id' => null,
                'cancelled_at' => null,
                'cancelled_by' => null,
                'cancellation_reason' => null,
            ])->save();

            if ($documentType === 'SPJ' && $scopeKey === 'MAIN') {
                $package->forceFill([
                    'document_number' => $number,
                    'status' => 'NUMBERED',
                    'numbered_at' => now(),
                    'cancelled_at' => null,
                    'cancelled_by' => null,
                    'cancellation_reason' => null,
                ])->save();
            }

            return $document;
        });
    }

    public function renderConfiguredNumber(
        DocumentNumberFormat $format,
        string $documentType,
        int $sequence,
        CarbonInterface $documentDate,
        string $schoolCode,
        ?string $npsn = null,
    ): string {
        return $this->renderNumber($format, strtoupper(trim($documentType)), $sequence, $documentDate, $schoolCode, $npsn);
    }

    private function canonicalDocumentDate(SpjPackage $package, string $documentType, CarbonInterface $fallback): CarbonInterface
    {
        $package->load('transaction');
        $package->transaction?->load(['goods', 'workOrder', 'travels']);
        $transaction = $package->transaction;
        $value = match ($documentType) {
            'SPJ' => $transaction->transaction_date,
            'ORDER', 'PESANAN', 'SURAT_PESANAN' => $transaction->goods->pluck('order_date')->filter()->sort()->first(),
            'BAP' => $transaction->goods->pluck('bap_date')->filter()->sort()->first(),
            'BAST', 'RECEIPT', 'PENERIMAAN' => $transaction->goods->pluck('bast_date')->filter()->sort()->first(),
            'SPK', 'WORK_ORDER' => $transaction->workOrder?->spk_date,
            'RAB' => $transaction->workOrder?->rab_date,
            'SURAT_TUGAS_PERJALANAN_DINAS' => $transaction->travels->pluck('assignment_letter_date')->filter()->sort()->first()
                ?: $transaction->travels->pluck('departure_date')->filter()->sort()->first(),
            'SPPD' => $transaction->travels->pluck('departure_date')->filter()->sort()->first(),
            default => null,
        };

        return filled($value) ? Carbon::parse($value) : $fallback;
    }

    private function periodKey(string $resetPeriod, CarbonInterface $date): string
    {
        return match (strtoupper($resetPeriod)) {
            'MONTH' => $date->format('Y-m'),
            'QUARTER' => $date->format('Y').'-Q'.(int) ceil((int) $date->format('n') / 3),
            'NONE' => 'ALL',
            default => $date->format('Y'),
        };
    }

    private function renderNumber(DocumentNumberFormat $format, string $documentType, int $sequence, CarbonInterface $documentDate, string $schoolCode, ?string $npsn): string
    {
        return strtr($format->format_pattern, [
            '{SEQ}' => str_pad((string) $sequence, $format->padding, '0', STR_PAD_LEFT),
            '{TYPE}' => $documentType,
            '{SCHOOL}' => $schoolCode,
            '{NPSN}' => $npsn ?: $schoolCode,
            '{YEAR}' => $documentDate->format('Y'),
            '{MONTH}' => $documentDate->format('m'),
            '{ROMAN_MONTH}' => $this->romanMonth((int) $documentDate->format('n')),
            '{TW}' => $this->quarterToken($documentDate),
        ]);
    }

    private function quarterToken(CarbonInterface $date): string
    {
        $quarter = (int) ceil((int) $date->format('n') / 3);

        return 'TW.'.[1 => 'I', 'II', 'III', 'IV'][$quarter];
    }

    private function romanMonth(int $month): string
    {
        return [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][$month];
    }
}
