<?php

namespace App\Http\Controllers;

use App\Models\DocumentTemplate;
use App\Models\Employee;
use App\Models\FiscalPeriodClosure;
use App\Models\FiscalYear;
use App\Models\QuarterNumberingRun;
use App\Models\School;
use App\Models\SpjDocument;
use App\Models\SpjHonor;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Services\FiscalPeriodWorkflowService;
use App\Services\OperationalAuditService;
use App\Services\SpjDocumentLifecycleService;
use App\Services\SpjDocumentNumberService;
use App\Services\SpjPackageValidationService;
use App\Services\SpjPdfService;
use App\Services\SpjTemplateService;
use App\Services\SpjTransactionDetailsService;
use App\Services\TransactionSettlementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SpjController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $tab = $request->query('tab', 'persiapan');

        return match ($tab) {
            'persiapan' => $this->tabPersiapan($request),
            'paket' => $this->tabPaket($request),
            'laporan' => $this->tabLaporan($request),
            'monitoring' => $this->tabMonitoring($request),
            default => $this->tabPersiapan($request),
        };
    }

    private function tabPersiapan(Request $request): View
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
            ->orderByRaw('COALESCE((SELECT CASE WHEN spj_packages.document_number IS NULL THEN 0 ELSE 2 END FROM spj_packages WHERE spj_packages.transaction_id = transactions.id LIMIT 1), 1)')
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->paginate($perPage)->withQueryString();

        return view('spj.index', [
            'tab' => 'persiapan',
            'transactions' => $transactions,
            ...$this->overviewMetrics(),
            'spjTypes' => Transaction::query()->activeContext()->whereNotNull('spj_category')->where('spj_category', '!=', '')->distinct()->orderBy('spj_category')->pluck('spj_category'),
            'filters' => $filters,
        ]);
    }

    private function tabPaket(Request $request): View|RedirectResponse
    {
        $packageId = $request->query('package_id');

        $packageList = SpjPackage::query()
            ->with(['transaction:id,no_bukti,transaction_date,payment_description,description,recipient_name,spj_category,gross_amount,fiscal_year_id,fund_source_id'])
            ->whereHas('transaction', fn ($query) => $query->activeContext())
            ->orderByRaw("CASE status WHEN 'CANCELLED' THEN 3 WHEN 'FINAL' THEN 2 WHEN 'NUMBERED' THEN 1 ELSE 0 END DESC")
            ->orderByDesc('numbered_at')
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'package_page')
            ->withQueryString();

        if (! $packageId) {
            return view('spj.index', [
                'tab' => 'paket',
                'package' => null,
                'packageList' => $packageList,
                'validationIssues' => [],
                'templates' => collect(),
                'transactions' => null,
                ...$this->overviewMetrics(),
                'spjTypes' => [],
                'filters' => [],
                'periodClosures' => FiscalPeriodClosure::query()->where('fiscal_year_id', session('active_fiscal_year_id'))->orderBy('quarter')->get()->keyBy('quarter'),
                'participantRoster' => collect(),
            ]);
        }

        $package = SpjPackage::query()->with([
            'documents.template',
            'transaction.items',
            'transaction.goods',
            'transaction.workOrder',
            'transaction.workers',
            'transaction.participants',
            'transaction.travels',
            'transaction.honors',
            'transaction.payments',
            'transaction.goodsReceipts.items',
        ])->find($packageId);
        if (! $package || $package->transaction->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
            return redirect()->route('spj.index', ['tab' => 'persiapan'])->with('error', 'Paket dokumen tidak ditemukan pada tahun anggaran aktif.');
        }

        $validator = app(SpjPackageValidationService::class);
        $category = strtoupper((string) ($package->transaction->spj_category ?: $package->transaction->spj_category));
        $participantRoster = $this->participantRoster();
        if ($category === 'KONSUMSI' && $package->transaction->participants->isEmpty()) {
            $item = $package->transaction->items->first();
            if ($item) {
                foreach ($participantRoster as $sortOrder => $employee) {
                    $item->participants()->create([
                        'name' => $employee->name,
                        'position' => $employee->position ?: $employee->staff_type,
                        'portions' => 1,
                        'sort_order' => $sortOrder,
                    ]);
                }
                $package->transaction->load('participants');
            }
        }
        $validationIssues = $validator->validate($package);
        $templates = DocumentTemplate::query()->where(['fiscal_year_id' => session('active_fiscal_year_id'), 'is_active' => true])->orderBy('document_type')->get()
            ->filter(fn (DocumentTemplate $template) => empty($template->applicable_categories) || in_array('SEMUA', $template->applicable_categories, true) || in_array($category, $template->applicable_categories, true));

        return view('spj.index', [
            'tab' => 'paket',
            'package' => $package,
            'packageList' => $packageList,
            'validationIssues' => $validationIssues,
            'templates' => $templates,
            'transactions' => null,
            ...$this->overviewMetrics(),
            'spjTypes' => [],
            'filters' => [],
            'periodClosures' => FiscalPeriodClosure::query()->where('fiscal_year_id', session('active_fiscal_year_id'))->orderBy('quarter')->get()->keyBy('quarter'),
            'participantRoster' => $participantRoster,
        ]);
    }

    private function tabLaporan(Request $request): View
    {
        $perPageRaw = $request->input('perPage', 15);
        $perPage = $perPageRaw === 'all' ? 10000 : (int) $perPageRaw;
        $perPage = in_array($perPage, [15, 25, 50, 100, 10000]) ? $perPage : 15;

        $pendingPerPageRaw = $request->input('pendingPerPage', 15);
        $pendingPerPage = $pendingPerPageRaw === 'all' ? 10000 : (int) $pendingPerPageRaw;
        $pendingPerPage = in_array($pendingPerPage, [15, 25, 50, 100, 10000]) ? $pendingPerPage : 15;

        [$packages, $summary] = $this->report($request, $perPage, $pendingPerPage);
        $pendingPaginator = $summary['pending_transactions'];

        return view('spj.index', [
            'tab' => 'laporan',
            'packages' => $packages,
            'summary' => $summary,
            'pendingPaginator' => $pendingPaginator,
            'transactions' => null,
            ...$this->overviewMetrics(),
            'spjTypes' => [],
            'filters' => [],
        ]);
    }

    private function tabMonitoring(Request $request): View
    {
        $pendingPerPageRaw = $request->input('pendingPerPage', 15);
        $pendingPerPage = $pendingPerPageRaw === 'all' ? 10000 : (int) $pendingPerPageRaw;
        $pendingPerPage = in_array($pendingPerPage, [15, 25, 50, 100, 10000]) ? $pendingPerPage : 15;
        [, $summary] = $this->report($request, 15, $pendingPerPage);
        $pendingPaginator = $summary['pending_transactions'];

        return view('spj.index', [
            'tab' => 'monitoring',
            'periodClosures' => FiscalPeriodClosure::query()->where('fiscal_year_id', session('active_fiscal_year_id'))->orderBy('quarter')->get()->keyBy('quarter'),
            'pendingPaginator' => $pendingPaginator,
            'summary' => $summary,
            'transactions' => null,
            ...$this->overviewMetrics(),
            'spjTypes' => [],
            'filters' => [],
        ]);
    }

    /**
     * @return array{totalPackages: int, numberedPackages: int, readyTransactions: int}
     */
    private function overviewMetrics(): array
    {
        $packages = SpjPackage::query()->whereHas('transaction', fn ($query) => $query->activeContext());

        return [
            'totalPackages' => (clone $packages)->count(),
            'numberedPackages' => (clone $packages)->whereNotNull('document_number')->count(),
            'readyTransactions' => Transaction::query()->activeContext()->has('items')->count(),
        ];
    }

    public function prepare(Request $request, string $transactionId): RedirectResponse
    {
        $transaction = Transaction::query()->with('spjPackage')->withCount('items')->find($transactionId);
        if (! $transaction || $transaction->fiscal_year_id !== (int) session('active_fiscal_year_id') || (int) $transaction->fund_source_id !== (int) session('active_fund_source_id')) {
            return back()->with('error', 'Transaksi tidak ditemukan pada tahun anggaran aktif.');
        }
        if ($transaction->items_count < 1) {
            return back()->with('error', 'Paket belum dapat dibuat karena transaksi belum memiliki rincian barang/jasa.');
        }
        if ($transaction->spjPackage && ! $transaction->spjPackage->isEditable()) {
            return back()->with('error', 'Paket sudah bernomor atau final. Batalkan penomoran dan buka paket terlebih dahulu sebelum memperbaiki data.');
        }
        $submittedCategory = strtoupper((string) $request->input('spj_category'));
        if (! in_array($submittedCategory, ['PEMELIHARAAN', 'UPAH', 'HONOR_PEGAWAI', 'JASA_HONORARIUM'], true)) {
            $request->request->remove('workers');
        }
        if (! in_array($submittedCategory, ['SPPD', 'PERJALANAN_DINAS'], true)) {
            $request->request->remove('travels');
        }
        if ($submittedCategory !== 'KONSUMSI') {
            $request->request->remove('participants');
        }
        $maximumDocumentDate = $transaction->transaction_date->format('Y-m-d');
        $data = $request->validate([
            'spj_category' => ['required', 'in:BARANG,KONSUMSI,PEMELIHARAAN,JASA_LAINNYA,SPPD,HONOR_PEGAWAI,BELANJA_MODAL,PERJALANAN_DINAS,JASA_HONORARIUM,UPAH,LAINNYA'],
            'payment_description' => ['required', 'string', 'max:4000'],
            'payment_reference' => ['nullable', 'string', 'max:160'],
            'payment_method' => ['required', 'in:transfer_bank,siplah,tunai'],
            'receipt_recipient_name' => ['required', 'string', 'max:255'],
            'order_number' => ['nullable', 'string', 'max:80'],
            'order_date' => ['required_if:spj_category,KONSUMSI', 'nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'bap_number' => ['nullable', 'string', 'max:80'],
            'bap_date' => ['required_if:spj_category,KONSUMSI', 'nullable', 'date', 'after_or_equal:order_date', 'before_or_equal:'.$maximumDocumentDate],
            'bast_number' => ['nullable', 'string', 'max:80'],
            'bast_date' => ['required_if:spj_category,KONSUMSI', 'nullable', 'date', 'after_or_equal:bap_date', 'before_or_equal:'.$maximumDocumentDate],
            'invoice_number' => ['nullable', 'string', 'max:80'],
            'invoice_date' => ['nullable', 'date', 'after_or_equal:bast_date', 'before_or_equal:'.$maximumDocumentDate],
            'invoice_status' => ['nullable', 'string', 'max:30'],
            'work_description' => ['nullable', 'string', 'max:4000'],
            'work_location' => ['nullable', 'string', 'max:180'],
            'work_started_at' => ['nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'work_completed_at' => ['nullable', 'date', 'after_or_equal:work_started_at', 'before_or_equal:'.$maximumDocumentDate],
            'spk_number' => ['nullable', 'string', 'max:80'],
            'spk_date' => ['nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'rab_number' => ['nullable', 'string', 'max:80'],
            'rab_date' => ['nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'signatory_name' => ['nullable', 'string', 'max:180'],
            'signatory_role' => ['nullable', 'string', 'max:80'],
            'vendor_name' => ['nullable', 'string', 'max:180'],
            'vendor_owner' => ['nullable', 'string', 'max:180'],
            'vendor_npwp' => ['nullable', 'string', 'max:32'],
            'ppn_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pph21_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pph22_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pph23_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pph4_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sspd_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'event_name' => ['required_if:spj_category,KONSUMSI', 'nullable', 'string', 'max:180'],
            'event_location' => ['required_if:spj_category,KONSUMSI', 'nullable', 'string', 'max:180'],
            'event_date' => ['required_if:spj_category,KONSUMSI', 'nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'participant_count' => ['required_if:spj_category,KONSUMSI', 'nullable', 'integer', 'min:1', function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                if (strtoupper((string) $request->input('spj_category')) !== 'KONSUMSI') {
                    return;
                }
                $portions = collect($request->input('participants', []))->sum(fn (array $row): int => (int) ($row['portions'] ?? 0));
                if ((int) $value !== $portions) {
                    $fail("Jumlah peserta harus sama dengan total porsi ({$portions}).");
                }
            }],
            'participants' => ['nullable', 'array'],
            'participants.*.name' => ['nullable', 'string', 'max:180'],
            'participants.*.position' => ['nullable', 'string', 'max:180'],
            'participants.*.portions' => ['nullable', 'numeric', 'min:1', function (string $attribute, mixed $value, \Closure $fail): void {
                if (abs((float) $value - round((float) $value)) > 0.000001) {
                    $fail('Jumlah porsi harus berupa bilangan bulat tanpa koma atau desimal.');
                }
            }],
            'workers' => ['required_if:spj_category,HONOR_PEGAWAI', 'nullable', 'array', 'min:1', function (string $attribute, mixed $value, \Closure $fail) use ($request, $transaction): void {
                $category = strtoupper((string) $request->input('spj_category'));
                if (! in_array($category, ['HONOR_PEGAWAI', 'JASA_HONORARIUM'], true) || ! is_array($value)) {
                    return;
                }
                $detailTotal = collect($value)->sum(fn (array $recipient): float => (float) ($recipient['work_days'] ?? 0) * (float) ($recipient['daily_rate'] ?? 0));
                $transactionTotal = (float) $transaction->gross_amount;
                if (abs($detailTotal - $transactionTotal) > 0.01) {
                    $fail(sprintf(
                        'Total rincian honor Rp %s tidak sama dengan nilai bruto transaksi Rp %s. Periksa jumlah penerima, Bulan/Kali, atau Tarif.',
                        number_format($detailTotal, 0, ',', '.'),
                        number_format($transactionTotal, 0, ',', '.'),
                    ));
                }
            }],
            'workers.*.name' => ['required_if:spj_category,HONOR_PEGAWAI', 'nullable', 'string', 'max:180'],
            'workers.*.job_description' => ['required_if:spj_category,HONOR_PEGAWAI', 'nullable', 'string', 'max:255'],
            'workers.*.work_days' => ['required_if:spj_category,HONOR_PEGAWAI', 'nullable', 'numeric', 'min:1', function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                if ($request->input('spj_category') === 'HONOR_PEGAWAI' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $fail('Bulan/Kali harus berupa bilangan bulat tanpa koma atau desimal.');
                }
            }],
            'workers.*.daily_rate' => ['required_if:spj_category,HONOR_PEGAWAI', 'nullable', 'numeric', 'min:1'],
            'workers.*.is_receipt_recipient' => ['nullable', 'boolean'],
            'workers.*.notes' => ['nullable', 'string', 'max:2000'],
            'travels' => ['nullable', 'array'],
            'travels.*.traveler_name' => ['required_with:travels', 'string', 'max:180'],
            'travels.*.destination' => ['nullable', 'string', 'max:180'],
            'travels.*.purpose' => ['nullable', 'string', 'max:4000'],
            'travels.*.departure_date' => ['nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'travels.*.assignment_letter_number' => ['nullable', 'string', 'max:255'],
            'travels.*.assignment_letter_date' => ['nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'travels.*.return_date' => ['nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'travels.*.transport_mode' => ['nullable', 'string', 'max:80'],
            'travels.*.amount' => ['nullable', 'numeric', 'min:0'],
            'travels.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['spj_category'] = ['BELANJA_MODAL' => 'BARANG', 'PERJALANAN_DINAS' => 'SPPD', 'JASA_HONORARIUM' => 'HONOR_PEGAWAI', 'UPAH' => 'PEMELIHARAAN', 'LAINNYA' => 'JASA_LAINNYA'][$data['spj_category'] ?? ''] ?? ($data['spj_category'] ?? null);
        $this->validateTaxMatchesBku($transaction, $data);
        if (filled($data['spj_category'] ?? null)) {
            $transaction->update(collect($data)->only([
                'spj_category', 'payment_description', 'payment_reference', 'payment_method',
                'receipt_recipient_name', 'signatory_name', 'signatory_role', 'vendor_name', 'vendor_owner', 'vendor_npwp',
                'invoice_number', 'invoice_date', 'invoice_status',
                'ppn_rate', 'pph21_rate', 'pph22_rate', 'pph23_rate', 'pph4_rate', 'sspd_rate',
            ])->all());
            $this->updateTaxAmounts($transaction);
            $transaction->load('items');
            app(SpjTransactionDetailsService::class)->synchronize($transaction, $data);
            $transaction->refresh();
        }
        if (blank($transaction->spj_category)) {
            return back()->with('error', 'Pilih kategori SPJ pada halaman transaksi terlebih dahulu.');
        }
        $transaction->load('items');
        if ($transaction->items->contains(fn ($item) => blank($item->item_description))) {
            return back()->with('error', 'Lengkapi uraian barang/jasa untuk SPJ pada setiap detail transaksi terlebih dahulu.');
        }

        $package = SpjPackage::firstOrCreate(
            ['transaction_id' => $transaction->id],
            ['quarter_code' => $this->quarter($transaction), 'semester_code' => $this->semester($transaction), 'status' => 'DRAFT']
        );
        $quarter = (int) ceil((int) $transaction->transaction_date->format('n') / 3);
        if (app(FiscalPeriodWorkflowService::class)->isLateEntry($transaction->fiscal_year_id, $quarter)) {
            $package->forceFill(['is_late_entry' => true])->save();
        }
        app(OperationalAuditService::class)->record($transaction->fiscal_year_id, 'SPJ_PACKAGE', $package->id, 'SIAPKAN_PAKET', 'Paket SPJ disiapkan untuk bukti '.$transaction->no_bukti);

        return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $package->id])->with('success', 'Paket dokumen SPJ telah disiapkan.');
    }

    public function assignNumber(string $packageId, SpjDocumentNumberService $numbers, SpjPackageValidationService $validator): RedirectResponse
    {
        $package = SpjPackage::query()->with(['transaction.items', 'transaction.goods', 'transaction.honors'])->find($packageId);
        if (! $package || $package->transaction->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Paket dokumen tidak ditemukan pada tahun anggaran aktif.');
        }
        if ($package->document_number && in_array($package->status, ['NUMBERED', 'FINAL'], true)) {
            return back()->with('success', 'Nomor dokumen SPJ sudah ditetapkan.');
        }
        if ($issues = $validator->validate($package)) {
            return back()->with('error', 'Penomoran ditolak. '.collect($issues)->pluck('message')->implode(' '));
        }

        if ($package->status === 'CANCELLED') {
            $package->forceFill(['document_number' => null, 'numbered_at' => null])->save();
        }

        $school = School::query()->findOrFail(session('active_school_id'));
        $result = $numbers->assignAutomaticNumbers($package, $school->school_code ?: $school->npsn, $school->npsn);
        $package->refresh();
        app(OperationalAuditService::class)->record($package->transaction->fiscal_year_id, 'SPJ_PACKAGE', $package->id, 'TETAPKAN_NOMOR', 'Nomor SPJ '.$package->document_number.' ditetapkan.');

        return back()->with('success', "Penomoran otomatis selesai: {$result['created']} nomor baru; {$result['skipped']} nomor yang sudah ada dilewati.");
    }

    public function markReady(string $packageId, SpjPackageValidationService $validator): RedirectResponse
    {
        $package = SpjPackage::query()->with(['transaction.items', 'transaction.goods', 'transaction.honors'])->find($packageId);
        if (! $package || $package->transaction->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
            return back()->with('error', 'Paket tidak ditemukan pada tahun anggaran aktif.');
        }
        if ($package->status !== 'DRAFT') {
            return back()->with('error', 'Hanya paket DRAFT yang dapat ditandai siap.');
        }
        if ($issues = $validator->validate($package)) {
            return back()->with('error', 'Paket belum siap: '.collect($issues)->pluck('message')->implode(' '));
        }

        $package->forceFill(['status' => 'READY'])->save();
        app(OperationalAuditService::class)->record($package->transaction->fiscal_year_id, 'SPJ_PACKAGE', $package->id, 'PAKET_READY', 'Paket dinyatakan siap untuk penomoran triwulan.');

        return back()->with('success', 'Paket siap dan akan masuk penomoran triwulan.');
    }

    public function assignQuarterNumbers(Request $request, SpjPackageValidationService $validator, SpjDocumentNumberService $numbers, FiscalPeriodWorkflowService $periods): RedirectResponse
    {
        $data = $request->validate([
            'quarter' => ['required', 'integer', 'between:1,4'],
            'document_types' => ['nullable', 'array'],
            'document_types.*' => ['string', 'max:40'],
        ]);
        $yearId = (int) session('active_fiscal_year_id');
        $school = School::query()->findOrFail(session('active_school_id'));
        $templates = DocumentTemplate::query()->where(['fiscal_year_id' => $yearId, 'is_active' => true])->get();
        $documentTypes = collect($data['document_types'] ?? $templates->pluck('document_type')->push('SPJ'))
            ->map(fn ($type) => strtoupper(trim((string) $type)))->filter()->unique()->values();
        $quarterScope = fn ($query) => $query->activeContext()
            ->whereMonth('transaction_date', '>=', ((int) $data['quarter'] - 1) * 3 + 1)
            ->whereMonth('transaction_date', '<=', (int) $data['quarter'] * 3);
        $notReady = Transaction::query()->where($quarterScope)->has('items')
            ->where(function ($query): void {
                $query->doesntHave('spjPackage')
                    ->orWhereHas('spjPackage', function ($package): void {
                        $package->where('status', 'DRAFT')
                            ->whereDoesntHave('documents', fn ($document) => $document
                                ->where('document_type', 'SPJ')
                                ->where('scope_key', 'MAIN')
                                ->where('status', 'CANCELLED'));
                    });
            })->count();
        if ($notReady > 0) {
            return back()->with('error', "Penomoran dibatalkan: masih ada {$notReady} transaksi triwulan ini yang belum berstatus READY.");
        }

        $period = $periods->period($yearId, (int) $data['quarter']);
        if ($period->status === 'CLOSED') {
            return back()->with('error', 'Triwulan sudah ditutup. Administrator harus membuka kembali periode terlebih dahulu.');
        }
        $run = QuarterNumberingRun::query()->create([
            'fiscal_period_closure_id' => $period->id,
            'fiscal_year_id' => $yearId,
            'quarter' => $data['quarter'],
            'status' => 'RUNNING',
            'document_types' => $documentTypes->all(),
            'started_by' => auth()->id(),
            'started_at' => now(),
        ]);

        $packages = SpjPackage::query()
            ->with(['transaction.items', 'transaction.goods', 'transaction.workOrder', 'transaction.honors', 'transaction.travels'])
            ->where(function ($query): void {
                $query->whereIn('status', ['READY', 'NUMBERED'])
                    ->orWhere(function ($cancelled): void {
                        $cancelled->whereIn('status', ['DRAFT', 'CANCELLED'])
                            ->whereHas('documents', fn ($document) => $document
                                ->where('document_type', 'SPJ')
                                ->where('scope_key', 'MAIN')
                                ->where('status', 'CANCELLED'));
                    });
            })
            ->whereHas('transaction', $quarterScope)
            ->get();

        $invalidPackages = $packages->map(function (SpjPackage $package) use ($validator): ?array {
            $issues = $validator->validate($package);

            return $issues ? ['package' => $package, 'issues' => $issues] : null;
        })->filter();
        if ($invalidPackages->isNotEmpty()) {
            $proofNumbers = $invalidPackages->map(fn (array $entry) => $entry['package']->transaction->no_bukti)->implode(', ');
            $run->update([
                'status' => 'FAILED',
                'failed_count' => $invalidPackages->count(),
                'error_message' => 'Paket tidak konsisten: '.$proofNumbers,
                'completed_at' => now(),
            ]);

            return back()->with('error', 'Penomoran dibatalkan. Perbaiki paket tidak konsisten: '.$proofNumbers.'.');
        }

        $numbered = 0;
        $skipped = 0;
        try {
            foreach ($documentTypes as $documentType) {
                $orderedPackages = $packages->filter(function (SpjPackage $package) use ($documentType, $templates, $validator): bool {
                    if ($validator->validate($package)) {
                        return false;
                    }
                    if ($documentType === 'SPJ') {
                        return true;
                    }

                    $category = strtoupper((string) $package->transaction->spj_category);

                    return $templates->where('document_type', $documentType)->contains(fn (DocumentTemplate $template) => empty($template->applicable_categories) || in_array('SEMUA', $template->applicable_categories, true) || in_array($category, $template->applicable_categories, true));
                })->sortBy(fn (SpjPackage $package) => $this->documentEventDate($package, $documentType)->format('Y-m-d').'-'.str_pad((string) $package->id, 12, '0', STR_PAD_LEFT));

                foreach ($orderedPackages as $package) {
                    $templateId = $templates->where('document_type', $documentType)->first()?->id;
                    $before = $package->documents()->where(['document_type' => $documentType, 'scope_key' => 'MAIN'])->where('status', '!=', 'CANCELLED')->whereNotNull('document_number')->exists();
                    $numbers->assign($package, $documentType, $this->documentEventDate($package, $documentType), $school->school_code ?: $school->npsn, templateId: $templateId, npsn: $school->npsn);
                    $before ? $skipped++ : $numbered++;
                }
            }
            foreach ($packages as $package) {
                $automaticResult = $numbers->assignAutomaticNumbers($package, $school->school_code ?: $school->npsn, $school->npsn);
                $numbered += $automaticResult['created'];
                $skipped += $automaticResult['skipped'];
            }
            $run->update(['status' => 'COMPLETED', 'numbered_count' => $numbered, 'skipped_count' => $skipped, 'completed_at' => now()]);
            $periods->markNumbered($period, (int) auth()->id());
        } catch (\Throwable $exception) {
            $run->update(['status' => 'FAILED', 'numbered_count' => $numbered, 'skipped_count' => $skipped, 'failed_count' => 1, 'error_message' => $exception->getMessage(), 'completed_at' => now()]);

            return back()->with('error', 'Proses penomoran terhenti dan dapat dilanjutkan: '.$exception->getMessage());
        }

        app(OperationalAuditService::class)->record($yearId, 'SPJ_QUARTER', (int) $data['quarter'], 'PENOMORAN_BATCH', "Penomoran triwulan {$data['quarter']}: {$numbered} nomor baru, {$skipped} sudah bernomor.");

        return back()->with('success', "Penomoran triwulan selesai: {$numbered} nomor baru; {$skipped} dokumen dilewati karena sudah bernomor.");
    }

    public function assignDocumentNumber(Request $request, string $packageId, string $documentType, SpjDocumentNumberService $numbers, SpjPackageValidationService $validator): RedirectResponse
    {
        $package = SpjPackage::query()->with(['transaction.items', 'transaction.goods', 'transaction.honors'])->find($packageId);
        if (! $package || $package->transaction->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
            return back()->with('error', 'Paket tidak ditemukan pada tahun anggaran aktif.');
        }
        if ($issues = $validator->validate($package)) {
            return back()->with('error', 'Penomoran ditolak. '.collect($issues)->pluck('message')->implode(' '));
        }
        $data = $request->validate([
            'document_date' => ['required', 'date'],
            'event_date' => ['nullable', 'date'],
            'scope_key' => ['nullable', 'string', 'max:80'],
        ]);
        $school = School::query()->findOrFail(session('active_school_id'));
        $document = $numbers->assign(
            $package,
            $documentType,
            Carbon::parse($data['document_date']),
            $school->school_code ?: $school->npsn,
            $data['scope_key'] ?? 'MAIN',
            npsn: $school->npsn,
        );
        if (filled($data['event_date'] ?? null)) {
            $document->forceFill(['event_date' => $data['event_date']])->save();
        }
        app(OperationalAuditService::class)->record($package->transaction->fiscal_year_id, 'SPJ_DOCUMENT', $document->id, 'TETAPKAN_NOMOR', 'Nomor '.$document->document_type.' '.$document->document_number.' ditetapkan.');

        return back()->with('success', 'Nomor '.$document->document_type.' berhasil dibuat: '.$document->document_number);
    }

    public function finalizeDocument(string $documentId, SpjDocumentLifecycleService $lifecycle): RedirectResponse
    {
        $document = SpjDocument::query()->with('package.transaction')->findOrFail($documentId);
        abort_unless($document->package->transaction->fiscal_year_id === (int) session('active_fiscal_year_id'), 404);
        $lifecycle->finalize($document, (int) auth()->id());

        return back()->with('success', 'Dokumen difinalkan dan snapshot dikunci.');
    }

    public function cancelDocument(Request $request, string $documentId, SpjDocumentLifecycleService $lifecycle): RedirectResponse
    {
        abort_unless(auth()->user()->isAdministrator(), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $document = SpjDocument::query()->with('package.transaction')->findOrFail($documentId);
        abort_unless($document->package->transaction->fiscal_year_id === (int) session('active_fiscal_year_id'), 404);
        $oldNumber = $document->document_number;
        $lifecycle->cancel($document, (int) auth()->id(), $data['reason']);
        app(OperationalAuditService::class)->record($document->package->transaction->fiscal_year_id, 'SPJ_DOCUMENT', $document->id, 'BATALKAN_NOMOR', 'Nomor '.$oldNumber.' dibatalkan. Alasan: '.$data['reason']);

        return back()->with('warning', 'Nomor '.$oldNumber.' dibatalkan dan slotnya tersedia untuk dialokasikan kembali. Buka paket untuk memperbaiki input.');
    }

    public function replaceDocument(Request $request, string $documentId, SpjDocumentLifecycleService $lifecycle, SpjDocumentNumberService $numbers): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $old = SpjDocument::query()->with('package.transaction')->findOrFail($documentId);
        abort_unless($old->package->transaction->fiscal_year_id === (int) session('active_fiscal_year_id'), 404);
        $lifecycle->cancel($old, (int) auth()->id(), $data['reason']);
        $school = School::query()->findOrFail(session('active_school_id'));
        $replacement = $numbers->assign($old->package, $old->document_type, now(), $school->school_code ?: $school->npsn, 'REPLACEMENT:'.$old->id.':'.now()->format('YmdHis'), $old->document_template_id, $school->npsn);
        $replacement->forceFill(['replaces_document_id' => $old->id, 'is_late_entry' => true])->save();

        return back()->with('success', 'Dokumen lama dibatalkan dan dokumen pengganti mendapat nomor '.$replacement->document_number.'.');
    }

    public function closeQuarter(Request $request, FiscalPeriodWorkflowService $periods): RedirectResponse
    {
        $data = $request->validate(['quarter' => ['required', 'integer', 'between:1,4']]);
        $period = $periods->period((int) session('active_fiscal_year_id'), (int) $data['quarter']);
        $periods->close($period, (int) session('active_fund_source_id'), (int) auth()->id());

        return back()->with('success', 'Triwulan '.$data['quarter'].' berhasil ditutup.');
    }

    public function reopenQuarter(Request $request, string $periodId, FiscalPeriodWorkflowService $periods): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $period = FiscalPeriodClosure::query()->where('fiscal_year_id', session('active_fiscal_year_id'))->findOrFail($periodId);
        $periods->reopen($period, (int) auth()->id(), $data['reason']);

        return back()->with('success', 'Triwulan dibuka kembali dan alasan telah dicatat.');
    }

    public function storePayment(Request $request, string $transactionId, TransactionSettlementService $settlements): RedirectResponse
    {
        $transaction = Transaction::query()->activeContext()->with('spjPackage')->findOrFail($transactionId);
        if ($transaction->spjPackage && ! $transaction->spjPackage->isEditable()) {
            return back()->with('error', 'Pembayaran tidak dapat diubah karena paket SPJ sudah dikunci. Batalkan nomor dan buka paket untuk koreksi terlebih dahulu.');
        }
        $data = $request->validate([
            'payment_date' => ['required', 'date'], 'gross_amount' => ['required', 'numeric', 'gt:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'], 'payment_method' => ['nullable', 'string', 'max:40'],
            'payment_reference' => ['nullable', 'string', 'max:160'],
        ]);
        $settlements->addPayment($transaction, $data);

        return back()->with('success', 'Tahap pembayaran berhasil ditambahkan.');
    }

    public function storeGoodsReceipt(Request $request, string $transactionId, TransactionSettlementService $settlements): RedirectResponse
    {
        $transaction = Transaction::query()->activeContext()->with('spjPackage')->findOrFail($transactionId);
        if ($transaction->spjPackage && ! $transaction->spjPackage->isEditable()) {
            return back()->with('error', 'Penerimaan barang tidak dapat diubah karena paket SPJ sudah dikunci. Batalkan nomor dan buka paket untuk koreksi terlebih dahulu.');
        }
        $data = $request->validate([
            'receipt_date' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'], 'items.*.transaction_item_id' => ['required', 'integer'],
            'items.*.quantity_received' => ['required', 'numeric', 'gt:0'], 'items.*.amount_received' => ['nullable', 'numeric', 'min:0'],
        ]);
        $items = $data['items'];
        unset($data['items']);
        $settlements->addGoodsReceipt($transaction, $data, $items);

        return back()->with('success', 'Tahap penerimaan barang berhasil ditambahkan.');
    }

    public function unlockPackage(Request $request, string $packageId, SpjDocumentLifecycleService $lifecycle): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $package = SpjPackage::query()->with('transaction')->findOrFail($packageId);
        abort_unless($package->transaction->fiscal_year_id === (int) session('active_fiscal_year_id'), 404);
        $lifecycle->unlock($package, (int) auth()->id(), $data['reason']);

        return back()->with('success', 'Paket dibuka kembali. Alasan pembukaan telah dicatat.');
    }

    public function updateDetails(string $packageId, Request $request): RedirectResponse
    {
        $package = SpjPackage::query()->with('transaction')->find($packageId);
        if (! $package || $package->transaction->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Paket dokumen tidak ditemukan pada tahun anggaran aktif.');
        }
        if (! $package->isEditable()) {
            return back()->with('error', 'Paket sudah bernomor atau final. Buka kembali paket melalui administrator sebelum mengubah data.');
        }

        $maximumDocumentDate = $package->transaction->transaction_date->format('Y-m-d');
        $data = $request->validate([
            'payment_description' => ['nullable', 'string', 'max:4000'],
            'payment_reference' => ['nullable', 'string', 'max:160'],
            'payment_method' => ['nullable', 'in:transfer_bank,siplah,tunai'],
            'receipt_recipient_name' => ['nullable', 'string', 'max:255'],
            'spj_category' => ['nullable', 'string', 'max:40'],
            'invoice_number' => ['nullable', 'string', 'max:80'],
            'invoice_date' => ['nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'invoice_status' => ['nullable', 'string', 'max:30'],
            'work_description' => ['nullable', 'string', 'max:4000'],
            'work_location' => ['nullable', 'string', 'max:180'],
            'work_started_at' => ['nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'work_completed_at' => ['nullable', 'date', 'after_or_equal:work_started_at', 'before_or_equal:'.$maximumDocumentDate],
            'spk_number' => ['nullable', 'string', 'max:80'],
            'spk_date' => ['nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'rab_number' => ['nullable', 'string', 'max:80'],
            'rab_date' => ['nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'signatory_name' => ['nullable', 'string', 'max:180'],
            'signatory_role' => ['nullable', 'string', 'max:80'],
            'event_name' => ['required_if:spj_category,KONSUMSI', 'nullable', 'string', 'max:180'],
            'event_location' => ['required_if:spj_category,KONSUMSI', 'nullable', 'string', 'max:180'],
            'event_date' => ['required_if:spj_category,KONSUMSI', 'nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'participant_count' => ['required_if:spj_category,KONSUMSI', 'nullable', 'integer', 'min:1', function (string $attribute, mixed $value, \Closure $fail) use ($request, $package): void {
                $category = strtoupper((string) ($request->input('spj_category') ?: $package->transaction->spj_category));
                if ($category !== 'KONSUMSI') {
                    return;
                }
                $portions = collect($request->input('participants', []))->sum(fn (array $row): int => (int) ($row['portions'] ?? 0));
                if ((int) $value !== $portions) {
                    $fail("Jumlah peserta harus sama dengan total porsi ({$portions}).");
                }
            }],
            'participants' => ['nullable', 'array'],
            'participants.*.name' => ['nullable', 'string', 'max:180'],
            'participants.*.position' => ['nullable', 'string', 'max:180'],
            'participants.*.portions' => ['nullable', 'numeric', 'min:1', function (string $attribute, mixed $value, \Closure $fail): void {
                if (abs((float) $value - round((float) $value)) > 0.000001) {
                    $fail('Jumlah porsi harus berupa bilangan bulat tanpa koma atau desimal.');
                }
            }],
            'workers' => ['nullable', 'array', function (string $attribute, mixed $value, \Closure $fail) use ($request, $package): void {
                $category = strtoupper((string) ($request->input('spj_category') ?: $package->transaction->spj_category));
                if (! in_array($category, ['HONOR_PEGAWAI', 'JASA_HONORARIUM'], true) || ! is_array($value)) {
                    return;
                }
                $detailTotal = collect($value)->sum(fn (array $recipient): float => (float) ($recipient['work_days'] ?? 0) * (float) ($recipient['daily_rate'] ?? 0));
                $transactionTotal = (float) $package->transaction->gross_amount;
                if (abs($detailTotal - $transactionTotal) > 0.01) {
                    $fail(sprintf(
                        'Total rincian honor Rp %s tidak sama dengan nilai bruto transaksi Rp %s.',
                        number_format($detailTotal, 0, ',', '.'),
                        number_format($transactionTotal, 0, ',', '.'),
                    ));
                }
            }],
            'workers.*.name' => ['nullable', 'string', 'max:180'],
            'workers.*.job_description' => ['nullable', 'string', 'max:255'],
            'workers.*.work_days' => ['nullable', 'numeric', 'min:0'],
            'workers.*.daily_rate' => ['nullable', 'numeric', 'min:0'],
            'workers.*.is_receipt_recipient' => ['nullable', 'boolean'],
            'workers.*.notes' => ['nullable', 'string', 'max:2000'],
            'travels' => ['nullable', 'array'],
            'travels.*.traveler_name' => ['required_with:travels', 'string', 'max:180'],
            'travels.*.destination' => ['nullable', 'string', 'max:180'],
            'travels.*.purpose' => ['nullable', 'string', 'max:4000'],
            'travels.*.departure_date' => ['nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'travels.*.assignment_letter_number' => ['nullable', 'string', 'max:255'],
            'travels.*.assignment_letter_date' => ['nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'travels.*.return_date' => ['nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'travels.*.transport_mode' => ['nullable', 'string', 'max:80'],
            'travels.*.amount' => ['nullable', 'numeric', 'min:0'],
            'travels.*.notes' => ['nullable', 'string', 'max:2000'],
            'vendor_name' => ['nullable', 'string', 'max:180'],
            'vendor_owner' => ['nullable', 'string', 'max:180'],
            'vendor_npwp' => ['nullable', 'string', 'max:32'],
            'ppn_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pph21_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pph22_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pph23_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pph4_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sspd_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        $this->validateTaxMatchesBku($package->transaction, $data);
        $workers = $data['workers'] ?? [];
        $package->transaction->fill(collect($data)->only([
            'payment_description', 'payment_reference', 'payment_method',
            'receipt_recipient_name', 'signatory_name', 'signatory_role', 'vendor_name', 'vendor_owner', 'vendor_npwp',
            'invoice_number', 'invoice_date', 'invoice_status',
            'ppn_rate', 'pph21_rate', 'pph22_rate', 'pph23_rate', 'pph4_rate', 'sspd_rate',
        ])->all())->save();
        $this->updateTaxAmounts($package->transaction);
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

    private function updateTaxAmounts(Transaction $transaction): void
    {
        $rates = [
            'ppn' => (float) ($transaction->ppn_rate ?? 0),
            'pph21' => (float) ($transaction->pph21_rate ?? 0),
            'pph22' => (float) ($transaction->pph22_rate ?? 0),
            'pph23' => (float) ($transaction->pph23_rate ?? 0),
            'pph4' => (float) ($transaction->pph4_rate ?? 0),
            'sspd' => (float) ($transaction->sspd_rate ?? 0),
        ];
        if (array_sum($rates) <= 0) {
            return;
        }
        $gross = (float) $transaction->gross_amount;
        $amounts = array_map(fn (float $rate): float => round($gross * $rate / 100, 2), $rates);
        $transaction->forceFill([
            ...$amounts,
            'tax_total' => array_sum($amounts),
            'net_amount' => $gross - array_sum($amounts),
        ])->save();
    }

    /** @param array<string, mixed> $data */
    private function validateTaxMatchesBku(Transaction $transaction, array $data): void
    {
        $category = strtoupper((string) ($data['spj_category'] ?? $transaction->spj_category));
        if ($category !== 'KONSUMSI') {
            return;
        }

        $amountByRate = [
            'ppn_rate' => 'ppn',
            'pph21_rate' => 'pph21',
            'pph22_rate' => 'pph22',
            'pph23_rate' => 'pph23',
            'pph4_rate' => 'pph4',
            'sspd_rate' => 'sspd',
        ];
        $gross = (float) $transaction->gross_amount;
        $rateTotal = 0.0;
        foreach ($amountByRate as $rateField => $amountField) {
            if (array_key_exists($rateField, $data)) {
                $rateTotal += (float) $data[$rateField];

                continue;
            }
            if ($transaction->{$rateField} !== null) {
                $rateTotal += (float) $transaction->{$rateField};

                continue;
            }

            $rateTotal += $gross > 0 ? (float) $transaction->{$amountField} / $gross * 100 : 0;
        }
        $calculatedTax = round($gross * $rateTotal / 100, 2);
        $bkuTax = (float) $transaction->tax_total;
        if (abs($calculatedTax - $bkuTax) > 0.01) {
            throw ValidationException::withMessages([
                'sspd_rate' => sprintf(
                    'Total pajak hasil tarif Rp %s tidak sama dengan pajak BKU Rp %s. Sesuaikan tarif berdasarkan rincian BKU.',
                    number_format($calculatedTax, 0, ',', '.'),
                    number_format($bkuTax, 0, ',', '.')
                ),
            ]);
        }
    }

    private function participantRoster()
    {
        $statusId = static fn (Employee $employee): int => is_numeric($employee->payload['status_kepegawaian_id'] ?? null)
            ? (int) $employee->payload['status_kepegawaian_id']
            : PHP_INT_MAX;

        return Employee::query()->where('is_active', true)->orderBy('name')
            ->get(['id', 'name', 'position', 'staff_type', 'source_type', 'nuptk', 'payload'])
            ->sortBy(fn (Employee $employee) => sprintf('%s-%d-%d', mb_strtolower(trim($employee->name)), $employee->source_type === 'DAPODIK' ? 0 : 1, filled($employee->nuptk) ? 0 : 1))
            ->unique(fn (Employee $employee) => mb_strtolower(trim($employee->name)))
            ->sortBy(fn (Employee $employee) => sprintf('%010d-%s', $statusId($employee), mb_strtolower(trim($employee->name))))
            ->values();
    }

    public function download(string $packageId, SpjPackageValidationService $validator, SpjPdfService $pdf, SpjDocumentNumberService $numbers)
    {
        $package = SpjPackage::query()->with(['transaction.items', 'transaction.goods', 'transaction.workers', 'transaction.participants', 'transaction.travels'])->find($packageId);
        if (! $package || $package->transaction->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Paket dokumen tidak ditemukan pada tahun anggaran aktif.');
        }
        $issues = $validator->validate($package);
        if ($issues) {
            return back()->with('error', 'PDF belum dapat dibuat. Lengkapi seluruh data wajib terlebih dahulu.');
        }

        $school = School::query()->findOrFail(session('active_school_id'));
        $numbers->assignAutomaticNumbers($package, $school->school_code ?: $school->npsn, $school->npsn);
        $package->refresh();

        return $pdf->download($package, $school);
    }

    public function downloadTemplate(string $packageId, string $templateId, SpjPackageValidationService $validator, SpjTemplateService $templates, SpjDocumentNumberService $numbers)
    {
        $package = SpjPackage::query()->with(['transaction.items', 'transaction.goods', 'transaction.workOrder', 'transaction.workers', 'transaction.participants', 'transaction.travels'])->find($packageId);
        $template = DocumentTemplate::query()->find($templateId);
        if (! $package || ! $template || $package->transaction->fiscal_year_id !== (int) session('active_fiscal_year_id') || $template->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Paket atau template tidak ditemukan pada tahun anggaran aktif.');
        }
        if ($validator->validate($package)) {
            return back()->with('error', 'Dokumen dari template belum dapat dibuat. Lengkapi data wajib terlebih dahulu.');
        }

        $school = School::query()->findOrFail(session('active_school_id'));
        $numbers->assignAutomaticNumbers($package, $school->school_code ?: $school->npsn, $school->npsn);
        $package->refresh();
        $documentType = strtoupper($template->document_type);
        $document = $numbers->assign(
            $package,
            $documentType,
            $this->documentEventDate($package, $documentType),
            $school->school_code ?: $school->npsn,
            templateId: $template->id,
            npsn: $school->npsn,
        );

        // Template lama menggunakan NOMOR_SPJ. Nilai ini hanya diganti di memori
        // agar setiap jenis dokumen menerima nomornya sendiri tanpa menimpa nomor SPJ.
        $package->setAttribute('document_number', $document->document_number);

        return $templates->download($template, $package, $school);
    }

    public function previewTemplate(string $packageId, string $templateId, SpjPackageValidationService $validator, SpjTemplateService $templates): View|RedirectResponse
    {
        $package = SpjPackage::query()->with(['transaction.items', 'transaction.goods', 'transaction.workers', 'transaction.participants', 'transaction.travels'])->find($packageId);
        $template = DocumentTemplate::query()->find($templateId);
        if (! $package || ! $template || $package->transaction->fiscal_year_id !== (int) session('active_fiscal_year_id') || $template->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Paket atau template tidak ditemukan pada tahun anggaran aktif.');
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

    public function export(Request $request, string $format)
    {
        [$packages, $summary] = $this->report($request);
        if ($format === 'pdf') {
            return Pdf::loadView('spj-reports.pdf', compact('packages', 'summary'))
                ->setPaper('a4', 'landscape')
                ->stream('REKAP-SPJ-'.$summary['year'].'.pdf');
        }
        abort_unless($format === 'xlsx', 404);
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Rekap SPJ');
        $sheet->fromArray(['No', 'Nomor SPJ', 'No Bukti', 'Tanggal', 'Penerima', 'Kegiatan', 'Rekening', 'Bruto', 'Pajak', 'Dibayarkan', 'Status'], null, 'A1');
        foreach ($packages as $index => $package) {
            $t = $package->transaction;
            $sheet->fromArray([[$index + 1, $package->document_number, $t->no_bukti, optional($t->transaction_date)->format('d-m-Y'), $t->recipient_name, $t->activity_name, $t->account_name, (float) $t->gross_amount, (float) $t->tax_total, (float) $t->net_amount, $package->status]], null, 'A'.($index + 2));
        }
        foreach (['H', 'I', 'J'] as $column) {
            $sheet->getStyle($column.'2:'.$column.($packages->count() + 1))->getNumberFormat()->setFormatCode('#,##0');
        }
        foreach (range('A', 'K') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $this->addRealizationSheet($book, 'Per Kegiatan', $summary['activities'], 'activity_code', 'activity_name');
        $this->addRealizationSheet($book, 'Per Rekening', $summary['accounts'], 'account_code', 'account_name');
        $path = storage_path('app/generated-documents/rekap-spj-'.uniqid().'.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        (new Xlsx($book))->save($path);

        return response()->download($path, 'REKAP-SPJ-'.$summary['year'].'.xlsx')->deleteFileAfterSend(true);
    }

    public function exportHonorPayments(Request $request, string $format)
    {
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);
        $year = FiscalYear::query()->findOrFail(session('active_fiscal_year_id'));
        $school = School::query()->findOrFail(session('active_school_id'));
        $honors = SpjHonor::query()
            ->with(['item.transaction.spjPackage'])
            ->whereHas('item.transaction', function ($query) use ($request): void {
                $query->activeContext()->where('spj_category', 'HONOR_PEGAWAI');
                if ($request->filled('month')) {
                    $query->whereMonth('transaction_date', $request->integer('month'));
                }
                if ($request->filled('quarter')) {
                    $quarter = $request->integer('quarter');
                    $query->whereMonth('transaction_date', '>=', (($quarter - 1) * 3) + 1)
                        ->whereMonth('transaction_date', '<=', $quarter * 3);
                }
                if ($request->filled('semester')) {
                    $semester = $request->integer('semester');
                    $query->whereMonth('transaction_date', '>=', $semester === 1 ? 1 : 7)
                        ->whereMonth('transaction_date', '<=', $semester === 1 ? 6 : 12);
                }
            })
            ->get()
            ->sortBy(fn (SpjHonor $honor) => sprintf(
                '%s-%010d-%010d-%010d',
                $honor->item->transaction->transaction_date?->format('Y-m-d') ?? '',
                $honor->item->transaction_id,
                $honor->sort_order,
                $honor->id
            ))
            ->values();
        $summary = [
            'gross' => $honors->sum(fn (SpjHonor $honor) => (float) $honor->gross_amount),
            'pph21' => $honors->sum(fn (SpjHonor $honor) => (float) $honor->tax_amount),
            'net' => $honors->sum(fn (SpjHonor $honor) => (float) $honor->net_amount),
        ];

        if ($format === 'pdf') {
            return Pdf::loadView('spj-reports.honor-payments', compact('honors', 'summary', 'year', 'school'))
                ->setPaper('a4', 'landscape')
                ->stream('DAFTAR-PEMBAYARAN-HONOR-'.$year->year.'.pdf');
        }

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Pembayaran Honor');
        $sheet->fromArray(['No', 'No Bukti', 'Nomor SPJ', 'Tanggal', 'Penerima', 'Jabatan/Jenis Honor', 'Bulan/Kali', 'Tarif', 'Bruto', 'PPh 21', 'Dibayarkan', 'Tanda Tangan'], null, 'A1');
        foreach ($honors as $index => $honor) {
            $transaction = $honor->item->transaction;
            $sheet->fromArray([[$index + 1, $transaction->no_bukti, $transaction->spjPackage?->document_number, $transaction->transaction_date?->format('d-m-Y'), $honor->name, $honor->position, (float) $honor->honor_months, (float) $honor->rate_per_unit, (float) $honor->gross_amount, (float) $honor->tax_amount, (float) $honor->net_amount, ($index + 1).'. __________________']], null, 'A'.($index + 2));
        }
        $totalRow = $honors->count() + 2;
        $sheet->fromArray([['', '', '', '', '', 'TOTAL', '', '', $summary['gross'], $summary['pph21'], $summary['net'], '']], null, 'A'.$totalRow);
        foreach (['H', 'I', 'J', 'K'] as $column) {
            $sheet->getStyle($column.'2:'.$column.$totalRow)->getNumberFormat()->setFormatCode('#,##0');
        }
        foreach (range('A', 'L') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $path = storage_path('app/generated-documents/daftar-pembayaran-honor-'.uniqid().'.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        (new Xlsx($book))->save($path);

        return response()->download($path, 'DAFTAR-PEMBAYARAN-HONOR-'.$year->year.'.xlsx')->deleteFileAfterSend(true);
    }

    private function report(Request $request, ?int $perPage = null, ?int $pendingPerPage = null): array
    {
        $year = FiscalYear::query()->findOrFail(session('active_fiscal_year_id'));
        $transactionFilter = fn ($query) => $this->applyReportTransactionFilters($query->activeContext(), $request, $year);
        $packageQuery = SpjPackage::query()->with(['transaction', 'documents'])
            ->whereHas('transaction', $transactionFilter)
            ->where(function ($query): void {
                $query->whereNotNull('document_number')
                    ->orWhereHas('documents', fn ($document) => $document
                        ->where('document_type', 'SPJ')
                        ->where('scope_key', 'MAIN')
                        ->where('status', 'CANCELLED'));
            })
            ->orderByRaw("COALESCE((SELECT sequence_number FROM spj_documents WHERE spj_documents.spj_package_id = spj_packages.id AND document_type = 'SPJ' ORDER BY id DESC LIMIT 1), 2147483647)")
            ->orderBy('spj_packages.id');

        $decorate = function ($packages) {
            return $packages->map(function (SpjPackage $package): SpjPackage {
                $cancelledDocument = $package->documents
                    ->where('document_type', 'SPJ')
                    ->where('scope_key', 'MAIN')
                    ->where('status', 'CANCELLED')
                    ->sortByDesc('id')
                    ->first();
                $package->setAttribute('report_document_number', $package->document_number ?: $cancelledDocument?->document_number);
                $package->setAttribute('report_status', $package->document_number ? $package->status : 'CANCELLED');
                $package->setAttribute('report_cancellation_reason', $package->document_number ? null : $cancelledDocument?->cancellation_reason);

                return $package;
            });
        };

        if ($perPage) {
            $packages = $packageQuery->paginate($perPage, ['*'], 'page')->withQueryString();
            $packages->setCollection($decorate($packages->getCollection()));
        } else {
            $packages = $decorate($packageQuery->get())->values();
        }

        $pendingQuery = Transaction::query()->with('spjPackage.documents')
            ->tap(fn ($query) => $this->applyReportTransactionFilters($query->activeContext(), $request, $year))
            ->has('items')
            ->where(function ($query): void {
                $query->doesntHave('spjPackage')
                    ->orWhereHas('spjPackage', fn ($package) => $package->whereNull('document_number'));
            })
            ->orderBy('transaction_date')
            ->orderBy('id');
        $pendingTransactions = $pendingPerPage
            ? $pendingQuery->paginate($pendingPerPage, ['*'], 'pending_page')->withQueryString()
            : $pendingQuery->get();

        $activities = Transaction::query()->activeContext()
            ->selectRaw("COALESCE(activity_code, '-') as activity_code, COALESCE(activity_name, 'Kegiatan belum diisi') as activity_name, SUM(gross_amount) as realization")
            ->groupBy('activity_code', 'activity_name')->orderByDesc('realization')->get();
        $accounts = Transaction::query()->activeContext()
            ->selectRaw("COALESCE(account_code, '-') as account_code, COALESCE(account_name, 'Rekening belum diisi') as account_name, SUM(gross_amount) as realization")
            ->groupBy('account_code', 'account_name')->orderBy('account_code')->get();

        $successfulTransactions = Transaction::query()
            ->tap(fn ($query) => $this->applyReportTransactionFilters($query->activeContext(), $request, $year))
            ->whereHas('spjPackage', fn ($package) => $package->whereNotNull('document_number'));
        $successfulSummary = (clone $successfulTransactions)->selectRaw('COUNT(*) as aggregate_count, COALESCE(SUM(gross_amount), 0) as gross, COALESCE(SUM(tax_total), 0) as tax, COALESCE(SUM(net_amount), 0) as net, COALESCE(SUM(ppn), 0) as ppn, COALESCE(SUM(pph21), 0) as pph21, COALESCE(SUM(pph22), 0) as pph22, COALESCE(SUM(pph23), 0) as pph23, COALESCE(SUM(pph4), 0) as pph4, COALESCE(SUM(sspd), 0) as sspd')->first();
        $cancelledCount = SpjPackage::query()
            ->whereHas('transaction', $transactionFilter)
            ->whereNull('document_number')
            ->whereHas('documents', fn ($document) => $document->where(['document_type' => 'SPJ', 'scope_key' => 'MAIN', 'status' => 'CANCELLED']))
            ->count();

        return [$packages, [
            'year' => $year->year,
            'count' => (int) $successfulSummary->aggregate_count,
            'cancelled_count' => $cancelledCount,
            'gross' => (float) $successfulSummary->gross,
            'tax' => (float) $successfulSummary->tax,
            'net' => (float) $successfulSummary->net,
            'ppn' => (float) $successfulSummary->ppn,
            'pph21' => (float) $successfulSummary->pph21,
            'pph22' => (float) $successfulSummary->pph22,
            'pph23' => (float) $successfulSummary->pph23,
            'pph4' => (float) $successfulSummary->pph4,
            'sspd' => (float) $successfulSummary->sspd,
            'pending_transactions' => $pendingTransactions,
            'activities' => $activities,
            'accounts' => $accounts,
        ]];
    }

    private function applyReportTransactionFilters($query, Request $request, FiscalYear $year)
    {
        if ($request->filled('month')) {
            $query->whereMonth('transaction_date', $request->integer('month'));
        }
        if ($request->filled('quarter')) {
            $quarter = $request->integer('quarter');
            $query->whereBetween('transaction_date', [now()->setYear($year->year)->setMonth(($quarter - 1) * 3 + 1)->startOfMonth(), now()->setYear($year->year)->setMonth($quarter * 3)->endOfMonth()]);
        }
        if ($request->filled('semester')) {
            $semester = $request->integer('semester');
            $query->whereBetween('transaction_date', [now()->setYear($year->year)->setMonth($semester === 1 ? 1 : 7)->startOfMonth(), now()->setYear($year->year)->setMonth($semester === 1 ? 6 : 12)->endOfMonth()]);
        }

        return $query;
    }

    private function addRealizationSheet(Spreadsheet $book, string $title, $rows, string $code, string $name): void
    {
        $sheet = $book->createSheet()->setTitle($title);
        $sheet->fromArray(['No', 'Kode', 'Nama', 'Realisasi'], null, 'A1');
        foreach ($rows as $index => $row) {
            $sheet->fromArray([[$index + 1, $row->{$code}, $row->{$name}, (float) $row->realization]], null, 'A'.($index + 2));
        }
        $sheet->getStyle('D2:D'.($rows->count() + 1))->getNumberFormat()->setFormatCode('#,##0');
        foreach (range('A', 'D') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    private function quarter(Transaction $transaction): string
    {
        $month = (int) $transaction->transaction_date?->format('n');

        return 'TW-'.(int) ceil(max(1, $month) / 3);
    }

    private function semester(Transaction $transaction): string
    {
        return ((int) $transaction->transaction_date?->format('n') <= 6) ? 'SEM-I' : 'SEM-II';
    }

    private function documentEventDate(SpjPackage $package, string $documentType): Carbon
    {
        $transaction = $package->transaction;

        $date = match ($documentType) {
            'ORDER', 'PESANAN', 'SURAT_PESANAN' => $transaction->goods->pluck('order_date')->filter()->sort()->first(),
            'BAP' => $transaction->goods->pluck('bap_date')->filter()->sort()->first(),
            'BAST', 'RECEIPT', 'PENERIMAAN' => $transaction->goods->pluck('bast_date')->filter()->sort()->first(),
            'SPK', 'WORK_ORDER' => $transaction->workOrder?->spk_date,
            'RAB' => $transaction->workOrder?->rab_date,
            'SURAT_TUGAS_PERJALANAN_DINAS' => $transaction->travels->pluck('assignment_letter_date')->filter()->sort()->first()
                ?: $transaction->travels->pluck('departure_date')->filter()->sort()->first(),
            'SPPD' => $transaction->travels->pluck('departure_date')->filter()->sort()->first(),
            default => $transaction->transaction_date,
        };

        return Carbon::parse($date ?? now());
    }
}
