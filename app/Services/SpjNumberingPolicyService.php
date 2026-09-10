<?php

namespace App\Services;

use App\Models\DocumentNumberFormat;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * Canonical policy for automatic SPJ document numbering.
 *
 * The transaction/payment quarter determines which package enters a batch,
 * while each document keeps its own canonical event date. This service only
 * answers whether a document type is applicable to the transaction category
 * and owns the default number format used when a school has not customized it.
 */
class SpjNumberingPolicyService
{
    /** @var list<string> */
    public const AUTOMATIC_DOCUMENT_TYPES = [
        'SPJ',
        'PESANAN',
        'BAP',
        'BAST',
        'SPK',
        'RAB',
        'SURAT_TUGAS_PERJALANAN_DINAS',
    ];

    public function __construct(private readonly SpjProcurementPolicyService $procurementPolicy) {}

    /** @return list<string> */
    public function automaticDocumentTypes(): array
    {
        return self::AUTOMATIC_DOCUMENT_TYPES;
    }

    public function isAutomaticDocumentType(string $documentType): bool
    {
        return in_array($this->automaticTypeAlias($documentType), self::AUTOMATIC_DOCUMENT_TYPES, true);
    }

    public function isAutomaticDocumentEligible(Transaction $transaction, string $documentType): bool
    {
        $documentType = $this->automaticTypeAlias($documentType);
        $category = $this->canonicalCategory((string) $transaction->spj_category);

        // Dalam aplikasi ini SiPLah adalah channel khusus pembelian BARANG.
        // KONSUMSI tetap memakai dokumen pengadaan internal walaupun metadata
        // legacy pernah membawa payment_method/is_siplah yang tidak semestinya.
        $isSiplahBarang = $category === 'BARANG' && $this->procurementPolicy->isSiplah($transaction);

        return match ($documentType) {
            // Setiap paket SPJ mempunyai dokumen utama. Validasi paket tetap
            // menolak kategori kosong/tidak canonical sebelum workflow riil.
            'SPJ' => true,

            // Pesanan/BAP/BAST internal berlaku untuk BARANG Non-SiPLah dan KONSUMSI.
            'PESANAN', 'BAP', 'BAST' => in_array($category, ['BARANG', 'KONSUMSI'], true)
                && ! $isSiplahBarang,

            // SPK/RAB adalah domain pekerjaan pemeliharaan.
            'SPK', 'RAB' => $category === 'PEMELIHARAAN',

            // Surat tugas hanya diterbitkan untuk transaksi perjalanan dinas.
            'SURAT_TUGAS_PERJALANAN_DINAS' => $category === 'SPPD',

            default => false,
        };
    }

    /**
     * Persist canonical defaults for automatic document types without
     * overwriting any format already customized by the school/operator.
     *
     * @return Collection<int, DocumentNumberFormat>
     */
    public function ensureAutomaticFormats(int $fiscalYearId): Collection
    {
        return collect(self::AUTOMATIC_DOCUMENT_TYPES)
            ->map(fn (string $documentType): DocumentNumberFormat => $this->formatFor($fiscalYearId, $documentType));
    }

    public function formatFor(int $fiscalYearId, string $documentType): DocumentNumberFormat
    {
        $documentType = strtoupper(trim($documentType));

        return DocumentNumberFormat::query()->firstOrCreate(
            [
                'fiscal_year_id' => $fiscalYearId,
                'document_type' => $documentType,
            ],
            $this->defaultFormat($documentType),
        );
    }

    /** @return array{format_pattern:string,reset_period:string,padding:int,is_active:bool} */
    public function defaultFormat(string $documentType): array
    {
        $documentType = strtoupper(trim($documentType));
        $prefix = match ($documentType) {
            'ORDER' => 'PESANAN',
            'RECEIPT' => 'KWITANSI',
            default => $documentType,
        };

        return [
            'format_pattern' => '{SEQ}/'.$prefix.'/{SCHOOL}/{TW}/{YEAR}',
            'reset_period' => 'YEAR',
            'padding' => 4,
            'is_active' => true,
        ];
    }

    public function canonicalCategory(string $category): string
    {
        $category = strtoupper(trim($category));

        return match ($category) {
            'BELANJA_MODAL' => 'BARANG',
            'PERJALANAN_DINAS' => 'SPPD',
            'JASA_HONORARIUM' => 'HONOR_PEGAWAI',
            'UPAH' => 'PEMELIHARAAN',
            'LAINNYA' => 'JASA_LAINNYA',
            default => $category,
        };
    }

    private function automaticTypeAlias(string $documentType): string
    {
        $documentType = strtoupper(trim($documentType));

        return match ($documentType) {
            'ORDER', 'SURAT_PESANAN' => 'PESANAN',
            'WORK_ORDER' => 'SPK',
            default => $documentType,
        };
    }
}
