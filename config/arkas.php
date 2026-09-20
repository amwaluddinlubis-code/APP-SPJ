<?php

use App\Services\ArkasReferenceResolver;

return [
    'reference_read_mode' => env('ARKAS_REFERENCE_READ_MODE', ArkasReferenceResolver::CENTRAL_COMPAT),
];
