<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\ExternalRehearsalPrerequisite;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ExternalRehearsalPrerequisite::skipIfUnavailable($this, static::class);
    }
}
