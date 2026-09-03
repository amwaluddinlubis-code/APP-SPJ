<?php

namespace App\Http\Controllers;

use App\Models\BackgroundOperation;
use App\Models\FiscalYear;
use App\Models\School;
use App\Models\SpjPackage;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OperationalDashboardController extends Controller
{
    public function __invoke(): View
    {
        $year = FiscalYear::query()->findOrFail(session('active_fiscal_year_id'));
        $school = School::query()->find(session('active_school_id'));

        $transactions = Transaction::query()->activeContext();
        $packages = SpjPackage::query()->whereHas('transaction', fn ($query) => $query->activeContext());

        $summary = [
            'transactions' => (clone $transactions)->count(),
            'without_package' => (clone $transactions)->has('items')->doesntHave('spjPackage')->count(),
            'draft' => (clone $packages)->where('status', 'DRAFT')->count(),
            'ready' => (clone $packages)->where('status', 'READY')->count(),
            'numbered' => (clone $packages)->where('status', 'NUMBERED')->count(),
            'final' => (clone $packages)->where('status', 'FINAL')->count(),
            'reconciliation' => (clone $transactions)->where('requires_reconciliation', true)->count(),
            'source_missing' => (clone $transactions)->where('source_status', 'SOURCE_MISSING')->count(),
        ];

        $attentionCount = $summary['without_package'] + $summary['draft'] + $summary['reconciliation'] + $summary['source_missing'];

        $quarterSummary = collect(range(1, 4))->map(function (int $quarter) use ($transactions): array {
            $startMonth = (($quarter - 1) * 3) + 1;
            $endMonth = $quarter * 3;
            $quarterTransactions = (clone $transactions)
                ->whereMonth('transaction_date', '>=', $startMonth)
                ->whereMonth('transaction_date', '<=', $endMonth);

            $total = (clone $quarterTransactions)->count();
            $withItems = (clone $quarterTransactions)->has('items')->count();
            $ready = (clone $quarterTransactions)->whereHas('spjPackage', fn ($query) => $query->where('status', 'READY'))->count();
            $numbered = (clone $quarterTransactions)->whereHas('spjPackage', fn ($query) => $query->whereIn('status', ['NUMBERED', 'FINAL']))->count();
            $blocked = (clone $quarterTransactions)->has('items')->where(function ($query): void {
                $query->doesntHave('spjPackage')
                    ->orWhereHas('spjPackage', fn ($package) => $package->where('status', 'DRAFT'));
            })->count();

            return compact('quarter', 'total', 'withItems', 'ready', 'numbered', 'blocked');
        });

        $workQueue = Transaction::query()
            ->activeContext()
            ->with(['spjPackage:id,transaction_id,status,document_number'])
            ->withCount('items')
            ->where(function ($query): void {
                $query->where('requires_reconciliation', true)
                    ->orWhere('source_status', 'SOURCE_MISSING')
                    ->orWhereDoesntHave('spjPackage')
                    ->orWhereHas('spjPackage', fn ($package) => $package->whereIn('status', ['DRAFT', 'READY']));
            })
            ->orderByRaw("CASE WHEN source_status = 'SOURCE_MISSING' THEN 0 WHEN requires_reconciliation = 1 THEN 1 ELSE 2 END")
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->limit(8)
            ->get();

        $latestSync = DB::connection('school')->table('sync_runs')
            ->where('fiscal_year_id', $year->id)
            ->latest('started_at')
            ->first();

        $latestOperation = BackgroundOperation::query()
            ->where('school_id', $school?->id)
            ->where('fiscal_year_id', $year->id)
            ->latest('id')
            ->first();

        $nextActions = collect();

        if ($latestSync?->status === 'FAILED' || $latestOperation?->status === 'FAILED') {
            $nextActions->push([
                'priority' => 'Mendesak', 'tone' => 'rose',
                'title' => 'Periksa proses sinkronisasi yang gagal',
                'description' => 'Data operasional sebaiknya tidak diproses lebih lanjut sebelum kegagalan sinkronisasi diperiksa.',
                'action' => 'Periksa integrasi ARKAS', 'url' => route('arkas.settings'),
            ]);
        }

        if ($summary['source_missing'] > 0) {
            $nextActions->push([
                'priority' => 'Mendesak', 'tone' => 'rose',
                'title' => $summary['source_missing'].' transaksi tidak muncul lagi di sinkronisasi',
                'description' => 'Tinjau transaksi sumber yang hilang sebelum melanjutkan finalisasi dokumen terkait.',
                'action' => 'Tinjau data yang hilang', 'url' => route('reconciliation.index', ['filter' => 'missing']),
            ]);
        }

        if ($summary['reconciliation'] > 0) {
            $nextActions->push([
                'priority' => 'Perlu perhatian', 'tone' => 'orange',
                'title' => $summary['reconciliation'].' transaksi perlu rekonsiliasi',
                'description' => 'Data ARKAS/BKU berubah setelah transaksi pernah diproses. Bandingkan dengan data SPJ operator.',
                'action' => 'Buka rekonsiliasi', 'url' => route('reconciliation.index', ['filter' => 'changed']),
            ]);
        }

        if ($summary['without_package'] > 0) {
            $nextActions->push([
                'priority' => 'Kerjakan berikutnya', 'tone' => 'amber',
                'title' => $summary['without_package'].' transaksi belum memiliki paket SPJ',
                'description' => 'Buka transaksi, lengkapi data SPJ operator, lalu siapkan paket dokumennya.',
                'action' => 'Siapkan paket SPJ', 'url' => route('spj.index', ['tab' => 'persiapan', 'state' => 'unprepared']),
            ]);
        }

        if ($summary['draft'] > 0) {
            $nextActions->push([
                'priority' => 'Kerjakan berikutnya', 'tone' => 'amber',
                'title' => $summary['draft'].' paket masih belum lengkap',
                'description' => 'Lengkapi isian paket dan selesaikan validasi sampai statusnya Siap diproses.',
                'action' => 'Lanjutkan paket draft', 'url' => route('spj.index', ['tab' => 'persiapan', 'state' => 'draft']),
            ]);
        }

        $readyQuarters = $quarterSummary->filter(fn (array $row) => $row['blocked'] === 0 && $row['ready'] > 0);
        if ($readyQuarters->isNotEmpty()) {
            $quarter = $readyQuarters->first();
            $nextActions->push([
                'priority' => 'Siap diproses', 'tone' => 'sky',
                'title' => 'Triwulan '.$quarter['quarter'].' siap ditinjau untuk penomoran',
                'description' => $quarter['ready'].' paket berstatus Siap diproses dan tidak ada paket draft yang menghambat triwulan ini.',
                'action' => 'Preview penomoran', 'url' => route('spj.numbering-workflow', ['quarter' => $quarter['quarter']]),
            ]);
        } elseif ($summary['ready'] > 0) {
            $nextActions->push([
                'priority' => 'Siap diproses', 'tone' => 'sky',
                'title' => $summary['ready'].' paket sudah siap, tetapi triwulan masih memiliki kendala',
                'description' => 'Buka workspace penomoran untuk melihat paket mana yang masih menghambat proses batch.',
                'action' => 'Periksa kesiapan triwulan', 'url' => route('spj.numbering-workflow'),
            ]);
        }

        if ($nextActions->isEmpty()) {
            $nextActions->push([
                'priority' => 'Terkendali', 'tone' => 'emerald',
                'title' => 'Tidak ada pekerjaan prioritas yang tertunda',
                'description' => 'Antrean utama bersih. Anda dapat memeriksa transaksi terbaru, laporan, atau menunggu sinkronisasi berikutnya.',
                'action' => 'Lihat semua transaksi', 'url' => route('transactions.index'),
            ]);
        }

        $nextActions = $nextActions->take(4)->values();

        return view('dashboard-operational-v3', compact(
            'school', 'year', 'summary', 'attentionCount', 'quarterSummary', 'workQueue',
            'latestSync', 'latestOperation', 'nextActions'
        ));
    }
}
