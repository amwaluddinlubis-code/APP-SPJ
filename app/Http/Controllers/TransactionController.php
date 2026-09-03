<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Transaction;
use App\Services\SpjTransactionDetailsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    /** Simpan uraian manual tanpa mengubah uraian sumber dari ARKAS. */
    public function updateManualDescription(Request $request, string $transactionId): RedirectResponse
    {
        $transaction = Transaction::query()->find($transactionId);
        if (! $transaction || $transaction->fiscal_year_id !== (int) session('active_fiscal_year_id') || (int) $transaction->fund_source_id !== (int) session('active_fund_source_id')) {
            return redirect()->route('transactions.index')->with('error', 'Transaksi tidak ditemukan pada tahun aktif.');
        }
        if ($transaction->spjPackage && ! $transaction->spjPackage->isEditable()) {
            return back()->with('error', 'Transaksi dikunci karena paket SPJ sudah bernomor atau final.');
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
            'spj_category' => ['nullable', 'in:BARANG,KONSUMSI,PEMELIHARAAN,JASA_LAINNYA,SPPD,HONOR_PEGAWAI,BELANJA_MODAL,PERJALANAN_DINAS,'],
            'payment_description' => ['nullable', 'string', 'max:4000'],
            'payment_method' => ['nullable', 'in:transfer_bank,siplah,tunai'],
            'payment_reference' => ['nullable', 'string', 'max:160'],
            'receipt_recipient_name' => ['nullable', 'string', 'max:255'],
            'order_number' => ['nullable', 'string', 'max:80'],
            'order_date' => ['required_if:spj_category,KONSUMSI', 'nullable', 'date', 'before_or_equal:'.$maximumDocumentDate],
            'bap_number' => ['nullable', 'string', 'max:80'],
            'bap_date' => ['required_if:spj_category,KONSUMSI', 'nullable', 'date', 'after_or_equal:order_date', 'before_or_equal:'.$maximumDocumentDate],
            'bast_number' => ['nullable', 'string', 'max:80'],
            'bast_date' => ['required_if:spj_category,KONSUMSI', 'nullable', 'date', 'after_or_equal:bap_date', 'before_or_equal:'.$maximumDocumentDate],
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
            'participants' => ['nullable', 'array'],
            'participants.*.name' => ['nullable', 'string', 'max:180'],
            'participants.*.position' => ['nullable', 'string', 'max:180'],
            'participants.*.portions' => ['nullable', 'numeric', 'min:1', function (string $attribute, mixed $value, \Closure $fail): void {
                if (abs((float) $value - round((float) $value)) > 0.000001) {
                    $fail('Jumlah porsi harus berupa bilangan bulat tanpa koma atau desimal.');
                }
            }],
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
        ]);
        $legacyTypes = ['BELANJA_MODAL' => 'BARANG', 'PERJALANAN_DINAS' => 'SPPD', 'JASA_HONORARIUM' => 'HONOR_PEGAWAI', 'UPAH' => 'PEMELIHARAAN', 'LAINNYA' => 'JASA_LAINNYA'];
        $spjCategory = $legacyTypes[$data['spj_category'] ?? ''] ?? ($data['spj_category'] ?? null);
        $paymentMethod = $this->normalizePaymentMethod($data['payment_method'] ?? null, $transaction);
        $transaction->update([
            'payment_description' => blank($data['payment_description'] ?? null) ? null : trim($data['payment_description']),
            'spj_category' => blank($spjCategory) ? null : $spjCategory,
            'payment_method' => $paymentMethod,
            ...collect($data)->only([
                'payment_reference',
                'receipt_recipient_name',
                'signatory_name',
                'signatory_role',
            ])->map(
                fn ($value) => is_string($value) && filled($value) ? trim($value) : ($value ?: null)
            )->all(),
        ]);
        $transaction->load('items');
        app(SpjTransactionDetailsService::class)->synchronize($transaction, $data);

        return back()->with('success', 'Rincian Pembayaran dan kategori SPJ berhasil disimpan.');
    }

    public function updateSpjDescriptions(Request $request, string $transactionId): RedirectResponse
    {
        $transaction = Transaction::query()->with('items')->find($transactionId);
        if (! $transaction || $transaction->fiscal_year_id !== (int) session('active_fiscal_year_id') || (int) $transaction->fund_source_id !== (int) session('active_fund_source_id')) {
            return redirect()->route('transactions.index')->with('error', 'Transaksi tidak ditemukan pada tahun aktif.');
        }
        if ($transaction->spjPackage && ! $transaction->spjPackage->isEditable()) {
            return back()->with('error', 'Uraian item dikunci karena paket SPJ sudah bernomor atau final.');
        }

        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.item_description' => ['required', 'string', 'max:4000'],
        ]);

        $itemIds = $transaction->items->pluck('id')->all();
        foreach ($data['items'] as $itemData) {
            if (! in_array((int) $itemData['id'], $itemIds, true)) {
                abort(422, 'Rincian transaksi tidak valid.');
            }

            $transaction->items->firstWhere('id', (int) $itemData['id'])->update([
                'item_description' => trim($itemData['item_description']),
            ]);
        }

        return back()->with('success', 'Uraian barang/jasa untuk SPJ berhasil disimpan.');
    }

    public function index(Request $request): View
    {
        return view('transactions.index');
    }

    public function show(string $transactionId): View|RedirectResponse
    {
        $transaction = Transaction::query()->find($transactionId);
        if (! $transaction || $transaction->fiscal_year_id !== (int) session('active_fiscal_year_id') || (int) $transaction->fund_source_id !== (int) session('active_fund_source_id')) {
            return redirect()->route('transactions.index')->with(
                'error',
                'Transaksi tidak ditemukan pada sekolah atau tahun anggaran yang sedang aktif. Jalankan sinkronisasi ARKAS atau buka transaksi dari daftar.'
            );
        }
        $transaction->load([
            'items' => fn ($query) => $query->orderBy('id'),
            'goods',
            'workers',
            'participants',
            'travels',
            'honors',
            'workOrder',
            'spjPackage',
        ]);
        $headerVisual = $this->headerVisual($transaction);
        $paymentMethod = $this->normalizePaymentMethod($transaction->payment_method, $transaction);
        $employmentStatusId = static function (Employee $employee): int {
            $statusId = $employee->payload['status_kepegawaian_id']
                ?? $employee->payload['STATUS_KEPEGAWAIAN_ID']
                ?? null;

            return is_numeric($statusId) ? (int) $statusId : PHP_INT_MAX;
        };
        $dapodikEmployees = Employee::query()->where('is_active', true)
            ->orderBy('name')->get(['id', 'name', 'nip', 'nuptk', 'position', 'staff_type', 'source_type', 'payload'])
            ->sortBy(fn (Employee $employee) => sprintf(
                '%s-%d-%d',
                mb_strtolower(trim($employee->name)),
                $employee->source_type === 'DAPODIK' ? 0 : 1,
                filled($employee->nuptk) ? 0 : 1
            ))
            // Satu orang dapat berasal dari Dapodik, PTK, atau ARKAS.
            // Rekaman dengan nama sama hanya ditampilkan sekali.
            ->unique(fn (Employee $employee) => mb_strtolower(trim($employee->name)))
            ->sortBy(fn (Employee $employee) => sprintf(
                '%010d-%s',
                $employmentStatusId($employee),
                mb_strtolower(trim($employee->name))
            ))
            ->values();
        $dapodikTeachers = $dapodikEmployees;

        return view('transactions.show', compact('transaction', 'headerVisual', 'paymentMethod', 'dapodikEmployees', 'dapodikTeachers'));
    }

    /** Selects a local header visual from the SPJ category, account, and description. */
    private function headerVisual(Transaction $transaction): array
    {
        $haystack = mb_strtolower(implode(' ', [
            $transaction->spj_category, $transaction->account_code, $transaction->account_name,
            $transaction->description, $transaction->activity_name,
        ]));

        if ($transaction->spj_category === 'HONOR_PEGAWAI' || str_contains($haystack, 'honor')) {
            return ['label' => 'Honor Pegawai', 'image' => null];
        }
        if (str_contains($haystack, 'buku')) {
            return ['label' => 'Belanja Buku', 'image' => 'images/spj-categories/belanja-buku.png'];
        }
        if (str_starts_with((string) $transaction->account_code, '5.2')) {
            return ['label' => 'Belanja Modal Peralatan dan Mesin', 'image' => 'images/spj-categories/belanja-modal.png'];
        }
        if (str_contains($haystack, 'makanan') || str_contains($haystack, 'minuman') || str_contains($haystack, 'konsumsi')) {
            return ['label' => 'Belanja Konsumsi', 'image' => 'images/spj-categories/belanja-konsumsi.png'];
        }
        if (str_starts_with((string) $transaction->account_code, '5.1.02.04') || str_contains($haystack, 'perjalanan')) {
            return ['label' => 'Perjalanan Dinas', 'image' => 'images/spj-categories/perjalanan-dinas.png'];
        }
        if (str_starts_with((string) $transaction->account_code, '5.1.02.03') || str_contains($haystack, 'pemeliharaan')) {
            return ['label' => 'Jasa Pemeliharaan', 'image' => 'images/spj-categories/jasa-pemeliharaan.png'];
        }

        return ['label' => 'Barang Habis Pakai / ATK / Peralatan Olahraga dll', 'image' => 'images/spj-categories/barang-habis-pakai.png'];
    }

    private function normalizePaymentMethod(?string $value, Transaction $transaction): string
    {
        $value = strtolower(trim((string) $value));

        if (in_array($value, ['transfer_bank', 'siplah', 'tunai'], true)) {
            return $value;
        }

        if ($transaction->is_siplah) {
            return 'siplah';
        }

        $proofNumber = strtolower((string) $transaction->no_bukti);
        if (str_contains($proofNumber, 'non_tunai') || str_contains($proofNumber, 'non tunai') || str_starts_with($proofNumber, 'bnu')) {
            return 'transfer_bank';
        }

        if (str_contains($value, 'transfer') || str_contains($value, 'cms') || str_contains($value, 'non tunai')) {
            return 'transfer_bank';
        }

        return 'tunai';
    }
}
