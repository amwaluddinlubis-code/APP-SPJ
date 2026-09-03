<?php

namespace App\Livewire;

use App\Models\ArkasRkasItem;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Concerns\RestrictsFileUploadsToSchemaComponents;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class RkasBudgetTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;
    use RestrictsFileUploadsToSchemaComponents;

    public int $fiscalYearId;

    public function mount(): void
    {
        $this->fiscalYearId = (int) session('active_fiscal_year_id');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ArkasRkasItem::query()->where('fiscal_year_id', $this->fiscalYearId))
            ->columns([
                TextColumn::make('account_code')->label('Kode Rekening')->searchable()->sortable()->copyable(),
                TextColumn::make('description')->label('Uraian / Barang')->searchable()->wrap()->limit(70),
                TextColumn::make('activity_code')->label('Kode Kegiatan')->searchable()->sortable(),
                TextColumn::make('activity_name')->label('Nama Kegiatan')->searchable()->wrap()->limit(55)->toggleable(),
                TextColumn::make('amount')->label('Anggaran')->money('IDR', 0)->sortable()->alignEnd(),
                TextColumn::make('created_at')->label('Disinkronkan')->dateTime('d-m-Y H:i')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('activity_code')
            ->searchPlaceholder('Cari rekening, uraian, atau kegiatan…')
            ->emptyStateHeading('Belum ada RKAS')
            ->emptyStateDescription('Jalankan Sinkron Semua ARKAS untuk mengisi data anggaran.')
            ->paginated([15, 25, 50, 100])
            ->defaultPaginationPageOption(15);
    }

    public function render(): View
    {
        return view('livewire.rkas-budget-table');
    }
}
