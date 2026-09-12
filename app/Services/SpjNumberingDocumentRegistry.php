<?php

namespace App\Services;

final class SpjNumberingDocumentRegistry
{
    public const CHANNEL_ANY = 'ANY';

    public const CHANNEL_NON_SIPLAH = 'NON_SIPLAH';

    /**
     * Canonical numbering registry.
     *
     * Every consumer of document numbering must derive document codes, labels,
     * eligibility categories, channel constraints, event-date rules, target
     * number fields, and scope behavior from this registry.
     *
     * @return array<string,array{
     *     code:string,
     *     label:string,
     *     numbered:bool,
     *     applicable_categories:list<string>,
     *     channel:string,
     *     event_date_rule:array{relation:string,field:string,fallback_field:?string},
     *     number_field:?string,
     *     scope_rule:string
     * }>
     */
    public function all(): array
    {
        $allCategories = ['BARANG', 'KONSUMSI', 'PEMELIHARAAN', 'SPPD', 'HONOR_PEGAWAI', 'JASA_LAINNYA'];

        return [
            'SPJ' => $this->definition(
                'SPJ',
                'SPJ Utama',
                $allCategories,
                self::CHANNEL_ANY,
                'transaction',
                'transaction_date',
                null,
                'document_number',
                'MAIN',
            ),
            'PESANAN' => $this->definition(
                'PESANAN',
                'Surat Pesanan',
                ['BARANG', 'KONSUMSI'],
                self::CHANNEL_NON_SIPLAH,
                'goods',
                'order_date',
                null,
                'order_number',
                'MAIN',
            ),
            'BAP' => $this->definition(
                'BAP',
                'Berita Acara Pemeriksaan/Penerimaan',
                ['BARANG', 'KONSUMSI'],
                self::CHANNEL_NON_SIPLAH,
                'goods',
                'bap_date',
                null,
                'bap_number',
                'MAIN',
            ),
            'BAST' => $this->definition(
                'BAST',
                'Berita Acara Serah Terima',
                ['BARANG', 'KONSUMSI'],
                self::CHANNEL_NON_SIPLAH,
                'goods',
                'bast_date',
                null,
                'bast_number',
                'MAIN',
            ),
            'SPK' => $this->definition(
                'SPK',
                'Surat Perintah Kerja',
                ['PEMELIHARAAN'],
                self::CHANNEL_ANY,
                'workOrder',
                'spk_date',
                null,
                'spk_number',
                'MAIN',
            ),
            'RAB' => $this->definition(
                'RAB',
                'Rencana Anggaran Biaya',
                ['PEMELIHARAAN'],
                self::CHANNEL_ANY,
                'workOrder',
                'rab_date',
                null,
                'rab_number',
                'MAIN',
            ),
            'SURAT_TUGAS_PERJALANAN_DINAS' => $this->definition(
                'SURAT_TUGAS_PERJALANAN_DINAS',
                'Surat Tugas Perjalanan Dinas',
                ['SPPD'],
                self::CHANNEL_ANY,
                'travels',
                'assignment_letter_date',
                'departure_date',
                'assignment_letter_number',
                'TRAVEL',
            ),
        ];
    }

    /** @return list<string> */
    public function numberedCodes(): array
    {
        return array_keys(array_filter(
            $this->all(),
            fn (array $definition): bool => $definition['numbered'] === true,
        ));
    }

    /** @return array<string,string> */
    public function numberedLabels(): array
    {
        $labels = [];
        foreach ($this->all() as $code => $definition) {
            if ($definition['numbered']) {
                $labels[$code] = $definition['label'];
            }
        }

        return $labels;
    }

    /** @return array<string,mixed>|null */
    public function get(string $code): ?array
    {
        $canonical = $this->canonical($code);

        return $canonical ? ($this->all()[$canonical] ?? null) : null;
    }

    public function canonical(string $code): ?string
    {
        $normalized = strtoupper(trim($code));
        if (isset($this->all()[$normalized])) {
            return $normalized;
        }

        $alias = [
            'ORDER' => 'PESANAN',
            'SURAT_PESANAN' => 'PESANAN',
            'WORK_ORDER' => 'SPK',
            'SPK_PEMELIHARAAN' => 'SPK',
            'RAB_PEMELIHARAAN' => 'RAB',
        ][$normalized] ?? null;

        return $alias && isset($this->all()[$alias]) ? $alias : null;
    }

    /** @return array<string,mixed> */
    private function definition(
        string $code,
        string $label,
        array $categories,
        string $channel,
        string $eventRelation,
        string $eventField,
        ?string $fallbackField,
        ?string $numberField,
        string $scopeRule,
    ): array {
        return [
            'code' => $code,
            'label' => $label,
            'numbered' => true,
            'applicable_categories' => $categories,
            'channel' => $channel,
            'event_date_rule' => [
                'relation' => $eventRelation,
                'field' => $eventField,
                'fallback_field' => $fallbackField,
            ],
            'number_field' => $numberField,
            'scope_rule' => $scopeRule,
        ];
    }
}
