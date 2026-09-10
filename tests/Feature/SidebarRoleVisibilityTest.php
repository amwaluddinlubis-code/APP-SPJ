<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarRoleVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_sees_no_write_actions(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_VIEWER]));

        // Aserti berbasis URL karena kamus x-ui-language memuat label mentah di semua halaman.
        $response = $this->get('/asisten')->assertOk();
        $response->assertSee('data-sinkron', false);
        $response->assertSee('pilih-sekolah', false);
        $response->assertDontSee('sinkronisasi/arkas', false);
        $response->assertDontSee('pengaturan/user', false);
        $response->assertDontSee('pengaturan/format-penomoran', false);
        $response->assertDontSee('pengaturan/impersonate', false);
    }

    public function test_operator_sees_sync_but_no_admin_menu(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_OPERATOR]));

        $response = $this->get('/asisten')->assertOk();
        $response->assertSee('sinkronisasi/arkas', false);
        $response->assertSee('pengaturan/format-penomoran', false);
        $response->assertSee('pilih-sekolah', false);
        $response->assertDontSee('pengaturan/user', false);
        $response->assertDontSee('pengaturan/impersonate', false);
        $response->assertDontSee('pengaturan/database-reset', false);
    }

    public function test_admin_sees_everything(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $response = $this->get('/asisten')->assertOk();
        $response->assertSee('sinkronisasi/arkas', false);
        $response->assertSee('pengaturan/user', false);
        $response->assertSee('pengaturan/format-penomoran', false);
        $response->assertSee('pilih-sekolah', false);
    }
}
