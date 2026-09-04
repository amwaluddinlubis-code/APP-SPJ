<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Models\FiscalYear;
use App\Models\School;
use App\Models\SpjPackage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Html;
use PhpOffice\PhpWord\TemplateProcessor;

class SpjTemplateService
{
    /** @return array<string,array<int,string>> */
    public static function placeholderGroups(): array
    {
        return [
            'Dokumen & periode' => ['NOMOR_SPJ', 'NOMOR_DOKUMEN', 'NO_BUKTI', 'NOMOR_BUKTI', 'TANGGAL_TRANSAKSI', 'TANGGAL_DOKUMEN', 'TAHUN_ANGGARAN', 'SUMBER_DANA', 'SUMBER_DANA_PERIODE', 'TRIWULAN', 'SEMESTER', 'JENIS_SPJ'],
            'Sekolah & pejabat' => ['NAMA_SEKOLAH', 'NAMA_SATUAN_PENDIDIKAN', 'NPSN', 'ALAMAT_SEKOLAH', 'KECAMATAN', 'KABUPATEN_KOTA', 'PROVINSI', 'KOP_SURAT', 'NAMA_KEPALA_SEKOLAH', 'NIP_KEPALA_SEKOLAH', 'NAMA_BENDAHARA_BOSP', 'NIP_BENDAHARA_BOSP'],
            'Penerima & penyedia' => ['NAMA_PENERIMA', 'NAMA_PENERIMA_BKU', 'NAMA_PENERIMA_KUITANSI', 'PENERIMA_PENYEDIA', 'NAMA_PENYEDIA', 'ALAMAT_PENYEDIA', 'NPWP_PENYEDIA', 'TELEPON_PENYEDIA', 'NAMA_PENANDATANGAN', 'JABATAN_PENANDATANGAN', 'SUDAH_TERIMA_DARI'],
            'Transaksi & pembayaran' => ['KODE_KEGIATAN', 'NAMA_KEGIATAN', 'KODE_REKENING', 'NAMA_REKENING', 'URAIAN_TRANSAKSI', 'UNTUK_PEMBAYARAN', 'CARA_BAYAR', 'REFERENSI_BAYAR', 'CARA_BAYAR_REFERENSI'],
            'Pembelian SiPLah' => ['SIPLAH_NOMOR_PESANAN', 'SIPLAH_PENYEDIA', 'SIPLAH_NOMOR_INVOICE', 'SIPLAH_TANGGAL_INVOICE', 'SIPLAH_REFERENSI_BAYAR'],
            'Pesanan & pekerjaan' => ['NOMOR_PESANAN', 'TANGGAL_PESANAN', 'NOMOR_INVOICE', 'TANGGAL_INVOICE', 'STATUS_INVOICE', 'NOMOR_SPK', 'TANGGAL_SPK', 'NOMOR_RAB', 'TANGGAL_RAB', 'URAIAN_PEKERJAAN', 'LOKASI_PEKERJAAN', 'TANGGAL_MULAI', 'TANGGAL_SELESAI', 'TANGGAL_TANDA_TANGAN', 'TANGGAL_PENYERAHAN', 'TEMPAT_PENYERAHAN'],
            'Nilai & pajak' => ['NILAI_BRUTO', 'NILAI_PEKERJAAN', 'NILAI_PEKERJAAN_TERBILANG', 'PPN', 'PPH21', 'PPH22', 'PPH23', 'PPH4', 'SSPD', 'TOTAL_PAJAK', 'POTONGAN_PAJAK', 'NILAI_DIBAYARKAN', 'TERBILANG_NETO'],
            'Ringkasan' => ['RINCIAN_BELANJA', 'RINCIAN_UPAH'],
            'Baris rincian barang' => ['ITEM_NO', 'ITEM_URAIAN', 'ITEM_VOLUME', 'ITEM_SATUAN', 'ITEM_HARGA_SATUAN', 'ITEM_JUMLAH', 'ITEM_KODE_REKENING', 'ITEM_NAMA_REKENING'],
            'Baris upah/honor' => ['UPAH_NO', 'UPAH_NAMA', 'UPAH_PEKERJAAN', 'UPAH_HARI', 'UPAH_TARIF_HARI', 'UPAH_JUMLAH', 'UPAH_PENERIMA_KUITANSI'],
        ];
    }

