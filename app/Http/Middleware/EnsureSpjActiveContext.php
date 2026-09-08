<?php

namespace App\Http\Middleware;

use App\Models\SpjDocument;
use App\Models\SpjPackage;
use App\Models\Transaction;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSpjActiveContext
{
    /**
     * Prevent forged route identifiers from crossing the active
     * Tahun Anggaran + Sumber Dana boundary inside the school tenant.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $yearId = (int) session('active_fiscal_year_id');
        $fundSourceId = (int) session('active_fund_source_id');

        $transactionId = $request->route('transactionId');
        if ($transactionId !== null) {
            abort_unless(
                Transaction::query()
                    ->whereKey($transactionId)
                    ->where('fiscal_year_id', $yearId)
                    ->where('fund_source_id', $fundSourceId)
                    ->exists(),
                404
            );
        }

        $packageId = $request->route('packageId');
        if ($packageId !== null) {
            abort_unless(
                SpjPackage::query()
                    ->whereKey($packageId)
                    ->whereHas('transaction', fn (Builder $transaction): Builder => $transaction
                        ->where('fiscal_year_id', $yearId)
                        ->where('fund_source_id', $fundSourceId))
                    ->exists(),
                404
            );
        }

        $documentId = $request->route('documentId');
        if ($documentId !== null) {
            abort_unless(
                SpjDocument::query()
                    ->whereKey($documentId)
                    ->whereHas('package.transaction', fn (Builder $transaction): Builder => $transaction
                        ->where('fiscal_year_id', $yearId)
                        ->where('fund_source_id', $fundSourceId))
                    ->exists(),
                404
            );
        }

        return $next($request);
    }
}
