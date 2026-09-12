<?php

namespace App\Services;

use App\Models\School;
use App\Models\SpjPackage;
use App\Support\ActiveSpjContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class DocumentTemplatePlaceholderInspectorService
{
    public function __construct(
        private readonly ActiveSpjContext $context,
        private readonly SpjTemplateService $templates,
        private readonly SpjMaintenanceDocumentContextService $maintenanceContext,
    ) {}

    /**
     * @return array{
     *     reference:string,
     *     package:array{id:string,document_number:string,no_bukti:string,status:string,category:string,transaction_date:string},
     *     groups:array<int,array{name:string,placeholders:array<int,array{key:string,marker:string,value:string,kind:string}>}>,
     *     total:int
     * }|null
     */
    public function inspect(string $reference, School $school): ?array
    {
        $reference = trim($reference);
        if ($reference === '') {
            return null;
        }

        $package = $this->findPackage($reference);
        if (! $package) {
            return null;
        }

        $this->maintenanceContext->apply($package);
        $values = $this->templates->placeholders($package, $school);
        $values = $this->appendRepeatingValues($values, $package, $school);
        $groups = [];
        $total = 0;

        foreach (SpjTemplateService::placeholderGroups() as $groupName => $markers) {
            $placeholders = [];

            foreach ($markers as $marker) {
                $placeholders[] = [
                    'key' => $marker,
                    'marker' => '{{'.$marker.'}}',
                    'value' => (string) ($values[$marker] ?? SpjDocumentTypeRegistry::EMPTY_SCALAR_VALUE),
                    'kind' => $this->placeholderKind($marker),
                ];
                $total++;
            }

            $groups[] = [
                'name' => $groupName,
                'placeholders' => $placeholders,
            ];
        }

        $transaction = $package->transaction;

        return [
            'reference' => $reference,
            'package' => [
                'id' => (string) $package->getKey(),
                'document_number' => $this->displayValue((string) $package->document_number),
                'no_bukti' => $this->displayValue((string) $transaction->no_bukti),
                'status' => $this->displayValue((string) $package->status),
                'category' => $this->displayValue((string) $transaction->spj_category),
                'transaction_date' => $transaction->transaction_date?->format('d-m-Y') ?: SpjDocumentTypeRegistry::EMPTY_SCALAR_VALUE,
            ],
            'groups' => $groups,
            'total' => $total,
        ];
    }

    private function findPackage(string $reference): ?SpjPackage
    {
        $package = $this->baseQuery()
            ->where('document_number', $reference)
            ->first();

        if ($package) {
            return $package;
        }

        $package = $this->baseQuery()
            ->whereHas('documents', fn (Builder $query): Builder => $query
                ->where('document_number', $reference)
                ->where('status', '!=', 'CANCELLED'))
            ->first();

        if ($package) {
            return $package;
        }

        return $this->baseQuery()
            ->whereHas('transaction', fn (Builder $query): Builder => $query->where('no_bukti', $reference))
            ->first();
    }

    private function baseQuery(): Builder
    {
        return SpjPackage::query()
            ->with([
                'documents',
                'transaction.items',
                'transaction.goods',
                'transaction.workOrder',
                'transaction.workers',
                'transaction.serviceRecipients',
                'transaction.maintenanceMaterialTransaction.items',
                'transaction.maintenanceLaborTransaction.workOrder',
                'transaction.maintenanceLaborTransaction.workers',
            ])
            ->whereHas('transaction', fn (Builder $query): Builder => $query->forSpjContext($this->context));
    }

    /**
     * @param  array<string,string>  $values
     * @return array<string,string>
     */
    private function appendRepeatingValues(array $values, SpjPackage $package, School $school): array
    {
        $transaction = $package->transaction;
        $transaction->loadMissing(['items', 'workers']);

        $itemRows = $transaction->items->values()->map(function ($item, int $index) use ($transaction): array {
            return [
                'ITEM_NO' => (string) ($index + 1),
                'ITEM_URAIAN' => $this->displayValue((string) ($item->item_description ?: $item->description)),
                'ITEM_VOLUME' => $this->displayValue((string) $item->quantity),
                'ITEM_SATUAN' => $this->displayValue((string) ($item->unit ?: '—')),
                'ITEM_HARGA_SATUAN' => $this->rupiah($item->unit_price),
                'ITEM_JUMLAH' => $this->rupiah($item->amount),
                'ITEM_KODE_REKENING' => $this->displayValue((string) ($item->account_code ?: $transaction->account_code)),
                'ITEM_NAMA_REKENING' => $this->displayValue((string) ($item->account_name ?: $transaction->account_name)),
            ];
        });

        $workerRows = $transaction->workers->values()->map(function ($worker, int $index): array {
            return [
                'UPAH_NO' => (string) ($index + 1),
                'UPAH_NAMA' => $this->displayValue((string) $worker->name),
                'UPAH_PEKERJAAN' => $this->displayValue((string) $worker->job_description),
                'UPAH_HARI' => $this->displayValue((string) $worker->work_days),
                'UPAH_TARIF_HARI' => $this->rupiah($worker->daily_rate),
                'UPAH_JUMLAH' => $this->rupiah($worker->amount),
                'UPAH_PENERIMA_KUITANSI' => $worker->is_receipt_recipient ? 'YA' : 'TIDAK',
            ];
        });

        foreach (SpjTemplateService::placeholderGroups() as $markers) {
            foreach ($markers as $marker) {
                if (str_starts_with($marker, 'ITEM_')) {
                    $values[$marker] = $this->columnPreview($itemRows->all(), $marker);
                } elseif (str_starts_with($marker, 'UPAH_')) {
                    $values[$marker] = $this->columnPreview($workerRows->all(), $marker);
                }
            }
        }

        $letterheadPath = trim((string) $school->letterhead_path);
        $values['KOP_SURAT'] = $letterheadPath !== '' && Storage::disk('local')->exists($letterheadPath)
            ? '[GAMBAR KOP SURAT TERSEDIA]'
            : SpjDocumentTypeRegistry::EMPTY_SCALAR_VALUE;

        return $values;
    }

    /** @param array<int,array<string,string>> $rows */
    private function columnPreview(array $rows, string $marker): string
    {
        if ($rows === []) {
            return SpjDocumentTypeRegistry::EMPTY_SCALAR_VALUE;
        }

        return collect($rows)
            ->map(fn (array $row): string => (string) ($row[$marker] ?? SpjDocumentTypeRegistry::EMPTY_SCALAR_VALUE))
            ->implode("\n");
    }

    private function placeholderKind(string $marker): string
    {
        if ($marker === 'KOP_SURAT') {
            return 'image';
        }

        return str_starts_with($marker, 'ITEM_') || str_starts_with($marker, 'UPAH_')
            ? 'repeat'
            : 'scalar';
    }

    private function displayValue(string $value): string
    {
        return trim($value) === '' ? SpjDocumentTypeRegistry::EMPTY_SCALAR_VALUE : $value;
    }

    private function rupiah(mixed $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }
}
