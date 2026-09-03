<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOperatorOrAdministrator
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            in_array($request->user()?->role, [User::ROLE_ADMIN, User::ROLE_OPERATOR], true),
            403,
            'Pengaturan ini hanya dapat diakses administrator atau operator.'
        );

        return $next($request);
    }
}
