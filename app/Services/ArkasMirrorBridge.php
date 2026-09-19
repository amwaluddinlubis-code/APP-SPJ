<?php

namespace App\Services;

interface ArkasMirrorBridge
{
    public function scope(): string;

    /** @return array<int, string> */
    public function sourceTables(): array;
}
