<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ReconciliationController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->string('filter')->toString();
        $search = trim($request->string('q')->toString());

        $baseQuery = Transaction::query()
            ->activeContext()
            ->where(function ($query): void {
                $query->where('requires_reconciliation', true)
                    ->orWhere('source_status', 'SOURCE_MISSING');
            });

        $summary = [
            'total' => (clone $baseQuery)->count(),
            'changed' => (clone $baseQuery)->where('requires_reconciliation', true)->count(),
            'missing' => (clone $baseQuery)->where('source_status', 'SOURCE_MISSING')->count(),
            'with_package' => (clone $baseQuery)->whereHas('spjPackage')->count(),
        ];

        $transactions = $baseQuery
            ->with(['spjPackage:id,transaction_id,document_number,status'])
            ->withCount('items')
            ->when($filter === 'changed', fn ($query) => $query->where('requires_reconciliation', true))
            ->when($filter === 'missing', fn ($query) => $query->where('source_status', 'SOURCE_MISSING'))
            ->when($filter === 'with_package', fn ($query) => $query->whereHas('spjPackage'))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('no_bukti', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('payment_description', 'like', "%{$search}%")
                        ->orWhere('recipient_name', 'like', "%{$search}%")
                        ->orWhere('receipt_recipient_name', 'like', "%{$search}%");
                });
            })
            ->orderByRaw("CASE WHEN source_status = 'SOURCE_MISSING' THEN 0 ELSE 1 END")
            ->orderByDesc('requires_reconciliation')
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('reconciliation.index', compact('transactions', 'summary', 'filter', 'search'));
    }
}
