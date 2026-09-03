<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class MeasureRequestPerformance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('performance.enabled')) {
            return $next($request);
        }

        $startedAt = hrtime(true);
        $response = $next($request);
        $durationMs = round((hrtime(true) - $startedAt) / 1_000_000, 2);
        $response->headers->set('Server-Timing', 'app;dur='.$durationMs);

        if ($durationMs >= config('performance.slow_request_ms')) {
            Log::channel('performance')->warning('Slow request', [
                'method' => $request->method(), 'route' => $request->route()?->getName(),
                'path' => $request->path(), 'duration_ms' => $durationMs,
                'user_id' => $request->user()?->id, 'school_id' => session('active_school_id'),
                'fiscal_year_id' => session('active_fiscal_year_id'),
            ]);
        }

        return $response;
    }
}
