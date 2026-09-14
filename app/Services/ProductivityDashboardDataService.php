<?php

namespace App\Services;

use App\Models\FiscalYear;
use App\Models\School;
use App\Models\SpjPackage;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class ProductivityDashboardDataService
{
    /**
     * Susun seluruh data dashboard dalam segelintir query agregat.
     *
     * Basis metrik konsisten: transaksi kerja (memiliki rincian) sebagai unit
     * kerja. Satu transaksi berpaket terhitung tepat sekali sehingga progres
     * tidak pernah double-counting antara transaksi dan paket.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $year = FiscalYear::query()->findOrFail(session('active_fiscal_year_id'));
        $school = School::query()->find(session('active_school_id'));

        // 1 query: agregat transaksi (total, kerja, belum berpaket, rekonsiliasi, sumber hilang).
        $stats = Transaction::query()->activeContext()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN EXISTS (SELECT 1 FROM transaction_items WHERE transaction_items.transaction_id = transactions.id) THEN 1 ELSE 0 END) AS workable')
            ->selectRaw('SUM(CASE WHEN EXISTS (SELECT 1 FROM transaction_items WHERE transaction_items.transaction_id = transactions.id) AND NOT EXISTS (SELECT 1 FROM spj_packages WHERE spj_packages.transaction_id = transactions.id) THEN 1 ELSE 0 END) AS without_package')
            ->selectRaw('SUM(CASE WHEN requires_reconciliation THEN 1 ELSE 0 END) AS reconciliation')
            ->selectRaw("SUM(CASE WHEN source_status = 'SOURCE_MISSING' THEN 1 ELSE 0 END) AS source_missing")
            ->first();

        // 1 query: hitung paket per status.
        $packageCounts = SpjPackage::query()
            ->whereHas('transaction', fn ($query) => $query->activeContext())
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $draft = (int) ($packageCounts['DRAFT'] ?? 0);
        $ready = (int) ($packageCounts['READY'] ?? 0);
        $numbered = (int) ($packageCounts['NUMBERED'] ?? 0);
        $final = (int) ($packageCounts['FINAL'] ?? 0);

        $summary = [
            'transactions' => (int) ($stats?->total ?? 0),
            'workable' => (int) ($stats?->workable ?? 0),
            'without_package' => (int) ($stats?->without_package ?? 0),
            'draft' => $draft,
            'ready' => $ready,
            'numbered' => $numbered,
            'final' => $final,
            'reconciliation' => (int) ($stats?->reconciliation ?? 0),
            'source_missing' => (int) ($stats?->source_missing ?? 0),
        ];

        // Progres = paket bernomor/final per transaksi kerja. Basis tunggal,
        // tanpa menjumlah transaksi + paket seperti sebelumnya.
        $done = $summary['numbered'] + $summary['final'];
        $progressPercent = $summary['workable'] > 0
            ? (int) round(($done / $summary['workable']) * 100)
            : 0;
        $progressPercent = min(100, max(0, $progressPercent));

        $pipeline = [
            ['key' => 'unprepared', 'label' => 'Belum disentuh', 'count' => $summary['without_package'], 'description' => 'Transaksi kerja belum memiliki paket SPJ.', 'url' => route('spj.index', ['tab' => 'persiapan', 'state' => 'unprepared']), 'action' => 'Mulai lengkapi'],
            ['key' => 'draft', 'label' => 'Perlu dilengkapi', 'count' => $summary['draft'], 'description' => 'Paket dibuat tetapi belum siap dinomori.', 'url' => route('spj.index', ['tab' => 'persiapan', 'state' => 'draft']), 'action' => 'Buka checklist'],
            ['key' => 'ready', 'label' => 'Siap dinomori', 'count' => $summary['ready'], 'description' => 'Paket lengkap dan menunggu penomoran.', 'url' => route('spj.numbering-workflow'), 'action' => 'Tinjau penomoran'],
            ['key' => 'done', 'label' => 'Selesai', 'count' => $done, 'description' => 'Paket sudah bernomor atau final.', 'url' => route('spj.index', ['tab' => 'paket']), 'action' => 'Lihat paket'],
        ];

        // 1 query: hitung berbeda (distinct) transaksi yang perlu perhatian agar
        // transaksi yang memenuhi beberapa kondisi tidak terhitung ganda.
        $attentionCount = Transaction::query()->activeContext()
            ->where(fn ($query) => $query
                ->where('requires_reconciliation', true)
                ->orWhere('source_status', 'SOURCE_MISSING')
                ->orWhere(fn ($query) => $query->whereHas('items')->doesntHave('spjPackage'))
                ->orWhereHas('spjPackage', fn ($package) => $package->where('status', 'DRAFT')))
            ->count();

        // 1 query: ringkasan triwulan via agregat kondisional, tanpa join
        // (aman dari duplikasi bila satu transaksi memiliki >1 paket).
        $quarterRows = Transaction::query()->activeContext()
            ->whereNotNull('transaction_date')
            ->selectRaw("((CAST(strftime('%m', transactions.transaction_date) AS INTEGER) - 1) / 3) + 1 AS quarter")
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN EXISTS (SELECT 1 FROM transaction_items WHERE transaction_items.transaction_id = transactions.id) THEN 1 ELSE 0 END) AS with_items')
            ->selectRaw("SUM(CASE WHEN EXISTS (SELECT 1 FROM spj_packages WHERE spj_packages.transaction_id = transactions.id AND spj_packages.status = 'READY') THEN 1 ELSE 0 END) AS ready")
            ->selectRaw("SUM(CASE WHEN EXISTS (SELECT 1 FROM spj_packages WHERE spj_packages.transaction_id = transactions.id AND spj_packages.status IN ('NUMBERED', 'FINAL')) THEN 1 ELSE 0 END) AS numbered")
            ->selectRaw("SUM(CASE WHEN EXISTS (SELECT 1 FROM transaction_items WHERE transaction_items.transaction_id = transactions.id) AND NOT EXISTS (SELECT 1 FROM spj_packages WHERE spj_packages.transaction_id = transactions.id AND spj_packages.status IN ('READY', 'NUMBERED', 'FINAL')) THEN 1 ELSE 0 END) AS blocked")
            ->groupBy('quarter')
            ->orderBy('quarter')
            ->get()
            ->keyBy('quarter');

        $quarterSummary = collect(range(1, 4))->map(function (int $quarter) use ($quarterRows): array {
            $row = $quarterRows->get($quarter);

            return [
                'quarter' => $quarter,
                'total' => (int) ($row?->total ?? 0),
                'withItems' => (int) ($row?->with_items ?? 0),
                'ready' => (int) ($row?->ready ?? 0),
                'numbered' => (int) ($row?->numbered ?? 0),
                'blocked' => (int) ($row?->blocked ?? 0),
            ];
        });

        // Antrean: satu query + eager load. Langkah berikut diturunkan dari
        // status murah tanpa menjalankan validasi paket per baris (itu
        // dilakukan di halaman checklist saat operator membukanya).
        $workQueue = Transaction::query()
            ->activeContext()
            ->with(['spjPackage:id,transaction_id,status,document_number'])
            ->withCount('items')
            ->where(fn ($query) => $query
                ->where('requires_reconciliation', true)
                ->orWhere('source_status', 'SOURCE_MISSING')
                ->orWhereDoesntHave('spjPackage')
                ->orWhereHas('spjPackage', fn ($package) => $package->where('status', 'DRAFT')))
            ->orderByRaw("CASE WHEN source_status = 'SOURCE_MISSING' THEN 0 WHEN requires_reconciliation = 1 THEN 1 WHEN EXISTS (SELECT 1 FROM spj_packages WHERE spj_packages.transaction_id = transactions.id AND spj_packages.status = 'DRAFT') THEN 2 ELSE 3 END")
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->limit(8)
            ->get()
            ->map(function (Transaction $transaction): Transaction {
                $package = $transaction->spjPackage;

                if ($transaction->source_status === 'SOURCE_MISSING') {
                    $transaction->queue_badge = 'SOURCE_MISSING';
                    $transaction->next_step = 'Tinjau data sumber yang hilang sebelum finalisasi.';
                    $transaction->next_step_url = route('reconciliation.index', ['filter' => 'missing']);
                } elseif ($transaction->requires_reconciliation) {
                    $transaction->queue_badge = 'RECONCILIATION';
                    $transaction->next_step = 'Bandingkan perubahan ARKAS/BKU dengan data SPJ.';
                    $transaction->next_step_url = route('reconciliation.index', ['filter' => 'changed']);
                } elseif ($package === null) {
                    $transaction->queue_badge = 'BELUM_LENGKAP';
                    $transaction->next_step = 'Lengkapi data SPJ lalu siapkan paket.';
                    $transaction->next_step_url = route('transactions.show', $transaction->id).'#modul-buat-spj';
                } elseif ($package->status === 'DRAFT') {
                    $transaction->queue_badge = 'BELUM_LENGKAP';
                    $transaction->next_step = 'Buka checklist untuk melengkapi paket.';
                    $transaction->next_step_url = route('spj.checklist', $package->id);
                } else {
                    $transaction->queue_badge = null;
                    $transaction->next_step = 'Tinjau status paket dan sumber data.';
                    $transaction->next_step_url = route('spj.index', ['tab' => 'paket', 'package_id' => $package->id]);
                }

                return $transaction;
            });

        $firstDraftPackage = SpjPackage::query()
            ->whereHas('transaction', fn ($query) => $query->activeContext())
            ->where('status', 'DRAFT')
            ->orderBy('id')
            ->first(['id']);

        $latestSync = DB::connection('school')->table('sync_runs')
            ->where('fiscal_year_id', $year->id)
            ->latest('started_at')
            ->first();

        $nextActions = collect();

        if ($latestSync?->status === 'FAILED') {
            $nextActions->push([
                'priority' => 'Mendesak',
                'title' => 'Periksa proses sinkronisasi yang gagal',
                'description' => 'Data operasional sebaiknya tidak diproses lebih lanjut sebelum kegagalan sinkronisasi diperiksa.',
                'action' => 'Periksa integrasi ARKAS', 'url' => route('arkas.settings'),
            ]);
        }

        if ($summary['source_missing'] > 0) {
            $nextActions->push([
                'priority' => 'Mendesak',
                'title' => $summary['source_missing'].' transaksi tidak muncul lagi di sinkronisasi',
                'description' => 'Tinjau transaksi sumber yang hilang sebelum melanjutkan finalisasi dokumen terkait.',
                'action' => 'Tinjau data yang hilang', 'url' => route('reconciliation.index', ['filter' => 'missing']),
            ]);
        }

        if ($summary['reconciliation'] > 0) {
            $nextActions->push([
                'priority' => 'Perlu perhatian',
                'title' => $summary['reconciliation'].' transaksi perlu rekonsiliasi',
                'description' => 'Data ARKAS/BKU berubah setelah transaksi pernah diproses. Bandingkan dengan data SPJ operator.',
                'action' => 'Buka rekonsiliasi', 'url' => route('reconciliation.index', ['filter' => 'changed']),
            ]);
        }

        if ($summary['draft'] > 0) {
            $nextActions->push([
                'priority' => 'Kerjakan berikutnya',
                'title' => $summary['draft'].' paket masih belum lengkap',
                'description' => 'Buka checklist paket untuk melihat persis data apa yang masih kurang sebelum status dapat menjadi READY.',
                'action' => 'Buka checklist paket',
                'url' => $firstDraftPackage ? route('spj.checklist', $firstDraftPackage->id) : route('spj.index', ['tab' => 'persiapan', 'state' => 'draft']),
            ]);
        }

        if ($summary['without_package'] > 0) {
            $nextActions->push([
                'priority' => 'Kerjakan berikutnya',
                'title' => $summary['without_package'].' transaksi belum memiliki paket SPJ',
                'description' => 'Buka transaksi, lengkapi data SPJ operator, lalu siapkan paket dokumennya.',
                'action' => 'Siapkan paket SPJ', 'url' => route('spj.index', ['tab' => 'persiapan', 'state' => 'unprepared']),
            ]);
        }

        $readyQuarters = $quarterSummary->filter(fn (array $row) => $row['blocked'] === 0 && $row['ready'] > 0);
        if ($readyQuarters->isNotEmpty()) {
            $quarter = $readyQuarters->first();
            $nextActions->push([
                'priority' => 'Siap diproses',
                'title' => 'Triwulan '.$quarter['quarter'].' siap ditinjau untuk penomoran',
                'description' => $quarter['ready'].' paket berstatus Siap diproses dan tidak ada paket draft yang menghambat triwulan ini.',
                'action' => 'Preview penomoran', 'url' => route('spj.numbering-workflow', ['quarter' => $quarter['quarter']]),
            ]);
        } elseif ($summary['ready'] > 0) {
            $nextActions->push([
                'priority' => 'Siap diproses',
                'title' => $summary['ready'].' paket sudah siap, tetapi triwulan masih memiliki kendala',
                'description' => 'Buka workspace penomoran untuk melihat paket mana yang masih menghambat proses batch.',
                'action' => 'Periksa kesiapan triwulan', 'url' => route('spj.numbering-workflow'),
            ]);
        }

        if ($nextActions->isEmpty()) {
            $nextActions->push([
                'priority' => 'Terkendali',
                'title' => 'Tidak ada pekerjaan prioritas yang tertunda',
                'description' => 'Antrean utama bersih. Anda dapat memeriksa transaksi terbaru, laporan, atau menunggu sinkronisasi berikutnya.',
                'action' => 'Lihat semua transaksi', 'url' => route('transactions.index'),
            ]);
        }

        $nextActions = $nextActions->take(4)->values();
        $startHere = $nextActions->first();
        $otherActions = $nextActions->skip(1)->values();

        $contextLabel = trim(($school?->name ?? 'Sekolah belum dipilih').' · TA '.($year->year ?? '?').' · '.($year->fund_source ?? ''));
        $progressLabel = $done.' paket selesai dari '.$summary['workable'].' transaksi kerja';

        return compact(
            'school', 'year', 'summary', 'attentionCount', 'quarterSummary', 'workQueue',
            'latestSync', 'nextActions', 'startHere', 'otherActions', 'pipeline',
            'progressPercent', 'progressLabel', 'contextLabel'
        );
    }
}
