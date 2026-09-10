<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
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
        }

        foreach ($data['items'] as $itemData) {
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
        [$previousTransaction, $nextTransaction] = $this->adjacentTransactions($transaction);

        // Catatan: daftar pegawai sengaja tidak dimuat di sini. Data pegawai
        // hanya dibutuhkan di modul SPJ, bukan di detail transaksi.
        return view('transactions.show', compact(
            'transaction',
            'headerVisual',
            'paymentMethod',
            'previousTransaction',
            'nextTransaction'
        ));
    }

    /** @return array{0: ?Transaction, 1: ?Transaction} */
    private function adjacentTransactions(Transaction $transaction): array
    {
        $baseQuery = fn () => Transaction::query()->activeContext();
        $transactionDate = $transaction->transaction_date;

        if ($transactionDate === null) {
            $previous = ($baseQuery())
                ->whereNull('transaction_date')
                ->where('id', '<', $transaction->id)
                ->orderByDesc('id')
                ->first();

            $next = ($baseQuery())
                ->where(function (Builder $query) use ($transaction): void {
                    $query->where(function (Builder $query) use ($transaction): void {
                        $query->whereNull('transaction_date')->where('id', '>', $transaction->id);
                    })->orWhereNotNull('transaction_date');
                })
                ->orderByRaw('transaction_date IS NULL DESC')
                ->orderBy('transaction_date')
                ->orderBy('id')
                ->first();

            return [$previous, $next];
        }

        $previous = ($baseQuery())
            ->where(function (Builder $query) use ($transaction, $transactionDate): void {
                $query->whereNull('transaction_date')
                    ->orWhere('transaction_date', '<', $transactionDate)
                    ->orWhere(function (Builder $query) use ($transaction, $transactionDate): void {
                        $query->where('transaction_date', $transactionDate)->where('id', '<', $transaction->id);
                    });
            })
            ->orderByRaw('transaction_date IS NULL DESC')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->first();

        $next = ($baseQuery())
            ->where(function (Builder $query) use ($transaction, $transactionDate): void {
                $query->where('transaction_date', '>', $transactionDate)
                    ->orWhere(function (Builder $query) use ($transaction, $transactionDate): void {
                        $query->where('transaction_date', $transactionDate)->where('id', '>', $transaction->id);
                    });
            })
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->first();

        return [$previous, $next];
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
