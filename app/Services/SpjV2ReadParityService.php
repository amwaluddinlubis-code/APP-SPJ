<?php

namespace App\Services;

use Illuminate\Database\Connection;
use RuntimeException;

final class SpjV2ReadParityService
{
    /** @return array<string, mixed> */
    public function compare(Connection $db): array
    {
        $required = [
            'spj_fresh_transactions',
            'spj_fresh_transaction_items',
            'spj_transactions',
            'spj_transaction_sources',
            'spj_transaction_overlays',
            'spj_item_overlays',
            'arkas_source_identity_registry',
        ];

        foreach ($required as $table) {
            if (! $db->getSchemaBuilder()->hasTable($table)) {
                throw new RuntimeException('V2-D shadow parity requires table: '.$table);
            }
        }

        $freshRows = $db->table('spj_fresh_transactions')
            ->where('source_status', 'ACTIVE')
            ->orderBy('id')
            ->get();
        $v2Rows = $db->table('spj_transactions')
            ->where('source_status', 'ACTIVE')
            ->orderBy('id')
            ->get();

        $freshByKey = [];
        foreach ($freshRows as $row) {
            $freshByKey[$this->freshKey($row)] = $row;
        }

        $v2ByKey = [];
        foreach ($v2Rows as $row) {
            $v2ByKey[$this->v2Key($row)] = $row;
        }

        $freshKeys = array_keys($freshByKey);
        $v2Keys = array_keys($v2ByKey);
        sort($freshKeys);
        sort($v2Keys);

        $missingInV2 = array_values(array_diff($freshKeys, $v2Keys));
        $missingInFresh = array_values(array_diff($v2Keys, $freshKeys));
        $sharedKeys = array_values(array_intersect($freshKeys, $v2Keys));

        $freshMembership = $this->freshMembership($db, $freshByKey);
        $v2Membership = $this->v2Membership($db, $v2ByKey);
        $membershipMismatches = [];
        $transactionOverlayMismatches = [];
        $itemOverlayMismatches = [];

        $v2Overlays = $db->table('spj_transaction_overlays')->get()->keyBy('spj_transaction_id');
        $freshItemOverlays = $this->freshItemOverlays($db, $freshByKey);
        $v2ItemOverlays = $this->v2ItemOverlays($db, $v2ByKey);

        foreach ($sharedKeys as $key) {
            if (($freshMembership[$key] ?? []) !== ($v2Membership[$key] ?? [])) {
                $membershipMismatches[] = $key;
            }

            $fresh = $freshByKey[$key];
            $v2Overlay = $v2Overlays->get($v2ByKey[$key]->id);
            if ($v2Overlay === null || $this->freshOverlay($fresh) !== $this->v2Overlay($v2Overlay)) {
                $transactionOverlayMismatches[] = $key;
            }

            if (($freshItemOverlays[$key] ?? []) !== ($v2ItemOverlays[$key] ?? [])) {
                $itemOverlayMismatches[] = $key;
            }
        }

        $conflicts = [
            'transaction' => $this->conflictCount($db, 'spj_transaction_overlays'),
            'item' => $this->conflictCount($db, 'spj_item_overlays'),
        ];

        $status = $missingInV2 === []
            && $missingInFresh === []
            && $membershipMismatches === []
            && $transactionOverlayMismatches === []
            && $itemOverlayMismatches === []
            && max($conflicts) === 0
            ? 'PASS'
            : 'FAIL';

        return [
            'status' => $status,
            'counts' => [
                'fresh_transactions' => count($freshByKey),
                'v2_transactions' => count($v2ByKey),
                'shared_transactions' => count($sharedKeys),
            ],
            'identity' => [
                'missing_in_v2_count' => count($missingInV2),
                'missing_in_fresh_count' => count($missingInFresh),
                'missing_in_v2' => $missingInV2,
                'missing_in_fresh' => $missingInFresh,
            ],
            'source_membership' => [
                'mismatch_count' => count($membershipMismatches),
                'mismatched_keys' => $membershipMismatches,
            ],
            'transaction_overlay' => [
                'mismatch_count' => count($transactionOverlayMismatches),
                'mismatched_keys' => $transactionOverlayMismatches,
                'conflict_count' => $conflicts['transaction'],
            ],
            'item_overlay' => [
                'mismatch_count' => count($itemOverlayMismatches),
                'mismatched_keys' => $itemOverlayMismatches,
                'conflict_count' => $conflicts['item'],
            ],
        ];
    }

    /** @param array<string, object> $freshByKey
     *  @return array<string, array<int, string>>
     */
    private function freshMembership(Connection $db, array $freshByKey): array
    {
        $keyById = [];
        foreach ($freshByKey as $key => $row) {
            $keyById[(int) $row->id] = $key;
        }

        $membership = [];
        if ($keyById === []) {
            return $membership;
        }

        foreach ($db->table('spj_fresh_transaction_items')
            ->whereIn('spj_fresh_transaction_id', array_keys($keyById))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get() as $item) {
            $key = $keyById[(int) $item->spj_fresh_transaction_id];
            $membership[$key][] = trim((string) $item->source_key);
        }

        foreach ($membership as &$sourceKeys) {
            $sourceKeys = array_values(array_unique(array_filter($sourceKeys)));
            sort($sourceKeys);
        }
        unset($sourceKeys);

        return $membership;
    }

