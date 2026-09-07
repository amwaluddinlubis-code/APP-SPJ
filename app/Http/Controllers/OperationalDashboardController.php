<?php

namespace App\Http\Controllers;

use App\Models\BackgroundOperation;
use App\Models\FiscalYear;
use App\Models\School;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Services\SpjPackageValidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OperationalDashboardController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        if (request()->routeIs('dashboard.operational')) {
            return redirect()->route('dashboard');
        }

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

        $pipeline = [
            ['key' => 'unprepared', 'label' => 'Belum disentuh', 'count' => $summary['without_package'], 'description' => 'Transaksi belum memiliki paket SPJ.', 'url' => route('spj.index', ['tab' => 'persiapan', 'state' => 'unprepared']), 'action' => 'Mulai lengkapi'],
            ['key' => 'draft', 'label' => 'Perlu dilengkapi', 'count' => $summary['draft'], 'description' => 'Paket dibuat tetapi belum siap dinomori.', 'url' => route('spj.index', ['tab' => 'persiapan', 'state' => 'draft']), 'action' => 'Buka checklist'],
            ['key' => 'ready', 'label' => 'Siap dinomori', 'count' => $summary['ready'], 'description' => 'Paket lengkap dan menunggu penomoran.', 'url' => route('spj.numbering-workflow'), 'action' => 'Tinjau penomoran'],
            ['key' => 'done', 'label' => 'Selesai', 'count' => $summary['numbered'] + $summary['final'], 'description' => 'Paket sudah bernomor atau final.', 'url' => route('spj.index', ['tab' => 'paket']), 'action' => 'Lihat paket'],
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
                    ->orWhereHas('spjPackage', fn ($package) => $package->where('status', 'DRAFT'));
            })
            ->orderByRaw("CASE WHEN source_status = 'SOURCE_MISSING' THEN 0 WHEN requires_reconciliation = 1 THEN 1 WHEN EXISTS (SELECT 1 FROM spj_packages WHERE spj_packages.transaction_id = transactions.id AND spj_packages.status = 'DRAFT') THEN 2 ELSE 3 END")
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->limit(8)
            ->get()
            ->map(function (Transaction $transaction): Transaction {
                $transaction->queue_state = $transaction->requires_reconciliation || $transaction->source_status === 'SOURCE_MISSING'
                    ? 'attention' : 'incomplete';

                if (! $transaction->spjPackage) {
                    $transaction->next_step = 'Lengkapi data SPJ lalu siapkan paket.';
                    $transaction->next_step_url = route('transactions.show', $transaction->id).'#modul-buat-spj';
                    $transaction->completion_checks = [];

                    return $transaction;
                }

                if ($transaction->spjPackage->status === 'DRAFT') {
                    $checks = app(SpjPackageValidationService::class)->checklist(
                        $transaction->spjPackage->loadMissing(['transaction.items', 'transaction.goods', 'transaction.goodsReceipts'])
                    );
                    $transaction->completion_checks = collect($checks)->take(4)->all();
                    $transaction->next_step = collect($checks)->where('passed', false)->pluck('label')->take(2)->implode(' · ') ?: 'Buka checklist untuk melengkapi paket.';
                    $transaction->next_step_url = route('spj.checklist', $transaction->spjPackage->id);

                    return $transaction;
                }

                $transaction->next_step = 'Tinjau status paket dan sumber data.';
                $transaction->next_step_url = route('spj.index', ['tab' => 'paket', 'package_id' => $transaction->spjPackage->id]);
                $transaction->completion_checks = [];

                return $transaction;
            });

        $firstDraftPackage = (clone $packages)
            ->where('status', 'DRAFT')
            ->orderBy('id')
            ->first();

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

        if ($summary['draft'] > 0) {
            $nextActions->push([
                'priority' => 'Kerjakan berikutnya', 'tone' => 'amber',
                'title' => $summary['draft'].' paket masih belum lengkap',
                'description' => 'Buka checklist paket untuk melihat persis data apa yang masih kurang sebelum status dapat menjadi READY.',
                'action' => 'Buka checklist paket',
                'url' => $firstDraftPackage ? route('spj.checklist', $firstDraftPackage->id) : route('spj.index', ['tab' => 'persiapan', 'state' => 'draft']),
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
        $startHere = $nextActions->first();
        $otherActions = $nextActions->skip(1)->values();

        return view('dashboard-operational-v3', compact(
            'school', 'year', 'summary', 'attentionCount', 'quarterSummary', 'workQueue',
            'latestSync', 'latestOperation', 'nextActions', 'startHere', 'otherActions', 'pipeline'
        ));
    }
}
