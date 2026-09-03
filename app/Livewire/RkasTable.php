<?php

namespace App\Livewire;

use App\Models\ArkasRkasItem;
use App\Models\FiscalYear;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class RkasTable extends Component implements HasActions, HasForms, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $fiscalYearId = (int) session('active_fiscal_year_id');

        return $table
            ->query(
                ArkasRkasItem::query()
                    ->where('fiscal_year_id', $fiscalYearId)
                    ->where('fund_source_id', session('active_fund_source_id'))
            )
            ->columns([
                TextColumn::make('row_number')->label('No')->rowIndex()->alignCenter()->width('50px'),
                TextColumn::make('account_code')->label('Kode Rekening')->searchable()->sortable()->copyable()->width('140px'),
                TextColumn::make('activity_code')->label('Kode Program')->searchable()->sortable()->width('110px'),
                TextColumn::make('description')->label('Uraian')->searchable()->wrap()->limit(50),
                TextColumn::make('volume')->label('Rincian Perhitungan')->state(function (ArkasRkasItem $record): string {
                    $p = $record->payload ?? [];
                    $vol = $p['VOLUME_TOTAL'] ?? $p['VOL_TW1'] ?? '-';
                    $sat = $p['SATUAN'] ?? '-';
                    $tarif = isset($p['HARGA_SATUAN']) ? 'Rp '.number_format((float) $p['HARGA_SATUAN'], 0, ',', '.') : '-';

                    return trim("{$vol} {$sat} × {$tarif}");
                })->wrap()->limit(40)->toggleable(),
                TextColumn::make('amount')->label('Jumlah')->money('IDR', 0)->sortable()->alignEnd()->summarize(Sum::make()->money('IDR', 0)),
            ])
            ->filters([
                SelectFilter::make('bulan')->label('Bulan')->options([
                    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
                ])->query(function ($query, $state) {
                    if (! $state['value']) {
                        return;
                    }
                    $tw = (int) ceil((int) $state['value'] / 3);
                    $query->whereRaw("json_extract(payload, '$.TW_{$tw}') > 0");
                }),
                SelectFilter::make('triwulan')->label('Triwulan')->options([1 => 'Triwulan 1', 2 => 'Triwulan 2', 3 => 'Triwulan 3', 4 => 'Triwulan 4'])->query(function ($query, $state) {
                    if (! $state['value']) {
                        return;
                    }
                    $tw = (int) $state['value'];
                    $query->whereRaw("json_extract(payload, '$.TW_{$tw}') > 0");
                }),
                SelectFilter::make('semester')->label('Semester')->options([1 => 'Semester 1', 2 => 'Semester 2'])->query(function ($query, $state) {
                    if (! $state['value']) {
                        return;
                    }
                    if ((int) $state['value'] === 1) {
                        $query->whereRaw("(json_extract(payload,'$.TW_1')+json_extract(payload,'$.TW_2'))>0");
                    } else {
                        $query->whereRaw("(json_extract(payload,'$.TW_3')+json_extract(payload,'$.TW_4'))>0");
                    }
                }),
                SelectFilter::make('tahun')->label('Tahun')->options(function () {
                    $years = FiscalYear::query()
                        ->whereNotNull('fund_source_id')
                        ->with('fundSource')
                        ->orderByDesc('year')
                        ->get()
                        ->mapWithKeys(fn ($y) => [$y->id => $y->year.' · '.($y->fundSource?->name ?? $y->fund_source)]);

                    return $years->toArray();
                })->query(function ($query, $state) {
                    if (! $state['value']) {
                        return;
                    }
                    $query->where('fiscal_year_id', (int) $state['value']);
                })->default((int) session('active_fiscal_year_id')),
            ])
            ->searchPlaceholder('Cari kode rekening, uraian, program…')
            ->paginated([15, 25, 50, 100])
            ->defaultPaginationPageOption(15)
            ->defaultSort('account_code')
            ->striped()
            ->emptyStateHeading('Belum ada RKAS')
            ->emptyStateDescription('Data akan muncul setelah sinkron ARKAS.');
    }

    public function render(): View
    {
        return view('livewire.rkas-table');
    }
}
