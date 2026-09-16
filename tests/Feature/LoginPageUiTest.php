<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginPageUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_redesigned_markup(): void
    {
        User::factory()->create();

        $response = $this->get('/masuk');

        $response->assertOk();
        $response->assertSee('Masuk ke SPJ BOSP', false);
        $response->assertSee('Gunakan akun sekolah yang telah terdaftar', false);
        $response->assertSee('Lupa kata sandi?', false);
        $response->assertSee('logo-app-spj.png', false);
        $response->assertSee('auth-login-card', false);
        $response->assertSee('data-theme-toggle', false);
        $response->assertSee('data-theme-icon="sun"', false);
        $response->assertSee('data-theme-icon="moon"', false);
        $response->assertDontSee('data-theme-selector', false);
        $response->assertDontSee('AKSES APLIKASI', false);
    }
}
