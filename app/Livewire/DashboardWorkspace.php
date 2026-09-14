<?php

namespace App\Livewire;

use App\Services\ProductivityDashboardDataService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class DashboardWorkspace extends Component
{
    public function render(ProductivityDashboardDataService $dashboardData): View
    {
        $data = $dashboardData->getData();
        $summary = $data['summary'];
        $completed = $summary['numbered'] + $summary['final'];
        $notNumbered = $summary['without_package'] + $summary['draft'] + $summary['ready'];
        $workflowTotal = $notNumbered + $completed;
        $workQueue = $data['workQueue'];

        $nextUnworkedTransaction = $workQueue->first(fn ($transaction): bool => $transaction->spjPackage === null);
        $nextDraftTransaction = $workQueue->first(fn ($transaction): bool => $transaction->spjPackage?->status === 'DRAFT');

        return view('livewire.dashboard-workspace', [
            ...$data,
            'productivity' => [
                'unworked' => $summary['without_package'],
                'in_progress' => $summary['draft'],
                'ready' => $summary['ready'],
                'attention' => $data['attentionCount'],
                'numbered' => $summary['numbered'],
                'final' => $summary['final'],
                'not_numbered' => $notNumbered,
                'completed' => $completed,
                'workflow_total' => $workflowTotal,
                'completion_percent' => $workflowTotal > 0 ? (int) round(($completed / $workflowTotal) * 100) : 0,
            ],
            'nextUnworkedTransaction' => $nextUnworkedTransaction,
            'nextDraftTransaction' => $nextDraftTransaction,
            'priority' => [
                'eyebrow' => $data['startHere']['priority'],
                'title' => $data['startHere']['title'],
                'description' => $data['startHere']['description'],
                'action' => $data['startHere']['action'],
                'url' => $data['startHere']['url'],
            ],
            'workQueueTotal' => $summary['workable'],
        ]);
    }
}
