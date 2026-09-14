<?php

namespace App\Services;

final class SpjPeriodicReportRegistry
{
    public const SCOPE_MONTHLY = 'bulan';

    public const SCOPE_QUARTERLY = 'triwulan';

    public const SCOPE_SEMESTER = 'semester';

    public const SCOPE_ANNUAL = 'tahunan';

    /** @return list<string> */
    public function scopes(): array
    {
        return [
            self::SCOPE_MONTHLY,
            self::SCOPE_QUARTERLY,
            self::SCOPE_SEMESTER,
            self::SCOPE_ANNUAL,
        ];
    }

    /** @return array<string,string> */
    public function scopeLabels(): array
    {
        return [
            self::SCOPE_MONTHLY => 'Laporan Bulanan',
            self::SCOPE_QUARTERLY => 'Laporan Tahap & Triwulan',
            self::SCOPE_SEMESTER => 'Laporan Semester',
            self::SCOPE_ANNUAL => 'Laporan Tahunan',
        ];
    }

    /**
     * Registry canonical untuk seluruh laporan periode milik APP-SPJ.
     * Layout cetak/PDF dibuat oleh engine internal laporan periode dan tidak
     * bergantung pada template Paket SPJ.
     *
     * @return array<string,list<array{key:string,label:string}>>
     */
    public function packages(): array
    {
        return [
            self::SCOPE_MONTHLY => [
                $this->report('sptjm', 'SPTJM'),
                $this->report('bku', 'Buku Kas Umum'),
                $this->report('buku_pembantu_kas', 'Buku Pembantu Kas'),
                $this->report('buku_pembantu_bank', 'Buku Pembantu Bank'),
                $this->report('buku_pembantu_pajak', 'Buku Pembantu Pajak'),
                $this->report('k7b', 'Register Penutupan Kas (K7B)'),
                $this->report('k7c', 'Berita Acara Pemeriksaan Kas (K7C)'),
                $this->report('bos_k7a', 'BOS K7A'),
                $this->report('rekap_belanja_modal_barang_jasa', 'Rekap Belanja Modal dan Belanja Barang / Jasa'),
            ],
            self::SCOPE_QUARTERLY => [
                $this->report('sptjm', 'SPTJM'),
                $this->report('bos_k7a', 'BOS K7A'),
                $this->report('format_k7', 'Format K7'),
                $this->report('bku', 'Buku Kas Umum (BKU)'),
                $this->report('spb', 'SPB'),
                $this->report('sp2b', 'SP2B'),
                $this->report('lampiran_sp2b', 'Lampiran SP2B'),
                $this->report('sp2t', 'SP2T'),
                $this->report('berita_acara_rekonsiliasi', 'Berita Acara Rekonsiliasi'),
                $this->report('lampiran_berita_acara_rekonsiliasi', 'Lampiran Berita Acara Rekonsiliasi'),
            ],
            self::SCOPE_SEMESTER => [
                $this->report('sptjm', 'SPTJM'),
                $this->report('bos_k7a', 'BOS K7A'),
                $this->report('format_k7', 'Format K7'),
                $this->report('bku', 'Buku Kas Umum (BKU)'),
                $this->report('spb', 'SPB'),
                $this->report('sp2b', 'SP2B'),
                $this->report('lampiran_sp2b', 'Lampiran SP2B'),
                $this->report('sp2t', 'SP2T'),
                $this->report('berita_acara_rekonsiliasi', 'Berita Acara Rekonsiliasi'),
                $this->report('lampiran_berita_acara_rekonsiliasi', 'Lampiran Berita Acara Rekonsiliasi'),
                $this->report('rekap_bmd', 'Rekap Belanja Barang Milik Daerah (Aset)'),
            ],
            self::SCOPE_ANNUAL => [
                $this->report('sptjm', 'SPTJM'),
                $this->report('bos_k7a', 'BOS K7A'),
                $this->report('format_k7', 'Format K7'),
                $this->report('bku', 'Buku Kas Umum (BKU)'),
                $this->report('rekap_bmd', 'Rekap Barang Milik Daerah (Aset)'),
                $this->report('bos_k8', 'BOS K8'),
                $this->report('rekap_belanja_dana_bos', 'Rekap Belanja Dana BOS'),
                $this->report('form_1c', 'Form 1C'),
                $this->report('rekapitulasi_pengeluaran_dana_bos', 'Rekapitulasi Pengeluaran Dana BOS'),
            ],
        ];
    }

    /** @return list<array{key:string,label:string}> */
    public function forScope(string $scope): array
    {
        return $this->packages()[$scope] ?? [];
    }

    /** @return array{key:string,label:string}|null */
    public function find(string $scope, string $key): ?array
    {
        foreach ($this->forScope($scope) as $report) {
            if ($report['key'] === $key) {
                return $report;
            }
        }

        return null;
    }

    public function isScope(string $scope): bool
    {
        return in_array($scope, $this->scopes(), true);
    }

    public function scopeLabel(string $scope): string
    {
        return $this->scopeLabels()[$scope] ?? 'Laporan';
    }

    public function periodRequired(string $scope): bool
    {
        return $scope !== self::SCOPE_ANNUAL;
    }

    /** @return list<int> */
    public function validPeriods(string $scope): array
    {
        return match ($scope) {
            self::SCOPE_MONTHLY => range(1, 12),
            self::SCOPE_QUARTERLY => range(1, 4),
            self::SCOPE_SEMESTER => range(1, 2),
            self::SCOPE_ANNUAL => [],
            default => [],
        };
    }

    public function isValidPeriod(string $scope, ?int $period): bool
    {
        if (! $this->isScope($scope)) {
            return false;
        }

        if (! $this->periodRequired($scope)) {
            return $period === null;
        }

        return $period !== null && in_array($period, $this->validPeriods($scope), true);
    }

    /** @return array{key:string,label:string} */
    private function report(string $key, string $label): array
    {
        return compact('key', 'label');
    }
}
