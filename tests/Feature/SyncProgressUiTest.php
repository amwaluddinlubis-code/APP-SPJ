<?php

namespace Tests\Feature;

use Tests\TestCase;

class SyncProgressUiTest extends TestCase
{
    public function test_global_sync_progress_covers_arkas_and_dapodik_with_clear_stages(): void
    {
        $javascript = file_get_contents(resource_path('js/sync-progress.js'));
        $bootstrap = file_get_contents(resource_path('js/bootstrap.js'));

        $this->assertStringContainsString('/sinkronisasi/arkas', $javascript);
        $this->assertStringContainsString('/pengaturan/dapodik/sinkron', $javascript);
        $this->assertStringContainsString('Sinkronisasi ARKAS', $javascript);
        $this->assertStringContainsString('Sinkronisasi Dapodik', $javascript);
        $this->assertStringContainsString('Membaca RKAS dan BKU', $javascript);
        $this->assertStringContainsString('Mengambil data GTK', $javascript);
        $this->assertStringContainsString('Mengambil data Peserta Didik', $javascript);
        $this->assertStringContainsString('Persentase menunjukkan tahapan proses', $javascript);
        $this->assertStringContainsString("import './sync-progress';", $bootstrap);
    }

    public function test_progress_only_reaches_one_hundred_after_server_response(): void
    {
        $javascript = file_get_contents(resource_path('js/sync-progress.js'));

        $responsePosition = strpos($javascript, 'const response = await fetch');
        $completedPosition = strpos($javascript, 'currentPercent = 100');

        $this->assertNotFalse($responsePosition);
        $this->assertNotFalse($completedPosition);
        $this->assertGreaterThan($responsePosition, $completedPosition);
        $this->assertStringContainsString('Server masih menyelesaikan penyimpanan dan validasi akhir', $javascript);
    }
}