    /** @param array<string, object> $v2ByKey
     *  @return array<string, array<int, string>>
     */
    private function v2Membership(Connection $db, array $v2ByKey): array
    {
        $keyById = [];
        foreach ($v2ByKey as $key => $row) {
            $keyById[(int) $row->id] = $key;
        }

        $membership = [];
        if ($keyById === []) {
            return $membership;
        }

        foreach ($db->table('spj_transaction_sources as source_link')
            ->join('arkas_source_identity_registry as identity', 'identity.id', '=', 'source_link.arkas_source_identity_id')
            ->whereIn('source_link.spj_transaction_id', array_keys($keyById))
            ->orderBy('source_link.sort_order')
            ->orderBy('source_link.id')
            ->get(['source_link.spj_transaction_id', 'identity.source_key']) as $item) {
            $key = $keyById[(int) $item->spj_transaction_id];
            $membership[$key][] = trim((string) $item->source_key);
        }

        foreach ($membership as &$sourceKeys) {
            $sourceKeys = array_values(array_unique(array_filter($sourceKeys)));
            sort($sourceKeys);
        }
        unset($sourceKeys);

        return $membership;
    }

    /** @param array<string, object> $freshByKey
     *  @return array<string, array<string, string|null>>
     */
    private function freshItemOverlays(Connection $db, array $freshByKey): array
    {
        $keyById = [];
        foreach ($freshByKey as $key => $row) {
            $keyById[(int) $row->id] = $key;
        }

        $overlays = [];
        if ($keyById === []) {
            return $overlays;
        }

        foreach ($db->table('spj_fresh_transaction_items')
            ->whereIn('spj_fresh_transaction_id', array_keys($keyById))
            ->get(['spj_fresh_transaction_id', 'source_key', 'item_description']) as $item) {
            $key = $keyById[(int) $item->spj_fresh_transaction_id];
            $overlays[$key][trim((string) $item->source_key)] = $this->normalize($item->item_description);
        }

        foreach ($overlays as &$values) {
            ksort($values);
        }
        unset($values);

        return $overlays;
    }

    /** @param array<string, object> $v2ByKey
     *  @return array<string, array<string, string|null>>
     */
    private function v2ItemOverlays(Connection $db, array $v2ByKey): array
    {
        $keyById = [];
        foreach ($v2ByKey as $key => $row) {
            $keyById[(int) $row->id] = $key;
        }

        $overlays = [];
        if ($keyById === []) {
            return $overlays;
        }

        foreach ($db->table('spj_transaction_sources as source_link')
            ->join('arkas_source_identity_registry as identity', 'identity.id', '=', 'source_link.arkas_source_identity_id')
            ->leftJoin('spj_item_overlays as overlay', 'overlay.spj_transaction_source_id', '=', 'source_link.id')
            ->whereIn('source_link.spj_transaction_id', array_keys($keyById))
            ->get(['source_link.spj_transaction_id', 'identity.source_key', 'overlay.item_description']) as $item) {
            $key = $keyById[(int) $item->spj_transaction_id];
            $overlays[$key][trim((string) $item->source_key)] = $this->normalize($item->item_description);
        }

        foreach ($overlays as &$values) {
            ksort($values);
        }
        unset($values);

        return $overlays;
    }

    /** @return array<string, string|null> */
    private function freshOverlay(object $row): array
    {
        return [
            'spj_category' => $this->normalize($row->spj_category ?? null),
            'payment_description' => $this->normalize($row->payment_description ?? null),
            'payment_method' => $this->normalize($row->payment_method ?? null),
            'payment_reference' => $this->normalize($row->payment_reference ?? null),
            'receipt_recipient_name' => $this->normalize($row->receipt_recipient_name ?? null),
        ];
    }

    /** @return array<string, string|null> */
    private function v2Overlay(object $row): array
    {
        return [
            'spj_category' => $this->normalize($row->spj_category ?? null),
            'payment_description' => $this->normalize($row->payment_description ?? null),
            'payment_method' => $this->normalize($row->payment_method ?? null),
            'payment_reference' => $this->normalize($row->payment_reference ?? null),
            'receipt_recipient_name' => $this->normalize($row->receipt_recipient_name ?? null),
        ];
    }

    private function conflictCount(Connection $db, string $table): int
    {
        return (int) $db->table($table)
            ->whereNotNull('operator_metadata')
            ->whereRaw("json_type(operator_metadata, '$._v2_reconciliation_conflicts') = 'object'")
            ->whereRaw("json_array_length(json_object('keys', json_extract(operator_metadata, '$._v2_reconciliation_conflicts'))) > 0")
            ->count();
    }

    private function freshKey(object $row): string
    {
        return implode(':', [
            (string) $row->fiscal_year_id,
            (string) $row->fund_source_id,
            (string) $row->source_id,
            trim((string) $row->source_key),
        ]);
    }

    private function v2Key(object $row): string
    {
        return implode(':', [
            (string) $row->fiscal_year_id,
            (string) $row->fund_source_id,
            (string) $row->source_id,
            trim((string) $row->source_membership_hash),
        ]);
    }

    private function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
