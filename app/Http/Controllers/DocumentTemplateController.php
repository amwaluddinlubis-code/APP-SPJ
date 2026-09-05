<?php

namespace App\Http\Controllers;

use App\Models\DocumentTemplate;
use App\Services\SpjDocumentTypeRegistry;
use App\Services\SpjTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory as WordWriter;
use PhpOffice\PhpWord\PhpWord;

class DocumentTemplateController extends Controller
{
    private const SPJ_CATEGORIES = ['BARANG', 'BELANJA_MODAL', 'KONSUMSI', 'JASA_HONORARIUM', 'HONOR_PEGAWAI', 'UPAH', 'PEMELIHARAAN', 'JASA', 'PERJALANAN_DINAS', 'LAINNYA'];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:all,active,inactive'],
            'category' => ['nullable', 'in:'.implode(',', self::SPJ_CATEGORIES)],
        ]);
        $query = DocumentTemplate::query()->where('fiscal_year_id', session('active_fiscal_year_id'));
        match ($filters['status'] ?? 'all') {
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            default => null,
        };
        if ($filters['category'] ?? null) {
            $category = $filters['category'];
            $query->where(function ($builder) use ($category): void {
                $builder->whereNull('applicable_categories')
                    ->orWhere('applicable_categories', '[]')
                    ->orWhereJsonContains('applicable_categories', $category);
            });
        }

        return view('document-templates.index', [
            'templates' => $query->orderBy('document_type')->orderBy('name')->paginate(15)->withQueryString(),
            'categories' => self::SPJ_CATEGORIES,
            'filters' => $filters,
            'placeholderGroups' => SpjTemplateService::placeholderGroups(),
            'documentTypes' => SpjDocumentTypeRegistry::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'document_type' => ['required', 'string', 'in:'.implode(',', SpjDocumentTypeRegistry::codes())],
            'name' => ['required', 'string', 'max:120'],
            'template' => ['required', 'file', 'mimes:docx,xlsx', 'max:10240'],
            'applicable_categories' => ['nullable', 'array'],
            'applicable_categories.*' => ['string', 'in:'.implode(',', self::SPJ_CATEGORIES)],
        ]);
        $documentType = strtoupper($data['document_type']);
        $extension = strtolower($request->file('template')->getClientOriginalExtension());
        $path = $request->file('template')->storeAs(
            'document-templates/'.session('active_fiscal_year_id'),
            uniqid('tpl_', true).'.'.$extension
        );
        $old = DocumentTemplate::query()->where([
            'fiscal_year_id' => session('active_fiscal_year_id'),
            'document_type' => $documentType,
            'format' => $extension,
        ])->first();
        if ($old) {
            Storage::delete($old->file_path);
        }
        DocumentTemplate::updateOrCreate(
            ['fiscal_year_id' => session('active_fiscal_year_id'), 'document_type' => $documentType, 'format' => $extension],
            ['name' => $data['name'], 'file_path' => $path, 'applicable_categories' => $data['applicable_categories'] ?? [], 'is_active' => true]
        );

        return back()->with('success', 'Template '.$data['name'].' berhasil disimpan.');
    }

    /** Memperbarui status aktif dan kategori yang memakai suatu template. */
    public function updateMapping(Request $request, string $templateId): RedirectResponse
    {
        $template = DocumentTemplate::query()->find($templateId);
        if (! $template || $template->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
            return back()->with('error', 'Template tidak ditemukan.');
        }
        $data = $request->validate([
            'is_active' => ['nullable', 'boolean'],
            'applicable_categories' => ['nullable', 'array'],
            'applicable_categories.*' => ['string', 'in:'.implode(',', self::SPJ_CATEGORIES)],
        ]);
        $template->update(['is_active' => (bool) ($data['is_active'] ?? false), 'applicable_categories' => $data['applicable_categories'] ?? []]);

        return back()->with('success', 'Pemetaan template berhasil diperbarui.');
    }

    public function destroy(string $templateId): RedirectResponse
    {
        $template = DocumentTemplate::query()->find($templateId);
        if (! $template || $template->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
            return back()->with('error', 'Template tidak ditemukan.');
        }
        Storage::delete($template->file_path);
        $template->delete();

        return back()->with('success', 'Template berhasil dihapus.');
    }

    public function sample(string $format)
    {
        abort_unless(in_array($format, ['docx', 'xlsx'], true), 404);
        $directory = storage_path('app/generated-documents');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $path = $directory.'/CONTOH-TEMPLATE-SPJ-'.uniqid().'.'.$format;

        if ($format === 'docx') {
            $word = new PhpWord;
            $section = $word->addSection();
            $section->addText('CONTOH TEMPLATE RINCIAN BELANJA SPJ', ['bold' => true, 'size' => 14]);
            $section->addText('Nomor SPJ: {{NOMOR_SPJ}}');
            $section->addText('No. Bukti: {{NO_BUKTI}}');
            $section->addText('Penerima: {{NAMA_PENERIMA}}');
            $table = $section->addTable(['borderSize' => 6, 'borderColor' => '64748B']);
            $headingRow = $table->addRow();
            foreach (['No', 'Uraian', 'Volume', 'Satuan', 'Harga', 'Jumlah'] as $heading) {
                $headingRow->addCell()->addText($heading);
            }
            $row = $table->addRow();
            foreach (['{{ITEM_NO}}', '{{ITEM_URAIAN}}', '{{ITEM_VOLUME}}', '{{ITEM_SATUAN}}', '{{ITEM_HARGA_SATUAN}}', '{{ITEM_JUMLAH}}'] as $marker) {
                $row->addCell()->addText($marker);
            }
            $section->addText('Total bruto: {{NILAI_BRUTO}}');
            WordWriter::createWriter($word, 'Word2007')->save($path);
        } else {
            $book = new Spreadsheet;
            $sheet = $book->getActiveSheet()->setTitle('Rincian Belanja');
            $sheet->setCellValue('A1', 'CONTOH TEMPLATE RINCIAN BELANJA SPJ');
            $sheet->setCellValue('A2', 'Nomor SPJ: {{NOMOR_SPJ}}');
            $sheet->setCellValue('A3', 'No. Bukti: {{NO_BUKTI}}');
            $sheet->fromArray(['No', 'Uraian', 'Volume', 'Satuan', 'Harga', 'Jumlah'], null, 'A5');
            $sheet->fromArray(['{{ITEM_NO}}', '{{ITEM_URAIAN}}', '{{ITEM_VOLUME}}', '{{ITEM_SATUAN}}', '{{ITEM_HARGA_SATUAN}}', '{{ITEM_JUMLAH}}'], null, 'A6');
            $sheet->setCellValue('E8', 'Total bruto');
            $sheet->setCellValue('F8', '{{NILAI_BRUTO}}');
            foreach (range('A', 'F') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
            (new Xlsx($book))->save($path);
        }

        return response()->download($path, 'CONTOH-TEMPLATE-SPJ.'.$format)->deleteFileAfterSend(true);
    }
}
