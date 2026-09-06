<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MaintenanceTransactionLinkController extends Controller
{
    public function show(string $transactionId): JsonResponse
    {
        $transaction = $this->findActiveTransaction($transactionId);

        $candidates = $this->candidateQuery($transaction)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get(['id', 'no_bukti', 'payment_description'])
            ->map(fn (Transaction $candidate): array => [
                'id' => $candidate->id,
                'label' => $candidate->no_bukti.' - '.$candidate->payment_description,
            ])
            ->values();

        return response()->json([
            'candidates' => $candidates,
            'selected' => [
                'material_transaction_id' => $transaction->maintenance_material_transaction_id,
                'labor_transaction_id' => $transaction->maintenance_labor_transaction_id,
            ],
        ]);
    }

    public function update(Request $request, string $transactionId): JsonResponse
    {
        $transaction = $this->findActiveTransaction($transactionId);

        if ($transaction->spjPackage && ! $transaction->spjPackage->isEditable()) {
            abort(423, 'Transaksi dikunci karena Paket SPJ sudah bernomor atau final.');
        }

        $data = $request->validate([
            'material_transaction_id' => ['nullable', 'integer'],
            'labor_transaction_id' => ['nullable', 'integer'],
        ]);

        foreach (['material_transaction_id', 'labor_transaction_id'] as $field) {
            $candidateId = $data[$field] ?? null;
            if ($candidateId === null) {
                continue;
            }

            if (! (clone $this->candidateQuery($transaction))->whereKey($candidateId)->exists()) {
                throw ValidationException::withMessages([
                    $field => 'Transaksi terkait tidak memenuhi syarat tahun anggaran, sumber dana, tanggal, atau status sumber.',
                ]);
            }
        }

        if (
            filled($data['material_transaction_id'] ?? null)
            && filled($data['labor_transaction_id'] ?? null)
            && (int) $data['material_transaction_id'] === (int) $data['labor_transaction_id']
        ) {
            throw ValidationException::withMessages([
                'labor_transaction_id' => 'Transaksi bahan dan transaksi upah harus berbeda.',
            ]);
        }

        $transaction->forceFill([
            'maintenance_material_transaction_id' => $data['material_transaction_id'] ?? null,
            'maintenance_labor_transaction_id' => $data['labor_transaction_id'] ?? null,
        ])->save();

        return response()->json(['message' => 'Transaksi terkait pemeliharaan berhasil disimpan.']);
    }

    private function findActiveTransaction(string $transactionId): Transaction
    {
        $transaction = Transaction::query()
            ->with('spjPackage')
            ->activeContext()
            ->find($transactionId);

        abort_unless($transaction, 404, 'Transaksi tidak ditemukan pada konteks aktif.');

        return $transaction;
    }

    private function candidateQuery(Transaction $transaction): Builder
    {
        return Transaction::query()
            ->activeContext()
            ->whereKeyNot($transaction->id)
            ->whereDate('transaction_date', '>=', $transaction->transaction_date)
            ->where('source_status', 'ACTIVE')
            ->where('requires_reconciliation', false)
            ->whereNotNull('payment_description')
            ->where('payment_description', '!=', '');
    }
}
