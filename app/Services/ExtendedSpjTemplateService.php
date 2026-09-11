<?php

namespace App\Services;

use App\Models\School;
use App\Models\SpjPackage;

class ExtendedSpjTemplateService extends SpjTemplateService
{
    /** @return array<string,array<int,string>> */
    public static function placeholderGroups(): array
    {
        return parent::placeholderGroups() + [
            'Konsumsi & kegiatan' => [
                'TANGGAL_KEGIATAN',
                'TEMPAT_KEGIATAN',
                'NAMA_PENANGGUNG_JAWAB',
                'NIP_PENANGGUNG_JAWAB',
                'KONSUMSI_NO',
                'KONSUMSI_NAMA',
                'KONSUMSI_IDENTITAS',
                'KONSUMSI_PORSI',
                'KONSUMSI_HARGA_PORSI',
                'KONSUMSI_JUMLAH',
                'TOTAL_KONSUMSI',
            ],
        ];
    }

    /** @return array<string,string> */
    public function placeholders(SpjPackage $package, School $school): array
    {
        $values = parent::placeholders($package, $school);
        $transaction = $package->transaction;
        $transaction->loadMissing(['participants.item']);

        $eventDate = $transaction->event_date?->translatedFormat('d F Y')
            ?: $transaction->transaction_date?->translatedFormat('d F Y')
            ?: SpjDocumentTypeRegistry::EMPTY_SCALAR_VALUE;
        $eventLocation = trim((string) $transaction->event_location);

        $participants = $transaction->participants->values();
        $participantNumbers = [];
        $participantNames = [];
        $participantIdentities = [];
        $participantPortions = [];
        $participantPrices = [];
        $participantAmounts = [];
        $totalConsumption = 0.0;

        foreach ($participants as $index => $participant) {
            $portions = (float) $participant->portions;
            $price = (float) ($participant->item?->unit_price ?? 0);
            $amount = $portions * $price;
            $identity = collect([
                trim((string) $participant->position),
                filled($participant->nip) ? 'NIP '.trim((string) $participant->nip) : null,
                filled($participant->nuptk) ? 'NUPTK '.trim((string) $participant->nuptk) : null,
            ])->filter()->implode(' / ');

            $participantNumbers[] = (string) ($index + 1);
            $participantNames[] = trim((string) $participant->name) ?: SpjDocumentTypeRegistry::EMPTY_SCALAR_VALUE;
            $participantIdentities[] = $identity ?: SpjDocumentTypeRegistry::EMPTY_SCALAR_VALUE;
            $participantPortions[] = $this->plainNumber($portions);
            $participantPrices[] = $this->rupiahValue($price);
            $participantAmounts[] = $this->rupiahValue($amount);
            $totalConsumption += $amount;
        }

        $fallback = SpjDocumentTypeRegistry::EMPTY_SCALAR_VALUE;

        return $values + [
            'TANGGAL_KEGIATAN' => $eventDate,
            'TEMPAT_KEGIATAN' => $eventLocation !== '' ? $eventLocation : $fallback,
            'NAMA_PENANGGUNG_JAWAB' => $fallback,
            'NIP_PENANGGUNG_JAWAB' => $fallback,
            'KONSUMSI_NO' => $participantNumbers !== [] ? implode("\n", $participantNumbers) : $fallback,
            'KONSUMSI_NAMA' => $participantNames !== [] ? implode("\n", $participantNames) : $fallback,
            'KONSUMSI_IDENTITAS' => $participantIdentities !== [] ? implode("\n", $participantIdentities) : $fallback,
            'KONSUMSI_PORSI' => $participantPortions !== [] ? implode("\n", $participantPortions) : $fallback,
            'KONSUMSI_HARGA_PORSI' => $participantPrices !== [] ? implode("\n", $participantPrices) : $fallback,
            'KONSUMSI_JUMLAH' => $participantAmounts !== [] ? implode("\n", $participantAmounts) : $fallback,
            'TOTAL_KONSUMSI' => $this->rupiahValue($totalConsumption),
        ];
    }

    private function rupiahValue(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    private function plainNumber(float $value): string
    {
        return abs($value - round($value)) < 0.00001
            ? number_format($value, 0, ',', '.')
            : rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }
}
