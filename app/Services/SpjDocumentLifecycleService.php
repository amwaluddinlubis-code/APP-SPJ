<?php

namespace App\Services;

use App\Models\SpjDocument;
use App\Models\SpjPackage;
use Illuminate\Support\Facades\DB;

class SpjDocumentLifecycleService
{
    public function __construct(private readonly SpjNumberingPolicyService $numberingPolicy) {}

    public function finalize(SpjDocument $document, int $userId): SpjDocument
    {
        $package = $document->package()->with('transaction')->firstOrFail();
        $this->finalizePackage($package, $userId);

        return $document->fresh();
    }

    public function finalizePackage(SpjPackage $package, int $userId): SpjPackage
    {
        return DB::connection('school')->transaction(function () use ($package, $userId): SpjPackage {
            $package = SpjPackage::query()
                ->with([
                    'documents.template',
                    'transaction.items',
                    'transaction.goods',
                    'transaction.goodsReceipts',
                    'transaction.participants',
                    'transaction.travels',
                    'transaction.honors',
                    'transaction.payments',
                    'transaction.serviceRecipients',
                    'transaction.workOrder.workers',
                ])
                ->lockForUpdate()
                ->findOrFail($package->id);

            if ($package->status === 'FINAL') {
                return $package;
            }
            if ($package->status !== 'NUMBERED') {
                throw new \RuntimeException('Hanya paket NUMBERED yang dapat difinalkan.');
            }

            $activeDocuments = $package->documents->where('status', '!=', 'CANCELLED');
            $invalidActiveDocuments = $activeDocuments->filter(fn (SpjDocument $document): bool => blank($document->document_number)
                || ! in_array($document->status, ['NUMBERED', 'FINAL'], true));
            if ($invalidActiveDocuments->isNotEmpty()) {
                throw new \RuntimeException('Finalisasi paket ditolak karena masih ada dokumen aktif yang belum bernomor.');
            }

            $missingRequired = collect($this->requiredDocumentIdentities($package))
                ->filter(function (array $identity) use ($activeDocuments): bool {
                    return ! $activeDocuments->contains(fn (SpjDocument $document): bool => $document->document_type === $identity['document_type']
                        && $document->scope_key === $identity['scope_key']
                        && filled($document->document_number)
                        && in_array($document->status, ['NUMBERED', 'FINAL'], true));
                })
                ->map(fn (array $identity): string => $identity['scope_key'] === 'MAIN'
                    ? $identity['document_type']
                    : $identity['document_type'].' ('.$identity['scope_key'].')')
                ->values();

            if ($missingRequired->isNotEmpty()) {
                throw new \RuntimeException('Finalisasi paket ditolak. Nomor dokumen canonical belum lengkap: '.$missingRequired->implode(', ').'.');
            }

            $capturedAt = now();
            $packageSnapshot = $package->toArray();
            foreach ($activeDocuments as $document) {
                if ($document->status === 'FINAL') {
                    continue;
                }

                $template = $document->template;
                $templateSnapshot = $template?->only(['id', 'document_type', 'name', 'format', 'file_path', 'applicable_categories', 'updated_at']);
                $templatePath = $template ? storage_path('app/'.$template->file_path) : null;
                $document->forceFill([
                    'status' => 'FINAL',
                    'snapshot' => [
                        'document' => $document->only(['document_type', 'document_number', 'document_date', 'event_date', 'scope_key']),
                        'package' => $packageSnapshot,
                        'captured_at' => $capturedAt->toIso8601String(),
                    ],
                    'template_snapshot' => $templateSnapshot,
                    'template_hash' => $templatePath && is_file($templatePath) ? hash_file('sha256', $templatePath) : null,
                    'finalized_at' => $capturedAt,
                    'finalized_by' => $userId,
                ])->save();
            }

            $package->forceFill([
                'status' => 'FINAL',
                'snapshot' => [
                    'package' => $packageSnapshot,
                    'captured_at' => $capturedAt->toIso8601String(),
                ],
                'finalized_at' => $capturedAt,
                'finalized_by' => $userId,
            ])->save();

            return $package;
        });
    }