    /** @return array<string,string> */
    public function placeholders(SpjPackage $package, School $school): array
    {
        $transaction = $package->transaction;
        $transaction->loadMissing(['items', 'goods', 'workOrder', 'workers']);

        $year = FiscalYear::query()->findOrFail($transaction->fiscal_year_id);
        $profile = DB::connection('school')->table('school_profiles')->where('fiscal_year_id', $year->id)->first();
        $goods = $transaction->goods->first();
        $workOrder = $transaction->workOrder;
        $items = $transaction->items->map(fn ($item, $index) => ($index + 1).'. '.($item->item_description ?: $item->description).' | '.$item->quantity.' '.($item->unit ?: '—').' | '.$this->rupiah($item->amount))->implode("\n");

        $transactionDate = $transaction->transaction_date?->translatedFormat('d F Y') ?: '';
        $orderDate = $goods?->order_date?->translatedFormat('d F Y') ?: '';
        $spkDate = $workOrder?->spk_date?->translatedFormat('d F Y') ?: '';
        $rabDate = $workOrder?->rab_date?->translatedFormat('d F Y') ?: '';
        $workStarted = $workOrder?->work_started_at?->translatedFormat('d F Y') ?: '';
        $workCompleted = $workOrder?->work_completed_at?->translatedFormat('d F Y') ?: '';
        $handoverDate = $goods?->bast_date?->translatedFormat('d F Y') ?: ($workCompleted ?: $transactionDate);
        $vendorName = (string) ($transaction->vendor_name ?: $transaction->effective_receipt_recipient_name);
        $paymentMethod = $this->paymentMethodLabel((string) $transaction->payment_method);

        $values = [
            'NOMOR_SPJ' => (string) $package->document_number,
            'NO_BUKTI' => (string) $transaction->no_bukti,
            'TANGGAL_TRANSAKSI' => $transactionDate,
            'TAHUN_ANGGARAN' => (string) $year->year,
            'SUMBER_DANA' => (string) $year->fund_source,
            'TRIWULAN' => (string) $package->quarter_code,
            'SEMESTER' => (string) $package->semester_code,
            'NAMA_SEKOLAH' => (string) $school->name,
            'NPSN' => (string) $school->npsn,
            'ALAMAT_SEKOLAH' => (string) $school->address,
            'KECAMATAN' => (string) $school->district,
            'KABUPATEN_KOTA' => (string) $school->regency,
            'PROVINSI' => (string) $school->province,
            'NAMA_KEPALA_SEKOLAH' => (string) ($profile->principal_name ?? ''),
            'NIP_KEPALA_SEKOLAH' => (string) ($profile->principal_nip ?? ''),
            'NAMA_BENDAHARA_BOSP' => (string) ($profile->treasurer_name ?? ''),
            'NIP_BENDAHARA_BOSP' => (string) ($profile->treasurer_nip ?? ''),
            'NAMA_PENERIMA' => (string) $transaction->effective_receipt_recipient_name,
            'NAMA_PENERIMA_BKU' => (string) $transaction->recipient_name,
            'NAMA_PENERIMA_KUITANSI' => (string) $transaction->effective_receipt_recipient_name,
            'PENERIMA_PENYEDIA' => $vendorName,
            'NAMA_PENYEDIA' => $vendorName,
            'NPWP_PENYEDIA' => (string) $transaction->vendor_npwp,
            'KODE_KEGIATAN' => (string) $transaction->activity_code,
            'NAMA_KEGIATAN' => (string) $transaction->activity_name,
            'KODE_REKENING' => (string) $transaction->account_code,
            'NAMA_REKENING' => (string) $transaction->account_name,
            'URAIAN_TRANSAKSI' => (string) $transaction->description,
            'UNTUK_PEMBAYARAN' => (string) ($transaction->payment_description ?: $transaction->description),
            'CARA_BAYAR' => $paymentMethod,
            'REFERENSI_BAYAR' => (string) $transaction->payment_reference,
            'SIPLAH_NOMOR_PESANAN' => (string) $transaction->siplah_order_number,
            'SIPLAH_PENYEDIA' => (string) $transaction->vendor_name,
            'SIPLAH_NOMOR_INVOICE' => (string) $transaction->invoice_number,
            'SIPLAH_TANGGAL_INVOICE' => $transaction->invoice_date?->translatedFormat('d F Y') ?: '',
            'SIPLAH_REFERENSI_BAYAR' => (string) $transaction->payment_reference,
            'NOMOR_PESANAN' => (string) ($goods?->order_number ?: ''),
            'TANGGAL_PESANAN' => $orderDate,
            'NOMOR_INVOICE' => (string) $transaction->invoice_number,
            'TANGGAL_INVOICE' => $transaction->invoice_date?->translatedFormat('d F Y') ?: '',
            'STATUS_INVOICE' => (string) $transaction->invoice_status,
            'NOMOR_SPK' => (string) ($workOrder?->spk_number ?: ''),
            'TANGGAL_SPK' => $spkDate,
            'NOMOR_RAB' => (string) ($workOrder?->rab_number ?: ''),
            'TANGGAL_RAB' => $rabDate ?: $transactionDate,
            'URAIAN_PEKERJAAN' => (string) ($workOrder?->work_description ?: $transaction->payment_description ?: $transaction->description),
            'LOKASI_PEKERJAAN' => (string) ($workOrder?->work_location ?: ''),
            'TANGGAL_MULAI' => $workStarted,
            'TANGGAL_SELESAI' => $workCompleted,
            'TANGGAL_TANDA_TANGAN' => $transactionDate,
            'TANGGAL_PENYERAHAN' => $handoverDate,
            'TEMPAT_PENYERAHAN' => (string) ($workOrder?->work_location ?: $transaction->event_location ?: $school->address),
            'NAMA_PENANDATANGAN' => (string) ($transaction->signatory_name ?: $transaction->effective_receipt_recipient_name),
            'JABATAN_PENANDATANGAN' => (string) $transaction->signatory_role,
            'NILAI_BRUTO' => $this->rupiah($transaction->gross_amount),
            'PPN' => $this->rupiah($transaction->ppn),
            'PPH21' => $this->rupiah($transaction->pph21),
            'PPH22' => $this->rupiah($transaction->pph22),
            'PPH23' => $this->rupiah($transaction->pph23),
            'PPH4' => $this->rupiah($transaction->pph4),
            'SSPD' => $this->rupiah($transaction->sspd),
            'TOTAL_PAJAK' => $this->rupiah($transaction->tax_total),
            'NILAI_DIBAYARKAN' => $this->rupiah($transaction->net_amount),
            'RINCIAN_BELANJA' => $items,
            'RINCIAN_UPAH' => $transaction->workers->map(fn ($worker, $index) => ($index + 1).'. '.$worker->name.' | '.$worker->job_description.' | '.$worker->work_days.' hari × '.$this->rupiah($worker->daily_rate).' = '.$this->rupiah($worker->amount))->implode("\n"),
        ];

        return $values + [
            'NOMOR_DOKUMEN' => $values['NOMOR_SPJ'],
            'TANGGAL_DOKUMEN' => $values['TANGGAL_TRANSAKSI'],
            'NOMOR_BUKTI' => $values['NO_BUKTI'],
            'SUMBER_DANA_PERIODE' => trim($values['SUMBER_DANA'].' / '.$values['TAHUN_ANGGARAN'].' / '.$values['TRIWULAN']),
            'JENIS_SPJ' => (string) $transaction->spj_category,
            'POTONGAN_PAJAK' => $values['TOTAL_PAJAK'],
            'NAMA_SATUAN_PENDIDIKAN' => $values['NAMA_SEKOLAH'],
            'SUDAH_TERIMA_DARI' => 'Bendahara Dana BOSP '.$values['NAMA_SEKOLAH'],
            'CARA_BAYAR_REFERENSI' => trim($values['CARA_BAYAR'].' / '.($values['REFERENSI_BAYAR'] ?: 'Belum ada referensi'), ' /'),
            'TERBILANG_NETO' => $this->terbilang((float) $transaction->net_amount),
            'NILAI_PEKERJAAN' => $values['NILAI_BRUTO'],
            'NILAI_PEKERJAAN_TERBILANG' => $this->terbilang((float) $transaction->gross_amount),
            // Field ini belum tersedia pada schema transaksi saat ini. Tetap dipetakan
            // sebagai placeholder resmi agar template tidak menyisakan marker mentah.
            'ALAMAT_PENYEDIA' => '',
            'TELEPON_PENYEDIA' => '',
            // KOP_SURAT ditangani sebagai gambar oleh fillExcelLetterhead().
            'KOP_SURAT' => '',
        ];
    }

