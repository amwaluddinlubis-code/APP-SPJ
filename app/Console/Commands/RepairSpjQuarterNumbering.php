<?php

namespace App\Console\Commands;

use App\Models\DocumentNumberFormat;
use App\Models\FiscalYear;
use App\Models\School;
use App\Models\SpjDocument;
use App\Services\SchoolDatabaseManager;
use App\Services\SpjDocumentNumberService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RepairSpjQuarterNumbering extends Command
{
    protected $signature = 'spj:repair-quarter-numbering
        {npsn : NPSN sekolah}
        {--year= : Tahun anggaran; default tahun aktif terbaru}
        {--apply : Terapkan perubahan. Tanpa opsi ini hanya preview}';

    protected $description = 'Preview atau perbaiki nomor dokumen NUMBERED menggunakan format aktif dengan placeholder {TW}.';

    public function handle(SchoolDatabaseManager $databases, SpjDocumentNumberService $numbers): int
    {
        $school = School::query()->where('npsn', (string) $this->argument('npsn'))->first();
        if (! $school) {
            $this->error('Sekolah tidak ditemukan.');

            return self::FAILURE;
        }

        $databases->ensureMigrated($school);

        $year = $this->resolveYear();
        if (! $year) {
            $this->error('Tahun anggaran tidak ditemukan pada database sekolah.');

            return self::FAILURE;
        }

        $schoolCode = $school->school_code ?: $school->npsn;
        $apply = (bool) $this->option('apply');
        $rows = [];
        $changes = [];
        $skippedFinal = 0;

        $documents = SpjDocument::query()
            ->with('package.transaction')
            ->whereNotNull('document_number')
            ->whereNotNull('sequence_number')
            ->whereNotNull('document_date')
            ->whereHas('package.transaction', fn ($query) => $query->where('fiscal_year_id', $year->id))
            ->orderBy('document_type')
            ->orderBy('sequence_number')
            ->get();

        foreach ($documents as $document) {
            if ($document->status === 'FINAL') {
                $skippedFinal++;

                continue;
            }
            if ($document->status !== 'NUMBERED') {
                continue;
            }

            $format = DocumentNumberFormat::query()
                ->where('fiscal_year_id', $year->id)
                ->where('document_type', $document->document_type)
                ->first();
            if (! $format || ! str_contains((string) $format->format_pattern, '{TW}')) {
                continue;
            }

            $newNumber = $numbers->renderConfiguredNumber(
                $format,
                (string) $document->document_type,
                (int) $document->sequence_number,
                Carbon::parse($document->document_date),
                (string) $schoolCode,
                (string) $school->npsn,
            );

            if ($newNumber === $document->document_number) {
                continue;
            }

            $rows[] = [
                $document->id,
                $document->document_type,
                $document->document_date?->format('Y-m-d'),
                $document->document_number,
                $newNumber,
            ];
            $changes[] = [$document, $newNumber];
        }

        $this->info('SPJ QUARTER NUMBERING REPAIR — '.($apply ? 'APPLY' : 'PREVIEW'));
        $this->line('Sekolah : '.$school->name.' ('.$school->npsn.')');
        $this->line('Tahun   : '.$year->year);
        $this->line('Perubahan kandidat: '.count($changes));
        if ($skippedFinal > 0) {
            $this->warn("Dokumen FINAL dilewati: {$skippedFinal}. Nomor FINAL tidak diubah otomatis.");
        }

        if ($rows !== []) {
            $this->table(['Doc ID', 'Jenis', 'Tanggal', 'Sebelum', 'Sesudah'], $rows);
        } else {
            $this->info('Tidak ada nomor NUMBERED yang perlu diperbaiki.');
        }

        if (! $apply || $changes === []) {
            if (! $apply && $changes !== []) {
                $this->comment('Preview saja. Jalankan kembali dengan --apply setelah hasil di atas diperiksa.');
            }

            return self::SUCCESS;
        }

        DB::connection('school')->transaction(function () use ($changes): void {
            foreach ($changes as [$document, $newNumber]) {
                $this->applyNumber($document, $newNumber);
            }
        });

        $this->info('Perbaikan nomor berhasil diterapkan: '.count($changes).' dokumen.');

        return self::SUCCESS;
    }

    private function resolveYear(): ?FiscalYear
    {
        $requested = $this->option('year');

        return FiscalYear::query()
            ->when(filled($requested), fn ($query) => $query->where('year', (int) $requested))
            ->orderByDesc('year')
            ->first();
    }

    private function applyNumber(SpjDocument $document, string $newNumber): void
    {
        $document->forceFill(['document_number' => $newNumber])->save();

        $transactionId = $document->package?->transaction_id;
        if (! $transactionId) {
            return;
        }

        if ($document->document_type === 'SPJ' && $document->scope_key === 'MAIN') {
            DB::connection('school')->table('spj_packages')
                ->where('id', $document->spj_package_id)
                ->update(['document_number' => $newNumber, 'updated_at' => now()]);

            return;
        }

        $goodsColumns = [
            'PESANAN' => 'order_number',
            'BAP' => 'bap_number',
            'BAST' => 'bast_number',
        ];
        if (isset($goodsColumns[$document->document_type])) {
            DB::connection('school')->table('spj_goods')
                ->where('transaction_id', $transactionId)
                ->update([$goodsColumns[$document->document_type] => $newNumber, 'updated_at' => now()]);

            return;
        }

        $workOrderColumns = [
            'SPK' => 'spk_number',
            'RAB' => 'rab_number',
        ];
        if (isset($workOrderColumns[$document->document_type])) {
            DB::connection('school')->table('spj_work_orders')
                ->where('transaction_id', $transactionId)
                ->update([$workOrderColumns[$document->document_type] => $newNumber, 'updated_at' => now()]);

            return;
        }

        if ($document->document_type === 'SURAT_TUGAS_PERJALANAN_DINAS'
            && str_starts_with((string) $document->scope_key, 'TRAVEL-')) {
            $travelId = (int) substr((string) $document->scope_key, 7);
            if ($travelId > 0) {
                DB::connection('school')->table('spj_travels')
                    ->where('id', $travelId)
                    ->where('transaction_id', $transactionId)
                    ->update(['assignment_letter_number' => $newNumber, 'updated_at' => now()]);
            }
        }
    }
}
