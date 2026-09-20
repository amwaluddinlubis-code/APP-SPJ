<?php

namespace Tests\Feature;

use Tests\TestCase;

final class ArkasSynchronizationQueueTest extends TestCase
{
    public function test_sidebar_exposes_reference_and_school_sync_in_order(): void
    {
        $layout = file_get_contents(resource_path('views/components/layouts/tailwind-app.blade.php'));

        self::assertStringContainsString("route('years.synchronize')", $layout);
        self::assertStringContainsString('1. Sinkron Referensi &amp; Tahun', $layout);
        self::assertStringContainsString("route('arkas.raw-mirror')", $layout);
        self::assertStringContainsString('2. Sinkron Data Sekolah', $layout);
        self::assertTrue(
            strpos($layout, "route('years.synchronize')")
                < strpos($layout, "route('arkas.raw-mirror')"),
        );
    }

    public function test_web_arkas_sync_always_queues_the_long_running_raw_mirror(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/ArkasSyncController.php'));

        self::assertStringContainsString("->onConnection('database')", $controller);
        self::assertStringContainsString("->onQueue('operations')", $controller);
        self::assertStringNotContainsString('dispatchSync', $controller);
    }
}
