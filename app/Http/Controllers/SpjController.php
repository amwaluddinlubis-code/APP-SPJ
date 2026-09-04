<?php

namespace App\Http\Controllers;

use App\UseCases\Spj\SpjDocumentUseCase;
use App\UseCases\Spj\SpjNumberingUseCase;
use App\UseCases\Spj\SpjPackageUseCase;
use App\UseCases\Spj\SpjReportUseCase;
use App\UseCases\Spj\SpjWorkspaceUseCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SpjController extends Controller
{
    public function index(Request $request, SpjWorkspaceUseCase $useCase): View|RedirectResponse
    {
        return $useCase->handle($request);
    }

    public function prepare(Request $request, string $transactionId, SpjPackageUseCase $useCase): RedirectResponse
    {
        return $useCase->prepare($request, $transactionId);
    }

    public function assignNumber(string $packageId, SpjNumberingUseCase $useCase): RedirectResponse
    {
        return $useCase->assignNumber($packageId);
    }

    public function markReady(string $packageId, SpjNumberingUseCase $useCase): RedirectResponse
    {
        return $useCase->markReady($packageId);
    }

    public function assignQuarterNumbers(Request $request, SpjNumberingUseCase $useCase): RedirectResponse
    {
        return $useCase->assignQuarterNumbers($request);
    }

    public function assignDocumentNumber(Request $request, string $packageId, string $documentType, SpjNumberingUseCase $useCase): RedirectResponse
    {
        return $useCase->assignDocumentNumber($request, $packageId, $documentType);
    }

    public function finalizeDocument(string $documentId, SpjNumberingUseCase $useCase): RedirectResponse
    {
        return $useCase->finalizeDocument($documentId);
    }

    public function cancelDocument(Request $request, string $documentId, SpjNumberingUseCase $useCase): RedirectResponse
    {
        return $useCase->cancelDocument($request, $documentId);
    }

    public function replaceDocument(Request $request, string $documentId, SpjNumberingUseCase $useCase): RedirectResponse
    {
        return $useCase->replaceDocument($request, $documentId);
    }

    public function closeQuarter(Request $request, SpjNumberingUseCase $useCase): RedirectResponse
    {
        return $useCase->closeQuarter($request);
    }

    public function reopenQuarter(Request $request, string $periodId, SpjNumberingUseCase $useCase): RedirectResponse
    {
        return $useCase->reopenQuarter($request, $periodId);
    }

    public function storePayment(Request $request, string $transactionId, SpjNumberingUseCase $useCase): RedirectResponse
    {
        return $useCase->storePayment($request, $transactionId);
    }

    public function storeGoodsReceipt(Request $request, string $transactionId, SpjNumberingUseCase $useCase): RedirectResponse
    {
        return $useCase->storeGoodsReceipt($request, $transactionId);
    }

    public function unlockPackage(Request $request, string $packageId, SpjNumberingUseCase $useCase): RedirectResponse
    {
        return $useCase->unlockPackage($request, $packageId);
    }

    public function updateDetails(string $packageId, Request $request, SpjPackageUseCase $useCase): RedirectResponse
    {
        return $useCase->updateDetails($packageId, $request);
    }

    public function download(string $packageId, SpjDocumentUseCase $useCase)
    {
        return $useCase->download($packageId);
    }

    public function downloadTemplate(string $packageId, string $templateId, SpjDocumentUseCase $useCase)
    {
        return $useCase->downloadTemplate($packageId, $templateId);
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
