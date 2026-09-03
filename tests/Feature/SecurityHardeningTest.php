<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_endpoint_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/masuk')
                ->post('/masuk', [
                    'email' => 'invalid@example.test',
                    'password' => 'invalid-password',
                ]);
        }

        $this->post('/masuk', [
            'email' => 'invalid@example.test',
            'password' => 'invalid-password',
        ])->assertTooManyRequests();
    }
}
