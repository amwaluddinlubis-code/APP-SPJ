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
        $transactionId = $request->route('transactionId');
        if ($transactionId !== null) {
            abort_unless(
                Transaction::query()->activeContext()->whereKey($transactionId)->exists(),
                404
            );
        }

        $packageId = $request->route('packageId');
        if ($packageId !== null) {
            abort_unless(
                SpjPackage::query()->activeContext()->whereKey($packageId)->exists(),
                404
            );
        }

        $documentId = $request->route('documentId');
        if ($documentId !== null) {
            abort_unless(
                SpjDocument::query()
                    ->whereKey($documentId)
                    ->whereHas('package', fn (Builder $package): Builder => $package->activeContext())
                    ->exists(),
                404
            );
        }

        return $next($request);
    }
}
