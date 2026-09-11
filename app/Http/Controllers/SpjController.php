<?php

namespace App\Http\Controllers;

use App\UseCases\Spj\SpjDocumentLifecycleUseCase;
use App\UseCases\Spj\SpjDocumentUseCase;
use App\UseCases\Spj\SpjFiscalPeriodUseCase;
use App\UseCases\Spj\SpjNumberingRollbackUseCase;
use App\UseCases\Spj\SpjPackageCategoryUseCase;
use App\UseCases\Spj\SpjPackageLifecycleUseCase;
use App\UseCases\Spj\SpjQuarterNumberingUseCase;
use App\UseCases\Spj\SpjReportUseCase;
use App\UseCases\Spj\SpjSettlementUseCase;
use App\UseCases\Spj\SpjSingleNumberingUseCase;
use App\UseCases\Spj\SpjWorkspaceUseCase;
use App\UseCases\Spj\UpdateSpjPackageDetailsUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SpjController extends Controller
{
    public function index(Request $request, SpjWorkspaceUseCase $useCase): View|RedirectResponse
    {
        return $useCase->handle($request);
    }

    public function assignNumber(string $packageId, SpjSingleNumberingUseCase $useCase): RedirectResponse
    {
        return $useCase->assignNumber($packageId);
    }

    public function markReady(string $packageId, SpjPackageLifecycleUseCase $useCase): RedirectResponse
    {
        return $useCase->markReady($packageId);
    }

    public function assignQuarterNumbers(Request $request, SpjQuarterNumberingUseCase $useCase): RedirectResponse
    {
        return $useCase->assignQuarterNumbers($request);
    }

    public function rollbackNumbering(Request $request, SpjNumberingRollbackUseCase $useCase): RedirectResponse
    {
        return $useCase->rollbackFromSequence($request);
    }

    public function cancelQuarterNumbering(Request $request, SpjNumberingRollbackUseCase $useCase): RedirectResponse
    {
        return $useCase->cancelQuarter($request);
    }

    public function assignDocumentNumber(Request $request, string $packageId, string $documentType, SpjSingleNumberingUseCase $useCase): RedirectResponse
    {
        return $useCase->assignDocumentNumber($request, $packageId, $documentType);
    }

    public function finalizeDocument(string $documentId, SpjDocumentLifecycleUseCase $useCase): RedirectResponse
    {
        return $useCase->finalizeDocument($documentId);
    }

    public function cancelDocument(Request $request, string $documentId, SpjDocumentLifecycleUseCase $useCase): RedirectResponse
    {
        return $useCase->cancelDocument($request, $documentId);
    }

    public function replaceDocument(Request $request, string $documentId, SpjDocumentLifecycleUseCase $useCase): RedirectResponse
    {
        return $useCase->replaceDocument($request, $documentId);
    }

    public function closeQuarter(Request $request, SpjFiscalPeriodUseCase $useCase): RedirectResponse
    {
        return $useCase->closeQuarter($request);
    }

    public function reopenQuarter(Request $request, string $periodId, SpjFiscalPeriodUseCase $useCase): RedirectResponse
    {
        return $useCase->reopenQuarter($request, $periodId);
    }

    public function storePayment(Request $request, string $transactionId, SpjSettlementUseCase $useCase): RedirectResponse
    {
        return $useCase->storePayment($request, $transactionId);
    }

    public function storeGoodsReceipt(Request $request, string $transactionId, SpjSettlementUseCase $useCase): RedirectResponse
    {
        return $useCase->storeGoodsReceipt($request, $transactionId);
    }

    public function unlockPackage(Request $request, string $packageId, SpjPackageLifecycleUseCase $useCase): RedirectResponse
    {
        return $useCase->unlockPackage($request, $packageId);
    }

    public function updateDetails(
        string $packageId,
        Request $request,
        UpdateSpjPackageDetailsUseCase $useCase,
        SpjPackageCategoryUseCase $categoryUseCase,
    ): RedirectResponse|JsonResponse {
        if ($request->boolean('category_switch')) {
            return $categoryUseCase->switchCategory($packageId, $request);
        }

        return $useCase->handle($packageId, $request);
    }

    public function download(string $packageId, SpjDocumentUseCase $useCase)
    {
        return $useCase->download($packageId);
    }

    public function downloadPackageExcel(string $packageId, SpjDocumentUseCase $useCase)
    {
        return $useCase->downloadPackageExcel($packageId);
    }

    public function previewPackage(string $packageId, SpjDocumentUseCase $useCase): View|RedirectResponse
    {
        return $useCase->previewPackage($packageId);
    }

    public function downloadTemplate(string $packageId, string $templateId, SpjDocumentUseCase $useCase)
    {
        return $useCase->downloadTemplate($packageId, $templateId);
    }

    public function downloadTemplatePdf(string $packageId, string $templateId, SpjDocumentUseCase $useCase)
    {
        return $useCase->downloadTemplatePdf($packageId, $templateId);
    }

    public function previewTemplate(string $packageId, string $templateId, SpjDocumentUseCase $useCase): View|RedirectResponse
    {
        return $useCase->previewTemplate($packageId, $templateId);
    }

    public function export(Request $request, string $format, SpjReportUseCase $useCase)
    {
        return $useCase->export($request, $format);
    }

    public function exportHonorPayments(Request $request, string $format, SpjReportUseCase $useCase)
    {
        return $useCase->exportHonorPayments($request, $format);
    }
}
