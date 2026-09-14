<?php

namespace App\Livewire;

use App\Http\Controllers\RkasBudgetController;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Full read-only RKAS budgeting workspace.
 *
 * The controller remains the query/data adapter while this component owns
 * the rendered page surface and its Livewire lifecycle.
 */
class RkasBudgetWorkspace extends Component
{
    public function render(RkasBudgetController $budgetController): View
    {
        return view('livewire.rkas-budget-workspace', [
            ...$budgetController->renderData(request()),
            'renderedByWorkspace' => true,
        ]);
    }
}
