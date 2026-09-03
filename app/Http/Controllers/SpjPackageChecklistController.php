<?php

namespace App\Http\Controllers;

use App\Models\SpjPackage;
use App\Models\User;
use App\Services\SpjDocumentRequirementService;
use App\Services\SpjPackageValidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SpjPackageChecklistController extends Controller
{
    public function __invoke(
        string $packageId,
        SpjPackageValidationService $validator,
        SpjDocumentRequirementService $requirements,
    ): View|RedirectResponse {
        $package = SpjPackage::query()
            ->with([
                'transaction.items',
                'transaction.goods',
                'transaction.workOrder',
                'transaction.workers',
                'transaction.participants',
                'transaction.travels',
                'transaction.honors',
                'transaction.payments',
                'transaction.goodsReceipts',
            ])
            ->find($packageId);

        if (! $package
            || $package->transaction->fiscal_year_id !== (int) session('active_fiscal_year_id')
            || (int) $package->transaction->fund_source_id !== (int) session('active_fund_source_id')) {
            return redirect()
                ->route('spj.index', ['tab' => 'persiapan'])
                ->with('error', 'Paket SPJ tidak ditemukan pada konteks aktif.');
        }

        $checklist = collect($validator->checklist($package));
        $totalChecks = $checklist->count();
        $completedChecks = $checklist->where('passed', true)->count();
        $remainingChecks = $totalChecks - $completedChecks;
        $progress = $totalChecks > 0 ? (int) round(($completedChecks / $totalChecks) * 100) : 100;

        $documentRequirements = collect($requirements->forTransaction($package->transaction));
        $requirementSummary = $requirements->summary($package->transaction);

        $canEdit = in_array(auth()->user()?->role, [User::ROLE_ADMIN, User::ROLE_OPERATOR], true);
        $canMarkReady = $canEdit
            && $package->status === 'DRAFT'
            && $remainingChecks === 0
            && $requirementSummary['missing_required'] === 0;

        return view('spj.checklist', compact(
            'package',
            'checklist',
            'totalChecks',
            'completedChecks',
            'remainingChecks',
            'progress',
            'documentRequirements',
            'requirementSummary',
            'canEdit',
            'canMarkReady',
        ));
    }
}
