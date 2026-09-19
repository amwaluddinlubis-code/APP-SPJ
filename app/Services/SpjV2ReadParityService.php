<?php

namespace App\Services;

use Illuminate\Database\Connection;
use RuntimeException;

final class SpjV2ReadParityService
{
    /** @return array<string, mixed> */
    public function compare(Connection $db): array
    {
        foreach ([
            'spj_fresh_transactions', 'spj_fresh_transaction_items',
            'spj_transactions', 'spj_transaction_sources', 'spj_transaction_overlays',
            'spj_item_overlays', 'arkas_source_identity_registry',
        ] as $table) {
            if (! $db->getSchemaBuilder()->hasTable($table)) {
                throw new RuntimeException('V2-D shadow parity requires table: '.$table);
            }
        }

        $v2ByKey = [];
        foreach ($db->table('spj_transactions')->where('source_status', 'ACTIVE')->orderBy('id')->get() as $row) {
            $v2ByKey[$this->key($row, 'source_membership_hash')] = $row;
        }

        $v2Contexts = [];
        foreach ($v2ByKey as $row) {
            $v2Contexts[(string) $row->fiscal_year_id.':'.(string) $row->fund_source_id] = true;
        }

        $freshByKey = [];
        foreach ($db->table('spj_fresh_transactions')->where('source_status', 'ACTIVE')->orderBy('id')->get() as $row) {
            $context = (string) $row->fiscal_year_id.':'.(string) $row->fund_source_id;
            if (isset($v2Contexts[$context])) {
                $freshByKey[$this->key($row, 'source_key')] = $row;
            }
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
        $legacyOverlays = $this->legacyOverlays($db);
        $legacyItems = $this->legacyItemOverlays($db);
        $v2Items = $this->v2ItemOverlays($db, $v2ByKey);
        $v2Overlays = $db->table('spj_transaction_overlays')->get()->keyBy('spj_transaction_id');

        $membershipMismatches = [];
        $transactionOverlayMismatches = [];
        $itemOverlayMismatches = [];
        foreach ($sharedKeys as $key) {
            if (($freshMembership[$key] ?? []) !== ($v2Membership[$key] ?? [])) {
                $membershipMismatches[] = $key;
            }

            $v2Overlay = $v2Overlays->get($v2ByKey[$key]->id);
            if ($v2Overlay === null || ($legacyOverlays[$v2ByKey[$key]->id] ?? []) !== $this->overlay($v2Overlay)) {
                $transactionOverlayMismatches[] = $key;
            }

            if (($legacyItems[$v2ByKey[$key]->id] ?? []) !== ($v2Items[$key] ?? [])) {
                $itemOverlayMismatches[] = $key;
            }
        }

        $conflicts = [
            'transaction' => $this->conflictCount($db, 'spj_transaction_overlays'),
            'item' => $this->conflictCount($db, 'spj_item_overlays'),
        ];
        $status = $missingInV2 === [] && $missingInFresh === []
            && $membershipMismatches === [] && $transactionOverlayMismatches === []
            && $itemOverlayMismatches === [] && max($conflicts) === 0
            ? 'PASS' : 'FAIL';

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

    /** @param array<string, object> $rows @return array<string, array<int, string>> */
    private function freshMembership(Connection $db, array $rows): array
    {
        $keys = $this->keyById($rows);
        $result = [];
        if ($keys === []) {
            return $result;
        }

        foreach ($db->table('spj_fresh_transaction_items')
            ->whereIn('spj_fresh_transaction_id', array_keys($keys))->get() as $item) {
            $result[$keys[(int) $item->spj_fresh_transaction_id]][] = trim((string) $item->source_key);
        }

        return $this->sortMembership($result);
    }

    /** @param array<string, object> $rows @return array<string, array<int, string>> */
    private function v2Membership(Connection $db, array $rows): array
    {
        $keys = $this->keyById($rows);
        $result = [];
        if ($keys === []) {
            return $result;
        }

        foreach ($db->table('spj_transaction_sources as link')
            ->join('arkas_source_identity_registry as identity', 'identity.id', '=', 'link.arkas_source_identity_id')
            ->whereIn('link.spj_transaction_id', array_keys($keys))
            ->get(['link.spj_transaction_id', 'identity.source_key']) as $item) {
            $result[$keys[(int) $item->spj_transaction_id]][] = trim((string) $item->source_key);
        }

        return $this->sortMembership($result);
    }

    /** @return array<int, array<string, string|null>> */
    private function legacyOverlays(Connection $db): array
    {
        $result = [];
        foreach ($db->table('legacy_transaction_v2_map as maps')
            ->join('transactions', 'transactions.id', '=', 'maps.legacy_transaction_id')
            ->get([
                'maps.spj_transaction_id',
                'transactions.spj_category',
                'transactions.payment_description',
                'transactions.payment_method',
                'transactions.payment_reference',
                'transactions.receipt_recipient_name',
            ]) as $row) {
            $transactionId = (int) $row->spj_transaction_id;
            $overlay = $this->overlay($row);
            foreach ($overlay as $column => $value) {
                if (! isset($result[$transactionId][$column]) || $result[$transactionId][$column] === null) {
                    $result[$transactionId][$column] = $value;
                }
            }
        }

        return $result;
    }

    /** @return array<int, array<string, array<string, string|null>>> */
    private function legacyItemOverlays(Connection $db): array
    {
        $result = [];
        foreach ($db->table('legacy_transaction_v2_map as maps')
            ->join('transaction_items', 'transaction_items.transaction_id', '=', 'maps.legacy_transaction_id')
            ->get(['maps.spj_transaction_id', 'transaction_items.source_item_id', 'transaction_items.item_description']) as $item) {
            $transactionId = (int) $item->spj_transaction_id;
            $sourceKey = trim((string) $item->source_item_id);
            $description = $this->normalize($item->item_description);
            if (! isset($result[$transactionId][$sourceKey]) || $result[$transactionId][$sourceKey] === null) {
                $result[$transactionId][$sourceKey] = $description;
            }
        }

        return $this->sortOverlays($result);
    }

    /** @param array<string, object> $rows @return array<string, array<string, string|null>> */
    private function v2ItemOverlays(Connection $db, array $rows): array
    {
        $keys = $this->keyById($rows);
        $result = [];
        if ($keys === []) {
            return $result;
        }

        foreach ($db->table('spj_transaction_sources as link')
            ->join('arkas_source_identity_registry as identity', 'identity.id', '=', 'link.arkas_source_identity_id')
            ->leftJoin('spj_item_overlays as overlay', 'overlay.spj_transaction_source_id', '=', 'link.id')
            ->whereIn('link.spj_transaction_id', array_keys($keys))
            ->get(['link.spj_transaction_id', 'identity.source_key', 'overlay.item_description']) as $item) {
            $result[$keys[(int) $item->spj_transaction_id]][trim((string) $item->source_key)] = $this->normalize($item->item_description);
        }

        return $this->sortOverlays($result);
    }

    /** @param array<string, object> $rows @return array<int, string> */
    private function keyById(array $rows): array
    {
        $result = [];
        foreach ($rows as $key => $row) {
            $result[(int) $row->id] = $key;
        }

        return $result;
    }

    /** @param array<string, array<int, string>> $membership @return array<string, array<int, string>> */
    private function sortMembership(array $membership): array
    {
        foreach ($membership as &$values) {
            $values = array_values(array_unique(array_filter($values)));
            sort($values);
        }
        unset($values);

        return $membership;
    }

    /** @param array<string, array<string, string|null>> $overlays @return array<string, array<string, string|null>> */
    private function sortOverlays(array $overlays): array
    {
        foreach ($overlays as &$values) {
            ksort($values);
        }
        unset($values);

        return $overlays;
    }

    /** @return array<string, string|null> */
    private function overlay(object $row): array
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
        $count = 0;
        foreach ($db->table($table)->whereNotNull('operator_metadata')->pluck('operator_metadata') as $metadata) {
            $decoded = json_decode((string) $metadata, true);
            if (is_array($decoded) && ! empty($decoded['_v2_reconciliation_conflicts'])) {
                $count++;
            }
        }

        return $count;
    }

    private function key(object $row, string $membershipColumn): string
    {
        return implode(':', [
            (string) $row->fiscal_year_id,
            (string) $row->fund_source_id,
            (string) $row->source_id,
            trim((string) $row->{$membershipColumn}),
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
