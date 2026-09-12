<?php

namespace App\UseCases\Spj;

use App\Models\QuarterNumberingRun;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Services\FiscalPeriodWorkflowService;
use App\Services\OperationalAuditService;
use App\Services\SpjDocumentNumberService;
use App\Services\SpjNumberingGateService;
use App\Services\SpjNumberingOrderService;
use App\Services\SpjNumberingPolicyService;
use App\Services\SpjPackageValidationService;
use App\Support\ActiveSpjContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

class SpjQuarterNumberingUseCase
{
    public function __construct(
        private readonly SpjPackageValidationService $validator,
        private readonly SpjDocumentNumberService $numbers,
        private readonly FiscalPeriodWorkflowService $periods,
        private readonly OperationalAuditService $audit,
        private readonly SpjNumberingOrderService $order,
        private readonly SpjNumberingPolicyService $numberingPolicy,
        private readonly SpjNumberingGateService $numberingGate,
        private readonly ActiveSpjContext $context,
    ) {}

    public function assignQuarterNumbers(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'quarter' => ['required', 'integer', 'between:1,4'],
            'document_types' => ['nullable', 'array'],
            'document_types.*' => ['string', 'max:40'],
        ]);
        $yearId = $this->context->fiscalYearId();
        $school = $this->context->school();
        $quarter = (int) $data['quarter'];

        $requestedTypes = collect($data['document_types'] ?? $this->numberingPolicy->automaticDocumentTypes())
            ->map(fn ($type) => strtoupper(trim((string) $type)))
            ->filter()
            ->values();
        $invalidTypes = $requestedTypes
            ->filter(fn (string $type): bool => $this->numberingPolicy->canonicalAutomaticDocumentType($type) === null)
            ->unique()
            ->values();
        if ($invalidTypes->isNotEmpty()) {
            return back()->with('error', 'Penomoran dibatalkan. Jenis dokumen di luar 7 domain canonical: '.$invalidTypes->implode(', ').'.');
        }

        $documentTypes = $requestedTypes
            ->map(fn (string $type): string => (string) $this->numberingPolicy->canonicalAutomaticDocumentType($type))
            ->unique()
            ->values();
        if ($documentTypes->isEmpty()) {
            return back()->with('error', 'Penomoran dibatalkan. Pilih minimal satu jenis dokumen canonical.');
        }

        if ($blocker = $this->numberingGate->previousQuarterFinalBlocker($quarter)) {
            return back()->with('error', $blocker);
        }

        $quarterScope = fn ($query) => $query->forSpjContext($this->context)
            ->whereMonth('transaction_date', '>=', ($quarter - 1) * 3 + 1)
            ->whereMonth('transaction_date', '<=', $quarter * 3);
        $notReady = Transaction::query()->where($quarterScope)->has('items')
            ->where(function ($query): void {
                $query->doesntHave('spjPackage')
                    ->orWhereHas('spjPackage', fn ($package) => $package->where('status', 'DRAFT'));
            })->count();
        if ($notReady > 0) {
            return back()->with('error', "Penomoran dibatalkan: masih ada {$notReady} transaksi triwulan ini yang belum berstatus READY.");
        }

        $period = $this->periods->period($yearId, $quarter);
        if ($period->status === 'CLOSED') {
            return back()->with('error', 'Triwulan sudah ditutup. Administrator harus membuka kembali periode terlebih dahulu.');
        }

        // Jadikan format default eksplisit sebelum nomor pertama diterbitkan.
        // firstOrCreate menjaga setiap format yang sudah dikustomisasi sekolah.
        $this->numberingPolicy->ensureAutomaticFormats($yearId);

        $run = QuarterNumberingRun::query()->create([
            'fiscal_period_closure_id' => $period->id,
            'fiscal_year_id' => $yearId,
            'quarter' => $quarter,
            'status' => 'RUNNING',
            'document_types' => $documentTypes->all(),
            'started_by' => $this->context->actorId(),
            'started_at' => now(),
        ]);

        $packages = SpjPackage::query()
            ->with([
                'documents',
                'transaction.items',
                'transaction.goods',
                'transaction.goodsReceipts',
                'transaction.workOrder',
                'transaction.honors',
                'transaction.travels',
                'transaction.payments',
                'transaction.workers',
                'transaction.participants',
                'transaction.serviceRecipients',
                'transaction.spjPackage',
            ])
            ->whereIn('status', ['READY', 'NUMBERED'])
            ->whereHas('transaction', $quarterScope)
            ->get();

        $invalidPackages = $packages->map(function (SpjPackage $package): ?array {
            $issues = $this->validator->validateForNumbering($package);

            return $issues ? ['package' => $package, 'issues' => $issues] : null;
        })->filter();
        if ($invalidPackages->isNotEmpty()) {
            $proofNumbers = $invalidPackages->map(fn (array $entry) => $entry['package']->transaction->no_bukti)->implode(', ');
            $run->update([
                'status' => 'FAILED',
                'failed_count' => $invalidPackages->count(),
                'error_message' => 'Paket tidak konsisten: '.$proofNumbers,
                'completed_at' => now(),
            ]);

            return back()->with('error', 'Penomoran dibatalkan. Perbaiki paket tidak konsisten: '.$proofNumbers.'.');
        }

        $numbered = 0;
        $skipped = 0;
        try {
            foreach ($documentTypes as $documentType) {
                if ($documentType === 'SURAT_TUGAS_PERJALANAN_DINAS') {
                    $travelPackages = $packages->filter(fn (SpjPackage $package): bool => $this->numberingPolicy
                        ->isAutomaticDocumentEligible($package->transaction, $documentType));
                    $travelResult = $this->assignQuarterTravelNumbers($travelPackages, $school->school_code ?: $school->npsn, $school->npsn);
                    $numbered += $travelResult['created'];
                    $skipped += $travelResult['skipped'];

                    continue;
                }

                $eligiblePackages = $packages->filter(fn (SpjPackage $package): bool => $this->numberingPolicy
                    ->isAutomaticDocumentEligible($package->transaction, $documentType)
                    && $this->order->documentEventDateValue($package, $documentType) !== null);
                foreach ($this->order->orderedPackagesForDocumentType($eligiblePackages, $documentType) as $package) {
                    $automaticResult = $this->numbers->assignAutomaticNumbers($package, $school->school_code ?: $school->npsn, $school->npsn, [$documentType]);
                    $numbered += $automaticResult['created'];
                    $skipped += $automaticResult['skipped'];
                }
            }

            $run->update(['status' => 'COMPLETED', 'numbered_count' => $numbered, 'skipped_count' => $skipped, 'completed_at' => now()]);
            $this->periods->markNumbered($period, $this->context->actorId());
        } catch (Throwable $exception) {
            $run->update(['status' => 'FAILED', 'numbered_count' => $numbered, 'skipped_count' => $skipped, 'failed_count' => 1, 'error_message' => $exception->getMessage(), 'completed_at' => now()]);

            return back()->with('error', 'Proses penomoran terhenti dan dapat dilanjutkan: '.$exception->getMessage());
        }

        $this->audit->record($yearId, 'SPJ_QUARTER', $quarter, 'PENOMORAN_BATCH', "Penomoran triwulan {$quarter}: {$numbered} nomor baru, {$skipped} sudah bernomor.");

        return back()->with('success', "Penomoran triwulan selesai: {$numbered} nomor baru; {$skipped} dokumen dilewati karena sudah bernomor.");
    }

    /**
     * Surat tugas memiliki scope per pelaksana, sehingga batch harus mengurutkan
     * setiap peristiwa perjalanan lintas paket, bukan hanya mengurutkan paket.
     *
     * @param  Collection<int, SpjPackage>  $packages
     * @return array{created:int,skipped:int}
     */
    private function assignQuarterTravelNumbers(Collection $packages, string $schoolCode, ?string $npsn): array
    {
        $entries = collect();
        foreach ($packages as $package) {
            foreach ($package->transaction->travels as $travel) {
                $eventDate = $travel->assignment_letter_date ?: $travel->departure_date;
                if (! $eventDate) {
                    continue;
                }
                $entries->push([
                    'package' => $package,
                    'travel' => $travel,
                    'date' => Carbon::parse($eventDate),
                    'key' => Carbon::parse($eventDate)->format('Y-m-d').'|'.$this->order->sourceOrderKey($package->transaction)
                        .'|'.str_pad((string) ($travel->sort_order ?? 0), 8, '0', STR_PAD_LEFT)
                        .'|'.str_pad((string) $travel->id, 12, '0', STR_PAD_LEFT),
                ]);
            }
        }

        $created = 0;
        $skipped = 0;
        foreach ($entries->sortBy('key')->values() as $entry) {
            $package = $entry['package'];
            $travel = $entry['travel'];
            if (filled($travel->assignment_letter_number)) {
                $skipped++;

                continue;
            }
            $scopeKey = 'TRAVEL-'.$travel->id;
            $before = $package->documents()
                ->where(['document_type' => 'SURAT_TUGAS_PERJALANAN_DINAS', 'scope_key' => $scopeKey])
                ->where('status', '!=', 'CANCELLED')
                ->whereNotNull('document_number')
                ->exists();
            $document = $this->numbers->assign($package, 'SURAT_TUGAS_PERJALANAN_DINAS', $entry['date'], $schoolCode, $scopeKey, npsn: $npsn);
            $travel->forceFill([
                'assignment_letter_number' => $document->document_number,
                'assignment_letter_date' => $travel->assignment_letter_date ?: $entry['date'],
            ])->save();
            $before ? $skipped++ : $created++;
        }

        return compact('created', 'skipped');
    }
}