    public function cancel(SpjDocument $document, int $userId, string $reason): SpjDocument
    {
        if (! in_array($document->status, ['NUMBERED', 'FINAL'], true)) {
            throw new \RuntimeException('Hanya dokumen bernomor atau final yang dapat dibatalkan.');
        }
        if (blank($reason)) {
            throw new \InvalidArgumentException('Alasan pembatalan wajib diisi.');
        }

        DB::connection('school')->transaction(function () use ($document, $userId, $reason): void {
            $packageWasFinal = $document->package()->where('status', 'FINAL')->exists();
            $definition = $this->numberingPolicy->numberingDefinition($document->document_type);

            $document->forceFill([
                'status' => 'CANCELLED', 'cancelled_at' => now(),
                'cancelled_by' => $userId, 'cancellation_reason' => trim($reason),
            ])->save();

            $isPackageNumber = ($definition['number_target']['relation'] ?? null) === 'package' && $document->scope_key === 'MAIN';
            if ($isPackageNumber) {
                $document->package()->update([
                    'status' => 'CANCELLED', 'document_number' => null, 'numbered_at' => null,
                    'snapshot' => null, 'finalized_at' => null, 'finalized_by' => null,
                    'cancelled_at' => now(), 'cancelled_by' => $userId, 'cancellation_reason' => trim($reason),
                ]);
            } else {
                $document->package()->where('status', 'FINAL')->update([
                    'status' => 'NUMBERED',
                    'snapshot' => null,
                    'finalized_at' => null,
                    'finalized_by' => null,
                ]);
            }

            if ($packageWasFinal) {
                $document->package->documents()
                    ->where('status', 'FINAL')
                    ->update([
                        'status' => 'NUMBERED',
                        'snapshot' => null,
                        'template_snapshot' => null,
                        'template_hash' => null,
                        'finalized_at' => null,
                        'finalized_by' => null,
                    ]);
            }

            $package = $document->package()->with(['transaction.goods', 'transaction.workOrder', 'transaction.travels'])->first();
            $transaction = $package?->transaction;
            $number = $document->document_number;
            if ($transaction && filled($number) && $definition) {
                $target = $definition['number_target'];
                $field = $target['field'];
                if ($field) {
                    match ($target['relation']) {
                        'goods' => $transaction->goods()->where($field, $number)->update([$field => null]),
                        'workOrder' => $transaction->workOrder?->{$field} === $number ? $transaction->workOrder->forceFill([$field => null])->save() : null,
                        'travels' => $transaction->travels->firstWhere($field, $number)?->forceFill([$field => null])->save(),
                        default => null,
                    };
                }
            }
        });

        return $document;
    }

    public function unlock(SpjPackage $package, int $userId, string $reason): SpjPackage
    {
        throw new \RuntimeException('Buka kunci langsung paket bernomor dinonaktifkan. Gunakan rollback/cancel penomoran resmi agar histori dan sequence tetap konsisten.');
    }

    /** @return array<int,array{document_type:string,scope_key:string}> */
    private function requiredDocumentIdentities(SpjPackage $package): array
    {
        $transaction = $package->transaction;
        $requirements = [];

        foreach ($this->numberingPolicy->automaticDocumentTypes() as $documentType) {
            if (! $this->numberingPolicy->isAutomaticDocumentEligible($transaction, $documentType)) {
                continue;
            }

            $definition = $this->numberingPolicy->numberingDefinition($documentType);
            if (! $definition) {
                continue;
            }

            if ($definition['scope_rule'] === 'TRAVEL') {
                foreach ($transaction->travels as $travel) {
                    $scopeKey = 'TRAVEL-'.$travel->id;
                    if (filled($this->numberingPolicy->documentEventDateValue($transaction, $documentType, $scopeKey))) {
                        $requirements[] = ['document_type' => $documentType, 'scope_key' => $scopeKey];
                    }
                }

                continue;
            }

            if (filled($this->numberingPolicy->documentEventDateValue($transaction, $documentType))) {
                $requirements[] = ['document_type' => $documentType, 'scope_key' => 'MAIN'];
            }
        }

        return $requirements;
    }
}
