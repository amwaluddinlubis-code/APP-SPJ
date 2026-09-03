<?php

namespace App\Http\Controllers;

use App\Models\DocumentTemplate;
use App\Models\FiscalYear;
use App\Models\School;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Services\OperationalAuditService;
use App\Services\SpjPackageValidationService;
use App\Services\SpjPdfService;
use App\Services\SpjTemplateService;
use App\Services\SpjTransactionDetailsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SpjDocumentController extends Controller
{
    public function index(Request $request): View
    {
        $yearId = (int) session('active_fiscal_year_id');
        $filters = $request->validate([
            'month' => ['nullable', 'integer', 'between:1,12'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'spj_category' => ['nullable', 'string', 'max:40'],
            'state' => ['nullable', 'in:all,ready,unprepared,draft,numbered'],
        ]);
        $query = Transaction::query()->activeContext()
            ->when($filters['month'] ?? null, fn ($q, $month) => $q->whereMonth('transaction_date', $month))
            ->when($filters['quarter'] ?? null, fn ($q, $quarter) => $q->whereMonth('transaction_date', '>=', ((int) $quarter - 1) * 3 + 1)->whereMonth('transaction_date', '<=', (int) $quarter * 3))
            ->when($filters['spj_category'] ?? null, fn ($q, $type) => $q->where('spj_category', $type));

        match ($filters['state'] ?? 'all') {
            'ready' => $query->has('items'),
            'unprepared' => $query->doesntHave('spjPackage'),
            'draft' => $query->whereHas('spjPackage', fn ($q) => $q->whereNull('document_number')),
            'numbered' => $query->whereHas('spjPackage', fn ($q) => $q->whereNotNull('document_number')),
            default => null,
        };

        $perPageRaw = $request->input('perPage', 15);
        $perPage = $perPageRaw === 'all' ? 10000 : (int) $perPageRaw;
        $perPage = in_array($perPage, [15, 25, 50, 100, 10000]) ? $perPage : 15;
        $transactions = $query
            ->with('spjPackage')->withCount('items')
            ->latest('transaction_date')->latest('id')->paginate($perPage)->withQueryString();
        $packages = SpjPackage::query()->whereHas('transaction', fn ($query) => $query->activeContext());

        return view('spj-documents.index', [
            'transactions' => $transactions,
            'totalPackages' => (clone $packages)->count(),
            'numberedPackages' => (clone $packages)->whereNotNull('document_number')->count(),
            'readyTransactions' => Transaction::query()->activeContext()->has('items')->count(),
            'spjTypes' => Transaction::query()->activeContext()->whereNotNull('spj_category')->where('spj_category', '!=', '')->distinct()->orderBy('spj_category')->pluck('spj_category'),
            'filters' => $filters,
        ]);
    }

    public function prepare(string $transactionId): RedirectResponse
    {
        $transaction = Transaction::query()->withCount('items')->find($transactionId);
        if (! $transaction || $transaction->fiscal_year_id !== (int) session('active_fiscal_year_id') || (int) $transaction->fund_source_id !== (int) session('active_fund_source_id')) {
            return back()->with('error', 'Transaksi tidak ditemukan pada tahun anggaran aktif.');
        }
        if ($transaction->items_count < 1) {
            return back()->with('error', 'Paket belum dapat dibuat karena transaksi belum memiliki rincian barang/jasa.');
        }

        $package = SpjPackage::firstOrCreate(
            ['transaction_id' => $transaction->id],
            ['quarter_code' => $this->quarter($transaction), 'semester_code' => $this->semester($transaction), 'status' => 'DRAFT']
        );
        app(OperationalAuditService::class)->record($transaction->fiscal_year_id, 'SPJ_PACKAGE', $package->id, 'SIAPKAN_PAKET', 'Paket SPJ disiapkan untuk bukti '.$transaction->no_bukti);

        return redirect()->route('spj-documents.show', $package->id)->with('success', 'Paket dokumen SPJ telah disiapkan.');
    }

    public function show(string $packageId, SpjPackageValidationService $validator): View|RedirectResponse
    {
        $package = SpjPackage::query()->with($this->packageRelations())->find($packageId);
        if (! $package || $package->transaction->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
            return redirect()->route('spj-documents.index')->with('error', 'Paket dokumen tidak ditemukan pada tahun anggaran aktif.');
        }

        $validationIssues = $validator->validate($package);
        $category = strtoupper((string) ($package->transaction->spj_category ?: $package->transaction->spj_category));
        $templates = DocumentTemplate::query()->where(['fiscal_year_id' => session('active_fiscal_year_id'), 'is_active' => true])->orderBy('document_type')->get()
            ->filter(fn (DocumentTemplate $template) => empty($template->applicable_categories) || in_array('SEMUA', $template->applicable_categories, true) || in_array($category, $template->applicable_categories, true));

        return view('spj-documents.show', compact('package', 'validationIssues', 'templates'));
    }

    public function assignNumber(string $packageId): RedirectResponse
    {
        $package = SpjPackage::query()->with('transaction')->find($packageId);
        if (! $package || $package->transaction->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
            return redirect()->route('spj-documents.index')->with('error', 'Paket dokumen tidak ditemukan pada tahun anggaran aktif.');
        }
        if ($package->document_number) {
            return back()->with('success', 'Nomor dokumen SPJ sudah ditetapkan.');
        }

        $year = FiscalYear::query()->findOrFail($package->transaction->fiscal_year_id);
        $school = School::query()->findOrFail(session('active_school_id'));
        $quarter = $package->quarter_code ?: $this->quarter($package->transaction);
        $periodKey = $year->year.'-'.$quarter;

        DB::connection('school')->transaction(function () use ($package, $year, $school, $quarter, $periodKey): void {
            $sequence = DB::connection('school')->table('document_number_sequences')
                ->where(['fiscal_year_id' => $year->id, 'format_name' => 'SPJ', 'period_key' => $periodKey])
                ->lockForUpdate()->first();
            $next = ((int) ($sequence->last_number ?? 0)) + 1;

            if ($sequence) {
                DB::connection('school')->table('document_number_sequences')->where('id', $sequence->id)->update(['last_number' => $next, 'updated_at' => now()]);
            } else {
                DB::connection('school')->table('document_number_sequences')->insert(['fiscal_year_id' => $year->id, 'format_name' => 'SPJ', 'period_key' => $periodKey, 'last_number' => $next, 'created_at' => now(), 'updated_at' => now()]);
            }

            $package->forceFill([
                'document_number' => sprintf('%04d/SPJ-BOSP/%s/%s/%d', $next, $school->npsn, $quarter, $year->year),
                'quarter_code' => $quarter,
                'semester_code' => $package->semester_code ?: $this->semester($package->transaction),
                'status' => 'BERNOMOR',
                'numbered_at' => now(),
            ])->save();
        });
        app(OperationalAuditService::class)->record($package->transaction->fiscal_year_id, 'SPJ_PACKAGE', $package->id, 'TETAPKAN_NOMOR', 'Nomor SPJ '.$package->document_number.' ditetapkan.');

        return back()->with('success', 'Nomor dokumen SPJ berhasil ditetapkan.');
    }

    public function updateDetails(string $packageId, Request $request): RedirectResponse
    {
        $package = SpjPackage::query()->with('transaction')->find($packageId);
        if (! $package || $package->transaction->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
            return redirect()->route('spj-documents.index')->with('error', 'Paket dokumen tidak ditemukan pada tahun anggaran aktif.');
        }

        $data = $request->validate([
            'payment_description' => ['nullable', 'string', 'max:4000'],
            'payment_method' => ['nullable', 'string', 'max:4000'],
            'payment_reference' => ['nullable', 'string', 'max:160'],
            'spj_category' => ['nullable', 'string', 'max:40'],
            'invoice_number' => ['nullable', 'string', 'max:80'],
            'invoice_date' => ['nullable', 'date'],
            'invoice_status' => ['nullable', 'string', 'max:30'],
            'work_description' => ['nullable', 'string', 'max:4000'],
            'work_location' => ['nullable', 'string', 'max:180'],
            'work_started_at' => ['nullable', 'date'],
            'work_completed_at' => ['nullable', 'date', 'after_or_equal:work_started_at'],
            'spk_number' => ['nullable', 'string', 'max:80'],
            'spk_date' => ['nullable', 'date'],
            'signatory_name' => ['nullable', 'string', 'max:180'],
            'signatory_role' => ['nullable', 'string', 'max:80'],
            'workers' => ['nullable', 'array'],
            'workers.*.name' => ['nullable', 'string', 'max:180'],
            'workers.*.job_description' => ['nullable', 'string', 'max:255'],
            'workers.*.work_days' => ['nullable', 'numeric', 'min:0'],
            'workers.*.daily_rate' => ['nullable', 'numeric', 'min:0'],
            'workers.*.is_receipt_recipient' => ['nullable', 'boolean'],
            'workers.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $workers = $data['workers'] ?? [];
        $package->transaction->fill(collect($data)->only([
            'payment_description', 'payment_method', 'payment_reference', 'spj_category',
            'invoice_number', 'invoice_date', 'invoice_status',
            'signatory_name', 'signatory_role',
        ])->all())->save();
        $package->transaction->load('items');
        app(SpjTransactionDetailsService::class)->synchronize($package->transaction, $data);
        $workOrder = $package->transaction->workOrder;
        $workOrder?->workers()->delete();
        $receiptRecipient = null;
        foreach ($workers as $sortOrder => $worker) {
            if (! $workOrder || blank($worker['name'] ?? null)) {
                continue;
            }
            $days = (float) ($worker['work_days'] ?? 0);
            $rate = (float) ($worker['daily_rate'] ?? 0);
            $isRecipient = (bool) ($worker['is_receipt_recipient'] ?? false);
            $workOrder->workers()->create([
                'name' => $worker['name'],
                'job_description' => $worker['job_description'] ?? null,
                'work_days' => $days,
                'daily_rate' => $rate,
                'amount' => $days * $rate,
                'is_receipt_recipient' => $isRecipient,
                'notes' => $worker['notes'] ?? null,
                'sort_order' => $sortOrder,
            ]);
            if ($isRecipient && ! $receiptRecipient) {
                $receiptRecipient = $worker['name'];
            }
        }
        if ($receiptRecipient) {
            $package->transaction->forceFill([
                'receipt_recipient_name' => $receiptRecipient,
                'signatory_name' => $package->transaction->signatory_name ?: $receiptRecipient,
            ])->save();
        }
        app(OperationalAuditService::class)->record($package->transaction->fiscal_year_id, 'SPJ_PACKAGE', $package->id, 'PERBARUI_ISIAN', 'Isian manual paket '.$package->transaction->no_bukti.' diperbarui.');

        return back()->with('success', 'Isian manual paket SPJ berhasil disimpan.');
    }

    public function download(string $packageId, SpjPackageValidationService $validator, SpjPdfService $pdf)
    {
        $package = SpjPackage::query()->with($this->packageRelations())->find($packageId);
        if (! $package || $package->transaction->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
            return redirect()->route('spj-documents.index')->with('error', 'Paket dokumen tidak ditemukan pada tahun anggaran aktif.');
        }
        $issues = $validator->validate($package);
        if ($issues) {
            return back()->with('error', 'PDF belum dapat dibuat. Lengkapi seluruh data wajib terlebih dahulu.');
        }

        return $pdf->download($package, School::query()->findOrFail(session('active_school_id')));
    }

    public function downloadTemplate(string $packageId, string $templateId, SpjPackageValidationService $validator, SpjTemplateService $templates)
    {
        $package = SpjPackage::query()->with($this->packageRelations())->find($packageId);
        $template = DocumentTemplate::query()->find($templateId);
        if (! $package || ! $template || $package->transaction->fiscal_year_id !== (int) session('active_fiscal_year_id') || $template->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
            return redirect()->route('spj-documents.index')->with('error', 'Paket atau template tidak ditemukan pada tahun anggaran aktif.');
        }
        if ($validator->validate($package)) {
            return back()->with('error', 'Dokumen dari template belum dapat dibuat. Lengkapi data wajib terlebih dahulu.');
        }

        return $templates->download($template, $package, School::query()->findOrFail(session('active_school_id')));
    }

    /** Menampilkan pratinjau HTML template Excel pada tab browser baru. */
    public function previewTemplate(string $packageId, string $templateId, SpjPackageValidationService $validator, SpjTemplateService $templates): View|RedirectResponse
    {
        $package = SpjPackage::query()->with($this->packageRelations())->find($packageId);
        $template = DocumentTemplate::query()->find($templateId);
        if (! $package || ! $template || $package->transaction->fiscal_year_id !== (int) session('active_fiscal_year_id') || $template->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
            return redirect()->route('spj-documents.index')->with('error', 'Paket atau template tidak ditemukan pada tahun anggaran aktif.');
        }
        $school = School::query()->findOrFail(session('active_school_id'));
        $previewHtml = $templates->previewHtml($template, $package, $school);

        return view('spj-documents.template-preview', [
            'package' => $package,
            'template' => $template,
            'previewHtml' => $previewHtml,
            'validationIssues' => $validator->validate($package),
        ]);
    }

    private function quarter(Transaction $transaction): string
    {
        $month = (int) $transaction->transaction_date?->format('n');

        return 'TW-'.(int) ceil(max(1, $month) / 3);
    }

    /** @return array<int, string> */
    private function packageRelations(): array
    {
        return [
            'transaction.items',
            'transaction.goods',
            'transaction.workOrder',
            'transaction.workers',
            'transaction.participants',
            'transaction.travels',
            'transaction.honors',
        ];
    }

    private function semester(Transaction $transaction): string
    {
        return ((int) $transaction->transaction_date?->format('n') <= 6) ? 'SEM-I' : 'SEM-II';
    }
}