    public function download(DocumentTemplate $template, SpjPackage $package, School $school)
    {
        $source = $this->templateSourcePath($template);
        if (! is_file($source)) {
            throw new \RuntimeException('Berkas template tidak ditemukan. Unggah ulang template ini.');
        }
        $values = $this->placeholders($package, $school);
        $extension = strtolower($template->format);
        $output = storage_path('app/generated-documents/'.uniqid('spj_', true).'.'.$extension);
        if (! is_dir(dirname($output))) {
            mkdir(dirname($output), 0775, true);
        }

        if ($extension === 'docx') {
            $document = new TemplateProcessor($source);
            $document->setMacroChars('{{', '}}');
            $this->fillWordItems($document, $package);
            $this->fillWordWorkers($document, $package);
            foreach ($values as $key => $value) {
                $document->setValue($key, $value);
            }
            $document->saveAs($output);
        } elseif ($extension === 'xlsx') {
            $spreadsheet = IOFactory::load($source);
            foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                $this->fillExcelItems($sheet, $package);
                $this->fillExcelWorkers($sheet, $package);
                $this->fillExcelLetterhead($sheet, $school);
                foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
                    $cell = $sheet->getCell($coordinate);
                    if (! is_string($cell->getValue())) {
                        continue;
                    }
                    $cell->setValue(strtr($cell->getValue(), array_combine(array_map(fn ($key) => '{{'.$key.'}}', array_keys($values)), array_values($values))));
                }
            }
            IOFactory::createWriter($spreadsheet, 'Xlsx')->save($output);
        } else {
            throw new \RuntimeException('Format template tidak didukung.');
        }

        return response()->download($output, $this->safeName($template->document_type.'-'.$package->document_number.'.'.$extension))->deleteFileAfterSend(true);
    }

    /** Menghasilkan HTML dari template Excel untuk pratinjau di browser. */
    public function previewHtml(DocumentTemplate $template, SpjPackage $package, School $school): ?string
    {
        if (strtolower($template->format) !== 'xlsx') {
            return null;
        }
        $source = $this->templateSourcePath($template);
        if (! is_file($source)) {
            throw new \RuntimeException('Berkas template tidak ditemukan. Unggah ulang template ini.');
        }
        $values = $this->placeholders($package, $school);
        $spreadsheet = IOFactory::load($source);
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $this->fillExcelItems($sheet, $package);
            $this->fillExcelWorkers($sheet, $package);
            $this->fillExcelLetterhead($sheet, $school);
            foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
                $cell = $sheet->getCell($coordinate);
                if (! is_string($cell->getValue())) {
                    continue;
                }
                $cell->setValue(strtr($cell->getValue(), array_combine(array_map(fn ($key) => '{{'.$key.'}}', array_keys($values)), array_values($values))));
            }
        }
        $writer = new Html($spreadsheet);
        $writer->setSheetIndex(0)->setEmbedImages(true)->setUseInlineCss(true);

        return $writer->generateHtmlAll();
    }

    private function rupiah(mixed $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }

    private function paymentMethodLabel(string $value): string
    {
        return match (strtolower(trim($value))) {
            'transfer_bank' => 'Transfer Bank (CMS / Non Tunai)',
            'siplah' => 'SiPLah Kemdikbud',
            'tunai' => 'Tunai Kas BOS',
            default => $value,
        };
    }

    /** Resolve unggahan pada disk local Laravel, dengan fallback template legacy. */
    private function templateSourcePath(DocumentTemplate $template): string
    {
        $relativePath = ltrim((string) $template->file_path, '/\\');
        $disk = Storage::disk('local');
        if ($disk->exists($relativePath)) {
            return $disk->path($relativePath);
        }

        return storage_path('app/'.$relativePath);
    }

    private function safeName(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?: 'dokumen-spj';
    }

    /** Sisipkan gambar kop pada sel penanda tanpa mengubah tata letak TPL. */
    private function fillExcelLetterhead(Worksheet $sheet, School $school): void
    {
        $relativePath = $school->letterhead_path;
        $disk = Storage::disk('local');
        if (blank($relativePath) || ! $disk->exists($relativePath)) {
            return;
        }
        $path = $disk->path($relativePath);
        foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
            if (trim((string) $sheet->getCell($coordinate)->getValue()) !== '{{KOP_SURAT}}') {
                continue;
            }
            $sheet->setCellValue($coordinate, '');
            $drawing = new Drawing;
            $drawing->setPath($path);
            $drawing->setCoordinates($coordinate);
            $drawing->setHeight(115);
            $drawing->setOffsetX(2);
            $drawing->setOffsetY(2);
            $drawing->setWorksheet($sheet);
            break;
        }
    }

    /** Konversi nilai rupiah ke terbilang Indonesia untuk kuitansi dan SPK. */
    private function terbilang(float $amount): string
    {
        $number = (int) round(abs($amount));
        if ($number === 0) {
            return 'Nol rupiah';
        }
        $words = $this->terbilangNumber($number);

        return ucfirst(trim($words)).' rupiah';
    }

    private function terbilangNumber(int $number): string
    {
        $basic = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
        if ($number < 12) {
            return $basic[$number];
        }
        if ($number < 20) {
            return $this->terbilangNumber($number - 10).' belas';
        }
        if ($number < 100) {
            return $this->terbilangNumber(intdiv($number, 10)).' puluh '.$this->terbilangNumber($number % 10);
        }
        if ($number < 200) {
            return 'seratus '.$this->terbilangNumber($number - 100);
        }
        if ($number < 1000) {
            return $this->terbilangNumber(intdiv($number, 100)).' ratus '.$this->terbilangNumber($number % 100);
        }
        if ($number < 2000) {
            return 'seribu '.$this->terbilangNumber($number - 1000);
        }
        if ($number < 1000000) {
            return $this->terbilangNumber(intdiv($number, 1000)).' ribu '.$this->terbilangNumber($number % 1000);
        }
        if ($number < 1000000000) {
            return $this->terbilangNumber(intdiv($number, 1000000)).' juta '.$this->terbilangNumber($number % 1000000);
        }
        if ($number < 1000000000000) {
            return $this->terbilangNumber(intdiv($number, 1000000000)).' miliar '.$this->terbilangNumber($number % 1000000000);
        }

        return $this->terbilangNumber(intdiv($number, 1000000000000)).' triliun '.$this->terbilangNumber($number % 1000000000000);
    }

    private function itemValues(SpjPackage $package, int $index): array
    {
        $item = $package->transaction->items[$index - 1];

        return [
            'ITEM_NO' => (string) $index,
            'ITEM_URAIAN' => (string) ($item->item_description ?: $item->description),
            'ITEM_VOLUME' => (string) $item->quantity,
            'ITEM_SATUAN' => (string) ($item->unit ?: '—'),
            'ITEM_HARGA_SATUAN' => $this->rupiah($item->unit_price),
            'ITEM_JUMLAH' => $this->rupiah($item->amount),
            'ITEM_KODE_REKENING' => (string) ($item->account_code ?: $package->transaction->account_code),
            'ITEM_NAMA_REKENING' => (string) ($item->account_name ?: $package->transaction->account_name),
        ];
    }

    private function fillWordItems(TemplateProcessor $document, SpjPackage $package): void
    {
        $items = $package->transaction->items;
        if ($items->isEmpty() || ! in_array('ITEM_NO', $document->getVariables(), true)) {
            return;
        }
        $document->cloneRow('ITEM_NO', $items->count());
        foreach ($items as $index => $item) {
            foreach ($this->itemValues($package, $index + 1) as $key => $value) {
                $document->setValue($key.'#'.($index + 1), $value);
            }
        }
    }

    private function fillExcelItems(Worksheet $sheet, SpjPackage $package): void
    {
        $rows = [];
        foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
            if (str_contains((string) $sheet->getCell($coordinate)->getValue(), '{{ITEM_NO}}')) {
                $rows[] = $sheet->getCell($coordinate)->getRow();
            }
        }
        $rows = array_values(array_unique($rows));
        $items = $package->transaction->items;
        if (empty($rows) || $items->isEmpty()) {
            return;
        }
        $row = $rows[0];
        $highestColumn = $sheet->getHighestColumn();
        $lastColumn = Coordinate::columnIndexFromString($highestColumn);
        $original = [];
        for ($column = 1; $column <= $lastColumn; $column++) {
            $original[$column] = $sheet->getCell(Coordinate::stringFromColumnIndex($column).$row)->getValue();
        }
        if ($items->count() > count($rows)) {
            $extra = $items->count() - count($rows);
            $insertAt = max($rows) + 1;
            $sheet->insertNewRowBefore($insertAt, $extra);
            for ($copyRow = $insertAt; $copyRow < $insertAt + $extra; $copyRow++) {
                $sheet->duplicateStyle($sheet->getStyle($row), 'A'.$copyRow.':'.$highestColumn.$copyRow);
                $sheet->getRowDimension($copyRow)->setRowHeight($sheet->getRowDimension($row)->getRowHeight());
                $rows[] = $copyRow;
            }
        }
        sort($rows);
        foreach ($items as $index => $item) {
            $replacements = [];
            foreach ($this->itemValues($package, $index + 1) as $key => $value) {
                $replacements['{{'.$key.'}}'] = $value;
            }
            foreach ($original as $column => $value) {
                if (is_string($value)) {
                    $sheet->getCell(Coordinate::stringFromColumnIndex($column).$rows[$index])->setValue(strtr($value, $replacements));
                }
            }
        }
        foreach (array_slice($rows, $items->count()) as $emptyRow) {
            foreach ($original as $column => $value) {
                if (is_string($value) && str_contains($value, '{{ITEM_')) {
                    $sheet->getCell(Coordinate::stringFromColumnIndex($column).$emptyRow)->setValue('');
                }
            }
        }
    }

    private function workerValues(SpjPackage $package, int $index): array
    {
        $worker = $package->transaction->workers[$index - 1];

        return [
            'UPAH_NO' => (string) $index,
            'UPAH_NAMA' => (string) $worker->name,
            'UPAH_PEKERJAAN' => (string) $worker->job_description,
            'UPAH_HARI' => (string) $worker->work_days,
            'UPAH_TARIF_HARI' => $this->rupiah($worker->daily_rate),
            'UPAH_JUMLAH' => $this->rupiah($worker->amount),
            'UPAH_PENERIMA_KUITANSI' => $worker->is_receipt_recipient ? 'YA' : 'TIDAK',
        ];
    }

    private function fillWordWorkers(TemplateProcessor $document, SpjPackage $package): void
    {
        $workers = $package->transaction->workers;
        if ($workers->isEmpty() || ! in_array('UPAH_NO', $document->getVariables(), true)) {
            return;
        }
        $document->cloneRow('UPAH_NO', $workers->count());
        foreach ($workers as $index => $worker) {
            foreach ($this->workerValues($package, $index + 1) as $key => $value) {
                $document->setValue($key.'#'.($index + 1), $value);
            }
        }
    }

    private function fillExcelWorkers(Worksheet $sheet, SpjPackage $package): void
    {
        $row = null;
        foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
            if (str_contains((string) $sheet->getCell($coordinate)->getValue(), '{{UPAH_NO}}')) {
                $row = $sheet->getCell($coordinate)->getRow();
                break;
            }
        }
        $workers = $package->transaction->workers;
        if (! $row || $workers->isEmpty()) {
            return;
        }
        $highestColumn = $sheet->getHighestColumn();
        $lastColumn = Coordinate::columnIndexFromString($highestColumn);
        $original = [];
        for ($column = 1; $column <= $lastColumn; $column++) {
            $original[$column] = $sheet->getCell(Coordinate::stringFromColumnIndex($column).$row)->getValue();
        }
        if ($workers->count() > 1) {
            $sheet->insertNewRowBefore($row + 1, $workers->count() - 1);
            for ($copyRow = $row + 1; $copyRow < $row + $workers->count(); $copyRow++) {
                $sheet->duplicateStyle($sheet->getStyle($row), 'A'.$copyRow.':'.$highestColumn.$copyRow);
                $sheet->getRowDimension($copyRow)->setRowHeight($sheet->getRowDimension($row)->getRowHeight());
            }
        }
        foreach ($workers as $index => $worker) {
            $replacements = [];
            foreach ($this->workerValues($package, $index + 1) as $key => $value) {
                $replacements['{{'.$key.'}}'] = $value;
            }
            foreach ($original as $column => $value) {
                if (is_string($value)) {
                    $sheet->getCell(Coordinate::stringFromColumnIndex($column).($row + $index))->setValue(strtr($value, $replacements));
                }
            }
        }
    }
}
