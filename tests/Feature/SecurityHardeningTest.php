<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_endpoint_is_rate_limited(): void
    {
        $csrfToken = 'security-hardening-test-token';
        $credentials = [
            '_token' => $csrfToken,
            'email' => 'invalid@example.test',
            'password' => 'invalid-password',
        ];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withSession(['_token' => $csrfToken])
                ->from('/masuk')
                ->post('/masuk', $credentials);
        }

        $this->withSession(['_token' => $csrfToken])
            ->post('/masuk', $credentials)
            ->assertTooManyRequests();
    }
}
