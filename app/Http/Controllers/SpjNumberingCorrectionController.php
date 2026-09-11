<?php

namespace App\Http\Controllers;

use App\Models\SpjDocument;
use App\Models\SpjPackage;
use App\Support\ActiveSpjContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SpjNumberingCorrectionController extends Controller
{
    public function __invoke(Request $request, ActiveSpjContext $context): View
    {
        $data = $request->validate([
            'quarter' => ['nullable', 'integer', 'between:1,4'],
        ]);
        $quarter = (int) ($data['quarter'] ?? 1);

        $documents = SpjDocument::query()
            ->with('package.transaction')
            ->where('document_type', 'SPJ')
            ->where('scope_key', 'MAIN')
            ->whereNotNull('document_number')
            ->whereHas('package.transaction', fn ($query) => $query
                ->where('fiscal_year_id', $context->fiscalYearId())
                ->where('fund_source_id', $context->fundSourceId()))
            ->orderBy('sequence_number')
            ->get();

        $quarterCounts = collect(range(1, 4))->mapWithKeys(function (int $candidate) use ($context): array {
            $start = (($candidate - 1) * 3) + 1;
            $end = $candidate * 3;
            $count = SpjPackage::query()
                ->whereHas('transaction', fn ($query) => $query
                    ->where('fiscal_year_id', $context->fiscalYearId())
                    ->where('fund_source_id', $context->fundSourceId())
                    ->whereMonth('transaction_date', '>=', $start)
                    ->whereMonth('transaction_date', '<=', $end))
                ->whereHas('documents', fn ($query) => $query
                    ->where('status', '!=', 'CANCELLED')
                    ->whereNotNull('document_number'))
                ->count();

            return [$candidate => $count];
        });

        return view('spj.numbering-correction', [
            'quarter' => $quarter,
            'documents' => $documents,
            'quarterCounts' => $quarterCounts,
        ]);
    }
}
