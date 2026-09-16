<?php

namespace App\Livewire;

use App\Models\Transaction;
use App\UseCases\Spj\ExtendedSpjReportUseCase;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

class SpjReportTransactionSelector extends Component
{
    private const HONOR_SELECTION_SESSION_KEY = 'spj_report_selection.honor';

    private const SERVICE_SELECTION_SESSION_KEY = 'spj_report_selection.service';

    public string $category = 'HONOR_PEGAWAI';

    #[Url(except: null)]
    public ?int $month = null;

    #[Url(except: null)]
    public ?int $quarter = null;

    #[Url(except: null)]
    public ?int $semester = null;

    public array $selected = [];

    public function mount(string $category): void
    {
        abort_unless(in_array($category, ['HONOR_PEGAWAI', 'JASA_LAINNYA'], true), 404);
        $this->category = $category;
        $this->month = request()->integer('month') ?: null;
        $this->quarter = request()->integer('quarter') ?: null;
        $this->semester = request()->integer('semester') ?: null;
    }

    public function updating($property): void
    {
        if (in_array($property, ['month', 'quarter', 'semester'], true)) {
            $this->selected = [];
        }
    }

    public function selectAll(): void
    {
        $this->selected = $this->transactions()->modelKeys();
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function continueToCompose(): mixed
    {
        if ($this->selected === []) {
            $this->addError('selected', 'Pilih minimal satu transaksi untuk menyusun laporan.');

            return null;
        }

        $route = $this->category === 'HONOR_PEGAWAI'
            ? 'spj.honor-payments.compose'
            : 'spj.service-recipients.compose';

        session()->put($this->selectionSessionKey(), array_values(array_map('intval', $this->selected)));

        return redirect()->route($route);
    }

    public function render(): View
    {
        return view('livewire.spj-report-transaction-selector', [
            'transactions' => $this->transactions(),
            'isHonor' => $this->category === 'HONOR_PEGAWAI',
        ]);
    }

    /** @return Collection<int, Transaction> */
    private function transactions(): Collection
    {
        return app(ExtendedSpjReportUseCase::class)->selectionTransactions($this->category, [
            'month' => $this->month,
            'quarter' => $this->quarter,
            'semester' => $this->semester,
        ]);
    }

    private function selectionSessionKey(): string
    {
        return $this->category === 'HONOR_PEGAWAI'
            ? self::HONOR_SELECTION_SESSION_KEY
            : self::SERVICE_SELECTION_SESSION_KEY;
    }
}
