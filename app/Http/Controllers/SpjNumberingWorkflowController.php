<?php

namespace App\Http\Controllers;

use App\Models\DocumentTemplate;
use App\Models\FiscalPeriodClosure;
use App\Models\QuarterNumberingRun;
use App\Models\SpjPackage;
use App\Models\Transaction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SpjNumberingWorkflowController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate([
            'quarter' => ['nullable', 'integer', 'between:1,4'],
        ]);

        $yearId = (int) session('active_fiscal_year_id');
        $selectedQuarter = (int) ($data['quarter'] ?? min(4, max(1, (int) ceil(now()->month / 3))));
        $closures = FiscalPeriodClosure::query()
            ->where('fiscal_year_id', $yearId)
            ->orderBy('quarter')
            ->get()
            ->keyBy('quarter');

        $quarterSummaries = collect(range(1, 4))->mapWithKeys(function (int $quarter) use ($closures): array {
            $transactionQuery = $this->quarterTransactions($quarter);
            $packageQuery = SpjPackage::query()
                ->whereHas('transaction', fn ($query) => $this->applyQuarterScope($query->activeContext(), $quarter));

            $transactionsWithItems = (clone $transactionQuery)->has('items')->count();
            $withoutPackage = (clone $transactionQuery)->has('items')->doesntHave('spjPackage')->count();
            $draft = (clone $packageQuery)->where('status', 'DRAFT')->count();
            $ready = (clone $packageQuery)->where('status', 'READY')->count();
            $numbered = (clone $packageQuery)->whereIn('status', ['NUMBERED', 'FINAL'])->count();

            return [$quarter => [
                'quarter' => $quarter,
                'transactions' => $transactionsWithItems,
                'without_package' => $withoutPackage,
                'draft' => $draft,
                'ready' => $ready,
                'numbered' => $numbered,
                'blocked' => $withoutPackage + $draft,
                'closure' => $closures->get($quarter),
            ]];
        });

        $previewPackages = SpjPackage::query()
            ->with(['transaction:id,no_bukti,transaction_date,payment_description,description,recipient_name,spj_category,gross_amount,fiscal_year_id,fund_source_id', 'documents:id,spj_package_id,document_type,document_number,status'])
            ->whereHas('transaction', fn ($query) => $this->applyQuarterScope($query->activeContext(), $selectedQuarter))
            ->whereIn('status', ['READY', 'NUMBERED', 'FINAL'])
            ->orderByRaw("CASE status WHEN 'READY' THEN 0 WHEN 'NUMBERED' THEN 1 ELSE 2 END")
            ->orderBy('id')
            ->get();

        $documentTypes = DocumentTemplate::query()
            ->where(['fiscal_year_id' => $yearId, 'is_active' => true])
            ->pluck('document_type')
            ->push('SPJ')
            ->map(fn ($type) => strtoupper(trim((string) $type)))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $recentRuns = QuarterNumberingRun::query()
            ->where('fiscal_year_id', $yearId)
            ->latest('id')
            ->limit(8)
            ->get();

        return view('spj.numbering', [
            'selectedQuarter' => $selectedQuarter,
            'quarterSummaries' => $quarterSummaries,
            'previewPackages' => $previewPackages,
            'documentTypes' => $documentTypes,
            'recentRuns' => $recentRuns,
            'selectedSummary' => $quarterSummaries->get($selectedQuarter),
        ]);
    }

    private function quarterTransactions(int $quarter)
    {
        return $this->applyQuarterScope(Transaction::query()->activeContext(), $quarter);
    }

    private function applyQuarterScope($query, int $quarter)
    {
        $startMonth = (($quarter - 1) * 3) + 1;
        $endMonth = $quarter * 3;

        return $query
            ->whereMonth('transaction_date', '>=', $startMonth)
            ->whereMonth('transaction_date', '<=', $endMonth);
    }
}
