<?php

use App\Services\ArkasReferenceResolver;

return [
    'reference_read_mode' => env('ARKAS_REFERENCE_READ_MODE', ArkasReferenceResolver::CENTRAL_COMPAT),
    'reference_release' => env('ARKAS_REFERENCE_RELEASE', '2026.09'),
];
