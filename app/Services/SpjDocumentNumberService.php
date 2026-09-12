<?php

namespace App\Services;

use App\Models\DocumentNumberFormat;
use App\Models\SpjDocument;
use App\Models\SpjPackage;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SpjDocumentNumberService
{
    public function __construct(private readonly SpjNumberingPolicyService $policy) {}

    /**
     * Terbitkan nomor domain paket berdasarkan registry canonical.
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
            : collect($onlyDocumentTypes)
                ->map(fn (string $type): ?string => $this->policy->canonicalAutomaticDocumentType($type))
                ->filter()
                ->unique()
                ->values();

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

        foreach ($this->policy->automaticDocumentTypes() as $type) {
            if (($selectedTypes !== null && ! $selectedTypes->contains($type))
                || ! $this->policy->isAutomaticDocumentEligible($transaction, $type)) {
                continue;
            }

            $definition = $this->policy->numberingDefinition($type);
            if (! $definition) {
                continue;
            }
            $target = $definition['number_target'];
            $targetField = $target['field'];
            $eventDate = $this->policy->documentEventDateValue($transaction, $type);

            if ($target['relation'] === 'package') {
                if ($eventDate) {
                    $assign($type, Carbon::parse($eventDate));
                }

                continue;
            }

            if ($target['relation'] === 'goods') {
                if (! $eventDate || ! $targetField) {
                    continue;
                }
                $existing = $transaction->goods->pluck($targetField)->filter()->first();
                if ($existing) {
                    $transaction->goods()->whereNull($targetField)->update([$targetField => $existing]);
                    $skipped++;

                    continue;
                }
                $document = $assign($type, Carbon::parse($eventDate));
                $transaction->goods()->whereNull($targetField)->update([$targetField => $document->document_number]);

                continue;
            }

            if ($target['relation'] === 'workOrder') {
                $workOrder = $transaction->workOrder;
                if (! $workOrder || ! $eventDate || ! $targetField) {
                    continue;
                }
                if (filled($workOrder->{$targetField})) {
                    $skipped++;

                    continue;
                }
                $document = $assign($type, Carbon::parse($eventDate));
                $workOrder->forceFill([$targetField => $document->document_number])->save();

                continue;
            }

            if ($target['relation'] === 'travels') {
                $rule = $definition['event_date_rule'];
                $travels = $transaction->travels->sortBy(function ($travel) use ($rule): string {
                    $date = $travel->{$rule['field']} ?: ($rule['fallback_field'] ? $travel->{$rule['fallback_field']} : null) ?: '9999-12-31';

                    return Carbon::parse($date)->format('Y-m-d').'-'.str_pad((string) ($travel->sort_order ?? 0), 8, '0', STR_PAD_LEFT).'-'.str_pad((string) $travel->id, 12, '0', STR_PAD_LEFT);
                });
                foreach ($travels as $travel) {
                    $travelEventDate = $travel->{$rule['field']} ?: ($rule['fallback_field'] ? $travel->{$rule['fallback_field']} : null);
                    if (! $travelEventDate) {
                        continue;
                    }
                    if ($targetField && filled($travel->{$targetField})) {
                        $skipped++;

                        continue;
                    }
                    $scopeKey = $definition['scope_rule'] === 'TRAVEL' ? 'TRAVEL-'.$travel->id : 'MAIN';
                    $document = $assign($type, Carbon::parse($travelEventDate), $scopeKey);
                    if ($targetField) {
                        $travel->forceFill([$targetField => $document->document_number])->save();
                    }
                    if ($rule['field'] === 'assignment_letter_date' && blank($travel->{$rule['field']})) {
                        $travel->forceFill([$rule['field'] => $travelEventDate])->save();
                    }
                }
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
        $documentType = $this->policy->canonicalAutomaticDocumentType($documentType);
        if ($documentType === null) {
            throw new InvalidArgumentException('Jenis dokumen tidak termasuk domain penomoran canonical aplikasi.');
        }
        $documentDate = $this->canonicalDocumentDate($package, $documentType, $documentDate, $scopeKey);

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

            $definition = $this->policy->numberingDefinition($documentType);
            if (($definition['number_target']['relation'] ?? null) === 'package' && $scopeKey === 'MAIN') {
                $field = $definition['number_target']['field'] ?? null;
                $updates = [
                    'status' => 'NUMBERED',
                    'numbered_at' => now(),
                    'cancelled_at' => null,
                    'cancelled_by' => null,
                    'cancellation_reason' => null,
                ];
                if ($field) {
                    $updates[$field] = $number;
                }
                $package->forceFill($updates)->save();
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

    private function canonicalDocumentDate(SpjPackage $package, string $documentType, CarbonInterface $fallback, string $scopeKey): CarbonInterface
    {
        $package->load('transaction');
        $package->transaction?->load(['goods', 'workOrder', 'travels']);
        $value = $this->policy->documentEventDateValue($package->transaction, $documentType, $scopeKey);

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

        return [1 => 'I', 'II', 'III', 'IV'][$quarter];
    }

    private function romanMonth(int $month): string
    {
        return [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][$month];
    }
}
