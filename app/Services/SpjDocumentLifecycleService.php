<?php

namespace App\Services;

use App\Models\SpjDocument;
use App\Models\SpjPackage;
use Illuminate\Support\Facades\DB;

class SpjDocumentLifecycleService
{
    public function finalize(SpjDocument $document, int $userId): SpjDocument
    {
        if ($document->status !== 'NUMBERED') {
            throw new \RuntimeException('Hanya dokumen bernomor yang dapat difinalkan.');
        }

        return DB::connection('school')->transaction(function () use ($document, $userId): SpjDocument {
            $package = $document->package()->with([
                'transaction.items', 'transaction.goods', 'transaction.participants',
                'transaction.travels', 'transaction.honors', 'transaction.workOrder.workers',
            ])->firstOrFail();
            $snapshot = [
                'document' => $document->only(['document_type', 'document_number', 'document_date', 'event_date']),
                'package' => $package->toArray(),
                'captured_at' => now()->toIso8601String(),
            ];
            $template = $document->template;
            $templateSnapshot = $template?->only(['id', 'document_type', 'name', 'format', 'file_path', 'applicable_categories', 'updated_at']);
            $templatePath = $template ? storage_path('app/'.$template->file_path) : null;
            $document->forceFill([
                'status' => 'FINAL', 'snapshot' => $snapshot,
                'template_snapshot' => $templateSnapshot,
                'template_hash' => $templatePath && is_file($templatePath) ? hash_file('sha256', $templatePath) : null,
                'finalized_at' => now(), 'finalized_by' => $userId,
            ])->save();

            if ($package->documents()->where('status', '!=', 'FINAL')->doesntExist()) {
                $package->forceFill([
                    'status' => 'FINAL', 'snapshot' => $snapshot,
                    'finalized_at' => now(), 'finalized_by' => $userId,
                ])->save();
            }

            return $document;
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
            $document->forceFill([
                'status' => 'CANCELLED', 'cancelled_at' => now(),
                'cancelled_by' => $userId, 'cancellation_reason' => trim($reason),
            ])->save();

            if ($document->document_type === 'SPJ' && $document->scope_key === 'MAIN') {
                $document->package()->update([
                    'status' => 'CANCELLED', 'document_number' => null, 'numbered_at' => null, 'cancelled_at' => now(),
                    'cancelled_by' => $userId, 'cancellation_reason' => trim($reason),
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
        if (! in_array($package->status, ['NUMBERED', 'CANCELLED'], true)) {
            throw new \RuntimeException('Hanya paket bernomor atau dibatalkan yang dapat dibuka kembali.');
        }
        if (blank($reason)) {
            throw new \InvalidArgumentException('Alasan pembukaan kunci wajib diisi.');
        }

        $package->forceFill([
            'status' => 'DRAFT', 'document_number' => null, 'numbered_at' => null, 'unlocked_at' => now(),
            'unlocked_by' => $userId, 'unlock_reason' => trim($reason),
        ])->save();

        return $package;
    }
}
