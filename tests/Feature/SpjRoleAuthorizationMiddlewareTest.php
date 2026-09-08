<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdministrator;
use App\Http\Middleware\EnsureOperatorOrAdministrator;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SpjRoleAuthorizationMiddlewareTest extends TestCase
{
    public function test_viewer_is_denied_by_operator_or_administrator_middleware(): void
    {
        try {
            $this->runOperatorGuard(User::ROLE_VIEWER);
            $this->fail('VIEWER unexpectedly passed operator-or-administrator guard.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_operator_and_administrator_pass_operator_guard(): void
    {
        $this->assertSame(204, $this->runOperatorGuard(User::ROLE_OPERATOR)->getStatusCode());
        $this->assertSame(204, $this->runOperatorGuard(User::ROLE_ADMIN)->getStatusCode());
    }

    public function test_only_administrator_passes_administrator_guard(): void
    {
        foreach ([User::ROLE_VIEWER, User::ROLE_OPERATOR] as $role) {
            try {
                $this->runAdministratorGuard($role);
                $this->fail($role.' unexpectedly passed administrator guard.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }

        $this->assertSame(204, $this->runAdministratorGuard(User::ROLE_ADMIN)->getStatusCode());
    }

    private function runOperatorGuard(string $role): Response
    {
        $request = $this->requestFor($role);

        return app(EnsureOperatorOrAdministrator::class)->handle(
            $request,
            fn () => new Response('', 204)
        );
    }

    private function runAdministratorGuard(string $role): Response
    {
        $request = $this->requestFor($role);

        return app(EnsureAdministrator::class)->handle(
            $request,
            fn () => new Response('', 204)
        );
    }

    private function requestFor(string $role): Request
    {
        $request = Request::create('/authorization-test', 'POST');
        $user = new User(['role' => $role]);
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}
