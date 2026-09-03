<?php

namespace App\Providers;

use App\Http\Middleware\EnsureActiveFiscalYear;
use App\Http\Middleware\EnsureActiveSchool;
use App\Models\FiscalYear;
use App\Models\School;
use App\Services\SchoolDatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Livewire::addPersistentMiddleware([
            EnsureActiveSchool::class,
            EnsureActiveFiscalYear::class,
        ]);

        Model::preventLazyLoading(! $this->app->isProduction());
        if (config('performance.enabled')) {
            DB::listen(function (QueryExecuted $query): void {
                if ($query->time >= config('performance.slow_query_ms')) {
                    Log::channel('performance')->warning('Slow query', [
                        'connection' => $query->connectionName,
                        'duration_ms' => $query->time,
                        'sql' => $query->sql,
                        'school_id' => session('active_school_id'),
                        'fiscal_year_id' => session('active_fiscal_year_id'),
                    ]);
                }
            });
        }

        View::composer('components.layouts.tailwind-app', function ($view): void {
            $schoolId = (int) session('active_school_id');
            $school = $schoolId ? Cache::remember('school:'.$schoolId.':profile', 300, fn () => School::query()->find($schoolId)) : null;
            $years = collect();
            if ($school) {
                try {
                    app(SchoolDatabaseManager::class)->activate($school);
                    $years = Cache::remember('school:'.$school->id.':header-years', 300, fn () => FiscalYear::query()
                        ->with('fundSource')
                        ->whereNotNull('fund_source_id')
                        ->where('is_active', true)
                        ->whereExists(function ($query): void {
                            $query->selectRaw('1')
                                ->from('arkas_rkas_items')
                                ->whereColumn('arkas_rkas_items.fiscal_year_id', 'fiscal_years.id')
                                ->whereColumn('arkas_rkas_items.fund_source_id', 'fiscal_years.fund_source_id');
                        })
                        ->whereNotExists(function ($query): void {
                            $query->selectRaw('1')
                                ->from('fiscal_years as duplicate_years')
                                ->whereColumn('duplicate_years.year', 'fiscal_years.year')
                                ->whereColumn('duplicate_years.fund_source_id', 'fiscal_years.fund_source_id')
                                ->whereColumn('duplicate_years.id', '<', 'fiscal_years.id');
                        })
                        ->orderByDesc('year')
                        ->orderBy('fund_source')
                        ->get());
                } catch (\Throwable) {
                    // The header remains usable when a newly added school has no local database yet.
                }
            }
            $view->with([
                'headerYears' => $years,
                'activeFiscalYearId' => (int) session('active_fiscal_year_id'),
            ]);
        });
    }
}
