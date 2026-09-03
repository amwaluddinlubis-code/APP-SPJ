<?php

namespace App\Http\Controllers;

use App\Models\DocumentNumberFormat;
use App\Models\DocumentTemplate;
use App\Models\FiscalYear;
use App\Models\School;
use App\Services\OperationalAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DocumentNumberFormatController extends Controller
{
    /** @var list<string> */
    private const PLACEHOLDERS = ['SEQ', 'TYPE', 'SCHOOL', 'NPSN', 'YEAR', 'MONTH', 'ROMAN_MONTH'];

    public function index(): View
    {
        $year = FiscalYear::query()->findOrFail(session('active_fiscal_year_id'));
        $school = School::query()->findOrFail(session('active_school_id'));
        $formats = DocumentNumberFormat::query()
            ->where('fiscal_year_id', $year->id)
            ->get()
            ->keyBy('document_type');

        return view('document-number-formats.index', [
            'year' => $year,
            'school' => $school,
            'formats' => $formats,
            'documentTypes' => $this->documentTypes($year->id, $formats),
            'placeholders' => self::PLACEHOLDERS,
        ]);
    }

    public function update(Request $request, string $documentType): RedirectResponse
    {
        $documentType = strtoupper(trim($documentType));
        abort_unless((bool) preg_match('/^[A-Z0-9_]{2,40}$/', $documentType), 404);

        $data = $request->validate([
            'format_pattern' => ['required', 'string', 'max:80'],
            'reset_period' => ['required', 'in:YEAR,QUARTER,MONTH,NONE'],
            'padding' => ['required', 'integer', 'between:1,8'],
        ]);
        $this->validatePattern($data['format_pattern']);

        $format = DocumentNumberFormat::query()->updateOrCreate(
            [
                'fiscal_year_id' => (int) session('active_fiscal_year_id'),
                'document_type' => $documentType,
            ],
            [
                ...$data,
                'is_active' => true,
            ]
        );

        app(OperationalAuditService::class)->record(
            (int) session('active_fiscal_year_id'),
            'DOCUMENT_NUMBER_FORMAT',
            $format->id,
            'PERBARUI_FORMAT',
            "Format penomoran {$documentType} diperbarui."
        );

        return back()->with('success', "Format penomoran {$documentType} berhasil disimpan. Perubahan berlaku untuk nomor baru.");
    }

    /**
     * @param  Collection<string, DocumentNumberFormat>  $formats
     * @return Collection<int, string>
     */
    private function documentTypes(int $yearId, Collection $formats): Collection
    {
        return collect(['SPJ', 'PESANAN', 'BAP', 'BAST', 'SPK', 'RAB', 'SURAT_TUGAS_PERJALANAN_DINAS', 'KUITANSI', 'RINCIAN_BELANJA', 'CHECKLIST', 'REKAP_PAJAK', 'INVOICE_PESANAN'])
            ->merge(DocumentTemplate::query()->where('fiscal_year_id', $yearId)->pluck('document_type'))
            ->merge($formats->keys())
            ->map(fn ($type) => strtoupper(trim((string) $type)))
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    private function validatePattern(string $pattern): void
    {
        preg_match_all('/\{([A-Z_]+)\}/', strtoupper($pattern), $matches);
        $tokens = collect($matches[1] ?? [])->unique();
        $unsupported = $tokens->diff(self::PLACEHOLDERS);

        if (! $tokens->contains('SEQ')) {
            throw ValidationException::withMessages(['format_pattern' => 'Format wajib memuat placeholder {SEQ}.']);
        }

        if ($unsupported->isNotEmpty()) {
            throw ValidationException::withMessages([
                'format_pattern' => 'Placeholder tidak dikenal: '.$unsupported->map(fn ($token) => "{{$token}}")->join(', ').'.',
            ]);
        }

        if (preg_match('/\{[^}]*\}|\{[^}]*$/', preg_replace('/\{[A-Z_]+\}/', '', strtoupper($pattern)))) {
            throw ValidationException::withMessages(['format_pattern' => 'Penulisan placeholder tidak valid. Gunakan tanda kurung kurawal seperti {SEQ}.']);
        }
    }
}
