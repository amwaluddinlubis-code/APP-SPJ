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
            $invalidActiveDocuments = $activeDocuments->filter(
                fn (SpjDocument $document): bool => blank($document->document_number)
                    || ! in_array($document->status, ['NUMBERED', 'FINAL'], true)
            );
            if ($invalidActiveDocuments->isNotEmpty()) {
                throw new \RuntimeException('Finalisasi paket ditolak karena masih ada dokumen aktif yang belum bernomor.');
            }

            $missingRequired = collect($this->requiredDocumentIdentities($package))
                ->filter(function (array $identity) use ($activeDocuments): bool {
                    return ! $activeDocuments->contains(fn (SpjDocument $document): bool =>
                        $document->document_type === $identity['document_type']
                        && $document->scope_key === $identity['scope_key']
                        && filled($document->document_number)
                        && in_array($document->status, ['NUMBERED', 'FINAL'], true)
                    );
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

            $document->forceFill([
                'status' => 'CANCELLED', 'cancelled_at' => now(),
                'cancelled_by' => $userId, 'cancellation_reason' => trim($reason),
            ])->save();

            if ($document->document_type === 'SPJ' && $document->scope_key === 'MAIN') {
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
            if ($transaction && filled($number)) {
                match ($document->document_type) {
                    'PESANAN' => $transaction->goods()->where('order_number', $number)->update(['order_number' => null]),
                    'BAP' => $transaction->goods()->where('bap_number', $number)->update(['bap_number' => null]),
                    'BAST' => $transaction->goods()->where('bast_number', $number)->update(['bast_number' => null]),
                    'SPK' => $transaction->workOrder?->spk_number === $number ? $transaction->workOrder->forceFill(['spk_number' => null])->save() : null,
                    'RAB' => $transaction->workOrder?->rab_number === $number ? $transaction->workOrder->forceFill(['rab_number' => null])->save() : null,
                    'SURAT_TUGAS_PERJALANAN_DINAS' => $transaction->travels->firstWhere('assignment_letter_number', $number)?->forceFill(['assignment_letter_number' => null])->save(),
                    default => null,
                };
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
        $requirements = [
            ['document_type' => 'SPJ', 'scope_key' => 'MAIN'],
        ];

        foreach ([
            'PESANAN' => 'order_date',
            'BAP' => 'bap_date',
            'BAST' => 'bast_date',
        ] as $documentType => $dateField) {
            if ($this->numberingPolicy->isAutomaticDocumentEligible($transaction, $documentType)
                && $transaction->goods->pluck($dateField)->filter()->isNotEmpty()) {
                $requirements[] = ['document_type' => $documentType, 'scope_key' => 'MAIN'];
            }
        }

        foreach ([
            'SPK' => 'spk_date',
            'RAB' => 'rab_date',
        ] as $documentType => $dateField) {
            if ($this->numberingPolicy->isAutomaticDocumentEligible($transaction, $documentType)
                && filled($transaction->workOrder?->{$dateField})) {
                $requirements[] = ['document_type' => $documentType, 'scope_key' => 'MAIN'];
            }
        }

        if ($this->numberingPolicy->isAutomaticDocumentEligible($transaction, 'SURAT_TUGAS_PERJALANAN_DINAS')) {
            foreach ($transaction->travels as $travel) {
                if ($travel->assignment_letter_date || $travel->departure_date) {
                    $requirements[] = [
                        'document_type' => 'SURAT_TUGAS_PERJALANAN_DINAS',
                        'scope_key' => 'TRAVEL-'.$travel->id,
                    ];
                }
            }
        }

        return $requirements;
    }
}
