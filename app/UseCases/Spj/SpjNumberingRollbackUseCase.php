<?php

namespace App\UseCases\Spj;

use App\Models\DocumentNumberFormat;
use App\Models\FiscalPeriodClosure;
use App\Models\QuarterNumberingRun;
use App\Models\SpjDocument;
use App\Models\SpjPackage;
use App\Services\OperationalAuditService;
use App\Support\ActiveSpjContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SpjNumberingRollbackUseCase
{
    public function __construct(
        private readonly OperationalAuditService $audit,
        private readonly ActiveSpjContext $context,
    ) {}

    public function rollbackFromSequence(Request $request): RedirectResponse
    {
        abort_unless($this->context->isAdministrator(), 403);
        $data = $request->validate([
            'sequence_number' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $sequence = (int) $data['sequence_number'];
        $permanentCancelled = SpjDocument::query()
            ->where('document_type', 'SPJ')
            ->where('scope_key', 'MAIN')
            ->where('status', 'CANCELLED')
            ->whereNotNull('sequence_number')
            ->where('sequence_number', '>=', $sequence)
            ->whereHas('package.transaction', fn ($query) => $query
                ->where('fiscal_year_id', $this->context->fiscalYearId())
                ->where('fund_source_id', $this->context->fundSourceId()))
            ->orderBy('sequence_number')
            ->first();
        if ($permanentCancelled) {
            return back()->with('error', 'Rollback tidak dapat melewati nomor '.$permanentCancelled->document_number.' karena nomor tersebut adalah pembatalan individual permanen dan harus tetap menjadi histori.');
        }

        $documents = SpjDocument::query()
            ->with('package.transaction')
            ->where('document_type', 'SPJ')
            ->where('scope_key', 'MAIN')
            ->where('status', '!=', 'CANCELLED')
            ->whereNotNull('sequence_number')
            ->where('sequence_number', '>=', $sequence)
            ->whereHas('package.transaction', fn ($query) => $query
                ->where('fiscal_year_id', $this->context->fiscalYearId())
                ->where('fund_source_id', $this->context->fundSourceId()))
            ->orderByDesc('sequence_number')
            ->get();

        if ($documents->isEmpty()) {
            return back()->with('error', "Tidak ada nomor SPJ aktif mulai nomor urut {$sequence} pada konteks aktif.");
        }

        $minimum = (int) $documents->min('sequence_number');
        if ($minimum !== $sequence) {
            return back()->with('error', "Nomor urut {$sequence} tidak ditemukan sebagai nomor aktif. Rollback harus dimulai dari nomor aktif yang benar-benar diterbitkan.");
        }

        $packageIds = $documents->pluck('spj_package_id')->unique()->values();
        $lastSequence = (int) $documents->max('sequence_number');
        $this->rollbackPackages($packageIds, (string) $data['reason']);

        $this->audit->record(
            $this->context->fiscalYearId(),
            'SPJ_NUMBERING',
            (string) $sequence,
            'ROLLBACK_NUMBERING',
            "Rollback nomor SPJ {$sequence}-{$lastSequence}. Alasan: ".trim((string) $data['reason']),
        );

        return back()->with('warning', "Penomoran SPJ {$sequence}-{$lastSequence} berhasil di-rollback. Sequence siap dilanjutkan kembali dari nomor sebelumnya sesuai urutan ARKAS.");
    }

    public function cancelQuarter(Request $request): RedirectResponse
    {
        abort_unless($this->context->isAdministrator(), 403);
        $data = $request->validate([
            'quarter' => ['required', 'integer', 'between:1,4'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $quarter = (int) $data['quarter'];

        $futureQuarter = $quarter < 4
            ? collect(range($quarter + 1, 4))->first(function (int $candidate): bool {
                return SpjDocument::query()
                    ->where('document_type', 'SPJ')
                    ->where('scope_key', 'MAIN')
                    ->where('status', '!=', 'CANCELLED')
                    ->whereNotNull('document_number')
                    ->whereHas('package.transaction', fn ($query) => $this->applyQuarterContext($query, $candidate))
                    ->exists();
            })
            : null;
        if ($futureQuarter !== null) {
            return back()->with('error', "Penomoran Triwulan {$quarter} tidak dapat dibatalkan karena Triwulan {$futureQuarter} masih memiliki penomoran. Batalkan triwulan terbaru terlebih dahulu.");
        }

        $cancelledHistory = SpjDocument::query()
            ->where('document_type', 'SPJ')
            ->where('scope_key', 'MAIN')
            ->where('status', 'CANCELLED')
            ->whereNotNull('document_number')
            ->whereHas('package.transaction', fn ($query) => $this->applyQuarterContext($query, $quarter))
            ->first();
        if ($cancelledHistory) {
            return back()->with('error', 'Penomoran Triwulan '.$quarter.' tidak dapat di-reset penuh karena terdapat nomor pembatalan individual permanen '.$cancelledHistory->document_number.'.');
        }

        $period = FiscalPeriodClosure::query()->where([
            'fiscal_year_id' => $this->context->fiscalYearId(),
            'quarter' => $quarter,
        ])->first();
        if ($period?->status === 'CLOSED') {
            return back()->with('error', 'Triwulan masih CLOSED. Buka kembali periode terlebih dahulu sebelum membatalkan penomoran.');
        }

        $packageIds = SpjPackage::query()
            ->whereHas('transaction', fn ($query) => $this->applyQuarterContext($query, $quarter))
            ->whereHas('documents', fn ($query) => $query
                ->where('status', '!=', 'CANCELLED')
                ->whereNotNull('document_number'))
            ->pluck('id');
        if ($packageIds->isEmpty()) {
            return back()->with('error', "Triwulan {$quarter} tidak memiliki penomoran aktif yang dapat di-rollback.");
        }

        $count = $packageIds->count();
        $this->rollbackPackages($packageIds, (string) $data['reason']);

        DB::connection('school')->transaction(function () use ($quarter, $period): void {
            QuarterNumberingRun::query()
                ->where('fiscal_year_id', $this->context->fiscalYearId())
                ->where('quarter', $quarter)
                ->delete();

            if ($period) {
                $period->forceFill([
                    'status' => 'OPEN',
                    'numbered_at' => null,
                    'numbered_by' => null,
                    'closed_at' => null,
                    'closed_by' => null,
                ])->save();
            }
        });

        $this->audit->record(
            $this->context->fiscalYearId(),
            'SPJ_QUARTER',
            $quarter,
            'ROLLBACK_PENOMORAN_TRIWULAN',
            "Penomoran Triwulan {$quarter} di-rollback untuk {$count} paket. Alasan: ".trim((string) $data['reason']),
        );

        return back()->with('warning', "Penomoran Triwulan {$quarter} berhasil dibatalkan. {$count} paket dikembalikan ke DRAFT dan sequence di-reset ke checkpoint numbering yang masih sah.");
    }

    /** @param Collection<int, int|string> $packageIds */
    private function rollbackPackages(Collection $packageIds, string $reason): void
    {
        DB::connection('school')->transaction(function () use ($packageIds, $reason): void {
            $packages = SpjPackage::query()
                ->with(['transaction.items.goods', 'transaction.workOrder', 'transaction.travels', 'documents'])
                ->whereIn('id', $packageIds)
                ->lockForUpdate()
                ->get();

            foreach ($packages as $package) {
                if (! $this->context->matchesTransaction($package->transaction)) {
                    throw new RuntimeException('Rollback keluar dari konteks tenant aktif diblokir.');
                }

                $activeDocuments = $package->documents->where('status', '!=', 'CANCELLED');
                $numbers = $activeDocuments->pluck('document_number')->filter()->values();
                foreach ($package->transaction->items as $item) {
                    $goods = $item->goods;
                    if (! $goods) {
                        continue;
                    }
                    if ($numbers->contains($goods->order_number)) {
                        $goods->order_number = null;
                    }
                    if ($numbers->contains($goods->bap_number)) {
                        $goods->bap_number = null;
                    }
                    if ($numbers->contains($goods->bast_number)) {
                        $goods->bast_number = null;
                    }
                    if ($goods->isDirty()) {
                        $goods->save();
                    }
                }

                $workOrder = $package->transaction->workOrder;
                if ($workOrder) {
                    if ($numbers->contains($workOrder->spk_number)) {
                        $workOrder->spk_number = null;
                    }
                    if ($numbers->contains($workOrder->rab_number)) {
                        $workOrder->rab_number = null;
                    }
                    if ($workOrder->isDirty()) {
                        $workOrder->save();
                    }
                }

                foreach ($package->transaction->travels as $travel) {
                    if ($numbers->contains($travel->assignment_letter_number)) {
                        $travel->assignment_letter_number = null;
                        $travel->save();
                    }
                }

                $package->documents()->where('status', '!=', 'CANCELLED')->delete();
                $package->forceFill([
                    'status' => 'DRAFT',
                    'document_number' => null,
                    'numbered_at' => null,
                    'generated_at' => null,
                    'snapshot' => null,
                    'finalized_at' => null,
                    'finalized_by' => null,
                    'cancelled_at' => null,
                    'cancelled_by' => null,
                    'cancellation_reason' => null,
                    'unlocked_at' => now(),
                    'unlocked_by' => $this->context->actorId(),
                    'unlock_reason' => 'Rollback numbering: '.trim($reason),
                ])->save();
            }

            $this->rebuildSequences();
        });
    }

    private function rebuildSequences(): void
    {
        $yearId = $this->context->fiscalYearId();
        $fundSourceId = $this->context->fundSourceId();
        $formats = DocumentNumberFormat::query()->where('fiscal_year_id', $yearId)->get()->keyBy('document_type');
        $documents = SpjDocument::query()
            ->whereNotNull('sequence_number')
            ->whereHas('package.transaction', fn ($query) => $query
                ->where('fiscal_year_id', $yearId)
                ->where('fund_source_id', $fundSourceId))
            ->get(['document_type', 'sequence_number', 'document_date']);

        DB::connection('school')->table('document_number_sequences')
            ->where('fiscal_year_id', $yearId)
            ->where('fund_source_id', $fundSourceId)
            ->delete();

        $groups = $documents->groupBy(function (SpjDocument $document) use ($formats): string {
            $format = $formats->get($document->document_type);
            $period = $format?->reset_period ?? 'YEAR';
            $date = $document->document_date ? Carbon::parse($document->document_date) : Carbon::now();

            return $document->document_type.'|'.$this->periodKey($period, $date);
        });

        foreach ($groups as $key => $group) {
            [$documentType, $periodKey] = explode('|', $key, 2);
            DB::connection('school')->table('document_number_sequences')->insert([
                'fiscal_year_id' => $yearId,
                'fund_source_id' => $fundSourceId,
                'format_name' => $documentType,
                'period_key' => $periodKey,
                'last_number' => (int) $group->max('sequence_number'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function applyQuarterContext($query, int $quarter)
    {
        return $query
            ->where('fiscal_year_id', $this->context->fiscalYearId())
            ->where('fund_source_id', $this->context->fundSourceId())
            ->whereMonth('transaction_date', '>=', (($quarter - 1) * 3) + 1)
            ->whereMonth('transaction_date', '<=', $quarter * 3);
    }

    private function periodKey(string $resetPeriod, Carbon $date): string
    {
        return match (strtoupper($resetPeriod)) {
            'MONTH' => $date->format('Y-m'),
            'QUARTER' => $date->format('Y').'-Q'.(int) ceil((int) $date->format('n') / 3),
            'NONE' => 'ALL',
            default => $date->format('Y'),
        };
    }
}
