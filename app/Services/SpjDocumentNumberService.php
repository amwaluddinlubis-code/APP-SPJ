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
    /**
     * Terbitkan seluruh nomor domain paket berdasarkan tanggal peristiwanya.
     * Nomor yang sudah tersedia tidak pernah ditimpa.
     *
     * @return array{created:int,skipped:int,documents:Collection<int, SpjDocument>}
     */
    public function assignAutomaticNumbers(SpjPackage $package, string $schoolCode, ?string $npsn = null): array
    {
        $package->loadMissing(['transaction.goods', 'transaction.workOrder', 'transaction.travels']);
        $transaction = $package->transaction;
        $documents = collect();
        $created = 0;
        $skipped = 0;

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

        if ($transaction->transaction_date) {
            $assign('SPJ', Carbon::parse($transaction->transaction_date));
        }

        foreach ([
            'PESANAN' => ['date' => 'order_date', 'number' => 'order_number'],
            'BAP' => ['date' => 'bap_date', 'number' => 'bap_number'],
            'BAST' => ['date' => 'bast_date', 'number' => 'bast_number'],
        ] as $type => $mapping) {
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
            if (! $workOrder?->{$mapping['date']}) {
                continue;
            }
            if (filled($workOrder->{$mapping['number']})) {
                $skipped++;

                continue;
            }
            $document = $assign($type, Carbon::parse($workOrder->{$mapping['date']}));
            $workOrder->forceFill([$mapping['number'] => $document->document_number])->save();
        }

        foreach ($transaction->travels as $travel) {
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
            $cancelledIdentityDocument = SpjDocument::query()
                ->where($identity)
                ->where('status', 'CANCELLED')
                ->latest('id')
                ->first();
            $document = $activeDocument ?? $cancelledIdentityDocument ?? new SpjDocument($identity);

            $yearId = $package->transaction->fiscal_year_id;
            $format = DocumentNumberFormat::query()->firstOrCreate(
                ['fiscal_year_id' => $yearId, 'document_type' => $documentType],
                $this->defaultFormat($documentType)
            );
            $periodKey = $this->periodKey($format->reset_period, $documentDate);
            $activeSequences = SpjDocument::query()
                ->where('document_type', $documentType)
                ->where('status', '!=', 'CANCELLED')
                ->whereNotNull('sequence_number')
                ->whereHas('package.transaction', fn ($query) => $query->where('fiscal_year_id', $yearId))
                ->get(['sequence_number', 'document_date'])
                ->filter(fn (SpjDocument $item): bool => $item->document_date
                    && $this->periodKey($format->reset_period, $item->document_date) === $periodKey)
                ->pluck('sequence_number')
                ->map(fn ($number): int => (int) $number)
                ->all();
            $cancelledDocument = SpjDocument::query()
                ->where('document_type', $documentType)
                ->where('status', 'CANCELLED')
                ->whereNotNull('document_number')
                ->whereNotNull('sequence_number')
                ->whereHas('package.transaction', fn ($query) => $query->where('fiscal_year_id', $yearId))
                ->orderBy('sequence_number')
                ->get()
                ->first(fn (SpjDocument $item): bool => $item->document_date
                    && $this->periodKey($format->reset_period, $item->document_date) === $periodKey
                    && ! in_array((int) $item->sequence_number, $activeSequences, true));
            if ($cancelledDocument) {
                $reusedSequence = (int) $cancelledDocument->sequence_number;
                $reusedNumber = $this->renderNumber($format, $documentType, $reusedSequence, $documentDate, $schoolCode, $npsn);
                $document->fill([
                    'document_template_id' => $templateId,
                    'document_number' => $reusedNumber,
                    'sequence_number' => $reusedSequence,
                    'document_date' => $documentDate,
                    'event_date' => $documentDate,
                    'status' => 'NUMBERED',
                    'is_late_entry' => (bool) $package->is_late_entry,
                    'numbered_at' => now(),
                    'replaces_document_id' => $document->exists && $document->is($cancelledDocument) ? null : $cancelledDocument->id,
                    'cancelled_at' => null,
                    'cancelled_by' => null,
                    'cancellation_reason' => null,
                ])->save();

                if ($documentType === 'SPJ' && $scopeKey === 'MAIN') {
                    $package->forceFill([
                        'document_number' => $document->document_number,
                        'status' => 'NUMBERED',
                        'numbered_at' => now(),
                        'cancelled_at' => null,
                        'cancelled_by' => null,
                        'cancellation_reason' => null,
                    ])->save();
                }

                return $document;
            }

            $sequence = DB::connection('school')->table('document_number_sequences')
                ->where(['fiscal_year_id' => $yearId, 'format_name' => $documentType, 'period_key' => $periodKey])
                ->lockForUpdate()->first();
            $next = ((int) ($sequence->last_number ?? 0)) + 1;
            if ($sequence) {
                DB::connection('school')->table('document_number_sequences')->where('id', $sequence->id)
                    ->update(['last_number' => $next, 'updated_at' => now()]);
            } else {
                DB::connection('school')->table('document_number_sequences')->insert([
                    'fiscal_year_id' => $yearId, 'format_name' => $documentType, 'period_key' => $periodKey,
                    'last_number' => $next, 'created_at' => now(), 'updated_at' => now(),
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
                $package->forceFill(['document_number' => $number, 'status' => 'NUMBERED', 'numbered_at' => now()])->save();
            }

            return $document;
        });
    }

    /** @return array<string, mixed> */
    private function defaultFormat(string $documentType): array
    {
        $prefix = match ($documentType) {
            'ORDER' => 'PESANAN',
            'RECEIPT' => 'KWITANSI',
            default => $documentType,
        };

        return [
            'format_pattern' => '{SEQ}/'.$prefix.'/{SCHOOL}/{ROMAN_MONTH}/{YEAR}',
            'reset_period' => 'YEAR',
            'padding' => 4,
            'is_active' => true,
        ];
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
        ]);
    }

    private function romanMonth(int $month): string
    {
        return [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][$month];
    }
}
