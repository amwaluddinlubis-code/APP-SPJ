<?php

namespace App\Services;

use RuntimeException;

/**
 * Internal control-flow exception for effective-context numbering issuance.
 *
 * Throwing this inside the issuance transaction boundary guarantees a full
 * rollback; the public service converts it into a fail-closed result array.
 * It must never escape to controllers or the UI layer.
 */
final class SpjV2IssuanceBlocked extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
