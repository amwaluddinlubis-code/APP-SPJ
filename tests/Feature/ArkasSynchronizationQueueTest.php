<?php

namespace Tests\Feature;

use Tests\TestCase;

final class ArkasSynchronizationQueueTest extends TestCase
{
    public function test_web_arkas_sync_always_queues_the_long_running_raw_mirror(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/ArkasSyncController.php'));

        self::assertStringContainsString("->onConnection('database')", $controller);
        self::assertStringContainsString("->onQueue('operations')", $controller);
        self::assertStringNotContainsString('dispatchSync', $controller);
    }
}
