<?php

namespace App\Services;

use Illuminate\Database\Connection;

final class SpjReadPathSelector
{
    public const LEGACY = 'legacy';

    public const V2 = 'v2';

    public function __construct(
        private readonly SpjV2CanonicalSourceResolver $sources,
    ) {}

    /**
     * Select the production read path conservatively.
     *
     * V2 is allowed only when explicitly requested and one canonical source can
     * be resolved for the active fiscal-year/fund-source context. Every invalid,
     * unavailable, or ambiguous state falls back to legacy.
     *
     * @return array{path:string,requested:string,source_id:?int,source_status:string,reason:string}
     */
    public function select(Connection $db, int $fiscalYearId, int $fundSourceId): array
    {
        $requested = strtolower(trim((string) config('spj.v2_read_path', self::LEGACY)));

        if (! in_array($requested, [self::LEGACY, self::V2], true)) {
            return [
                'path' => self::LEGACY,
                'requested' => $requested,
                'source_id' => null,
                'source_status' => 'NOT_RESOLVED',
                'reason' => 'invalid SPJ V2 read-path configuration; legacy fallback applied',
            ];
        }

        if ($requested === self::LEGACY) {
            return [
                'path' => self::LEGACY,
                'requested' => self::LEGACY,
                'source_id' => null,
                'source_status' => 'NOT_RESOLVED',
                'reason' => 'legacy read path is configured',
            ];
        }

        $source = $this->sources->resolve($db, $fiscalYearId, $fundSourceId);
        if ($source['status'] !== 'RESOLVED' || $source['source_id'] === null) {
            return [
                'path' => self::LEGACY,
                'requested' => self::V2,
                'source_id' => null,
                'source_status' => $source['status'],
                'reason' => $source['reason'].'; legacy fallback applied',
            ];
        }

        return [
            'path' => self::V2,
            'requested' => self::V2,
            'source_id' => $source['source_id'],
            'source_status' => $source['status'],
            'reason' => 'V2 read path explicitly configured with a unique canonical source',
        ];
    }
}
