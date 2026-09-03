<?php

use App\Http\Middleware\EnsureActiveFiscalYear;
use App\Http\Middleware\EnsureActiveSchool;
use App\Http\Middleware\EnsureAdministrator;
use App\Http\Middleware\EnsureOperatorOrAdministrator;
use App\Http\Middleware\MeasureRequestPerformance;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(MeasureRequestPerformance::class);
        $middleware->alias([
            'active-year' => EnsureActiveFiscalYear::class,
            'active-school' => EnsureActiveSchool::class,
            'administrator' => EnsureAdministrator::class,
            'operator-or-administrator' => EnsureOperatorOrAdministrator::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
