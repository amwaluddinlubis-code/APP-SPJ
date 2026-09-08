<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\SpjWorkflowFilterService;
use Illuminate\View\View;

class ProductivityDashboardController extends Controller
{
    public function __invoke(
        OperationalDashboardController $operationalDashboard,
        SpjWorkflowFilterService $workflowFilters,
    ): View {
        $legacyDashboard = $operationalDashboard();
        $data = $legacyDashboard->getData();

        $baseTransactions = Transaction::query()->activeContext();

        $unworkedQuery = $workflowFilters->apply(clone $baseTransactions, 'unprepared');
        $draftQuery = $workflowFilters->apply(clone $baseTransactions, 'draft');
        $readyQuery = $workflowFilters->apply(clone $baseTransactions, 'ready');

        $productivity = [
            'unworked' => (clone $unworkedQuery)->count(),
            'in_progress' => (clone $draftQuery)->count(),
            'ready' => (clone $readyQuery)->count(),
            'attention' => (clone $workflowFilters->apply(clone $baseTransactions, 'attention'))->count(),
            'numbered' => (int) ($data['summary']['numbered'] ?? 0),
            'final' => (int) ($data['summary']['final'] ?? 0),
        ];

        $productivity['not_numbered'] = $productivity['unworked'] + $productivity['in_progress'] + $productivity['ready'];
        $productivity['completed'] = $productivity['numbered'] + $productivity['final'];
        $productivity['workflow_total'] = $productivity['not_numbered'] + $productivity['completed'];
        $productivity['completion_percent'] = $productivity['workflow_total'] > 0
            ? (int) round(($productivity['completed'] / $productivity['workflow_total']) * 100)
            : 0;

        $nextUnworkedTransaction = (clone $unworkedQuery)
            ->withCount('items')
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->first();

        $nextDraftTransaction = (clone $draftQuery)
            ->with(['spjPackage:id,transaction_id,status,document_number'])
            ->withCount('items')
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->first();

        $priority = $this->priority($productivity, $nextUnworkedTransaction, $nextDraftTransaction);

        return view('dashboard-productivity', array_merge($data, compact(
            'productivity',
            'nextUnworkedTransaction',
            'nextDraftTransaction',
            'priority'
        )));
    }

    /**
     * @param  array{unworked:int,in_progress:int,ready:int,attention:int,not_numbered:int,completed:int,workflow_total:int,completion_percent:int,numbered:int,final:int}  $productivity
     * @return array{eyebrow:string,title:string,description:string,action:string,url:string}
     */
    private function priority(array $productivity, ?Transaction $nextUnworkedTransaction, ?Transaction $nextDraftTransaction): array
    {
        if ($productivity['attention'] > 0) {
            return [
                'eyebrow' => 'Perlu perhatian terlebih dahulu',
                'title' => $productivity['attention'].' transaksi perlu diperiksa',
                'description' => 'Selesaikan masalah rekonsiliasi atau data sumber terlebih dahulu agar pekerjaan SPJ berikutnya memakai data yang benar.',
                'action' => 'Buka rekonsiliasi',
                'url' => route('reconciliation.index'),
            ];
        }

        if ($productivity['in_progress'] > 0) {
            return [
                'eyebrow' => 'Prioritas kerja berikutnya',
                'title' => 'Selesaikan '.$productivity['in_progress'].' transaksi yang sudah dikerjakan',
                'description' => 'Pekerjaan yang sudah dimulai sebaiknya diselesaikan lebih dulu sampai siap dinomori sebelum membuka transaksi baru.',
                'action' => 'Lanjutkan transaksi',
                'url' => $nextDraftTransaction
                    ? route('spj.checklist', $nextDraftTransaction->spjPackage->id)
                    : route('spj.index', ['tab' => 'persiapan', 'state' => 'draft']),
            ];
        }

        if ($productivity['unworked'] > 0) {
            return [
                'eyebrow' => 'Prioritas kerja berikutnya',
                'title' => 'Mulai '.$productivity['unworked'].' transaksi Belum Dikerjakan',
                'description' => 'Kerjakan transaksi berikutnya berdasarkan urutan tanggal agar antrean tidak menumpuk dan progres mudah dipantau.',
                'action' => 'Mulai transaksi berikutnya',
                'url' => $nextUnworkedTransaction
                    ? route('transactions.show', $nextUnworkedTransaction->id).'#modul-buat-spj'
                    : route('transactions.index'),
            ];
        }

        if ($productivity['ready'] > 0) {
            return [
                'eyebrow' => 'Siap diproses',
                'title' => $productivity['ready'].' transaksi siap masuk penomoran',
                'description' => 'Tidak ada pekerjaan draft atau Belum Dikerjakan. Lanjutkan ke penomoran batch per triwulan.',
                'action' => 'Buka penomoran SPJ',
                'url' => route('spj.numbering-workflow'),
            ];
        }

        return [
            'eyebrow' => 'Antrean terkendali',
            'title' => 'Tidak ada pekerjaan SPJ utama yang tertunda',
            'description' => 'Gunakan waktu untuk memeriksa laporan, hasil sinkronisasi, atau transaksi terbaru.',
            'action' => 'Lihat semua transaksi',
            'url' => route('transactions.index'),
        ];
    }
}
