<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpjVerifyCommandTest extends TestCase
{
    public function test_verify_command_can_report_static_checkpoint_without_requiring_real_tenant(): void
    {
        $this->artisan('spj:verify', [
            '--skip-style' => true,
            '--skip-build' => true,
            '--skip-tests' => true,
        ])
            ->expectsOutputToContain('SPJ VERIFICATION KIT')
            ->expectsOutputToContain('real tenant audit masih RVR')
            ->assertExitCode(0);
    }
}
