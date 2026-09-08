<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\SpjSourceReconciliationService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SourceReconciliationController extends Controller
{
    public function resolve(Request $request, string $transactionId, SpjSourceReconciliationService $service): RedirectResponse
    {
        $transaction = Transaction::query()->with('spjPackage')->find($transactionId);
        if (! $transaction
            || $transaction->fiscal_year_id !== (int) session('active_fiscal_year_id')
            || (int) $transaction->fund_source_id !== (int) session('active_fund_source_id')) {
            return redirect()->route('transactions.index')->with('error', 'Transaksi tidak ditemukan pada konteks aktif.');
        }

        $data = $request->validate([
            'resolution' => [
                'required',
                'string',
                Rule::in([
                    SpjSourceReconciliationService::REVIEWED_NO_BUSINESS_CHANGE,
                    SpjSourceReconciliationService::ACCEPT_SOURCE,
                    SpjSourceReconciliationService::KEEP_OVERLAY,
                ]),
            ],
            'source_event_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $result = $service->resolve(
                $transaction,
                $data['resolution'],
                $data['notes'] ?? null,
                auth()->id() !== null ? (int) auth()->id() : null,
                isset($data['source_event_id']) ? (int) $data['source_event_id'] : null,
            );
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Rekonsiliasi selesai: '.$result['label'].'.');
    }
}
