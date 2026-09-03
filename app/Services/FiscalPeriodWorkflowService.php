<?php

namespace App\Services;

use App\Models\FiscalPeriodClosure;
use App\Models\SpjPackage;
use App\Models\Transaction;

class FiscalPeriodWorkflowService
{
    public function period(int $fiscalYearId, int $quarter): FiscalPeriodClosure
    {
        return FiscalPeriodClosure::query()->firstOrCreate(
            ['fiscal_year_id' => $fiscalYearId, 'quarter' => $quarter],
            ['status' => 'OPEN'],
        );
    }

    public function markNumbered(FiscalPeriodClosure $period, int $userId): FiscalPeriodClosure
    {
        if ($period->status === 'CLOSED') {
            throw new \RuntimeException('Triwulan sudah ditutup. Buka kembali sebelum menjalankan penomoran.');
        }

        return tap($period)->update(['status' => 'NUMBERED', 'numbered_at' => now(), 'numbered_by' => $userId]);
    }

    public function close(FiscalPeriodClosure $period, int $fundSourceId, int $userId): FiscalPeriodClosure
    {
        if ($period->status !== 'NUMBERED') {
            throw new \RuntimeException('Triwulan hanya dapat ditutup setelah proses penomoran selesai.');
        }

        $transactions = $this->transactions($period, $fundSourceId);
        if ((clone $transactions)->where(fn ($query) => $query->where('requires_reconciliation', true)->orWhere('source_status', 'SOURCE_MISSING'))->exists()) {
            throw new \RuntimeException('Masih ada transaksi yang harus direkonsiliasi atau hilang dari sumber ARKAS.');
        }
        $unfinished = SpjPackage::query()->whereHas('transaction', fn ($query) => $this->applyPeriod($query, $period, $fundSourceId))
            ->whereNotIn('status', ['NUMBERED', 'FINAL'])->count();
        if ($unfinished > 0) {
            throw new \RuntimeException("Masih ada {$unfinished} paket yang belum bernomor atau final.");
        }

        return tap($period)->update(['status' => 'CLOSED', 'closed_at' => now(), 'closed_by' => $userId]);
    }

    public function reopen(FiscalPeriodClosure $period, int $userId, string $reason): FiscalPeriodClosure
    {
        if ($period->status !== 'CLOSED') {
            throw new \RuntimeException('Hanya triwulan CLOSED yang dapat dibuka kembali.');
        }
        if (blank($reason)) {
            throw new \InvalidArgumentException('Alasan pembukaan triwulan wajib diisi.');
        }

        return tap($period)->update(['status' => 'NUMBERED', 'reopened_at' => now(), 'reopened_by' => $userId, 'reopen_reason' => trim($reason)]);
    }

    public function isLateEntry(int $fiscalYearId, int $quarter): bool
    {
        return in_array($this->period($fiscalYearId, $quarter)->status, ['NUMBERED', 'CLOSED'], true);
    }

    private function transactions(FiscalPeriodClosure $period, int $fundSourceId)
    {
        return Transaction::query()->where(fn ($query) => $this->applyPeriod($query, $period, $fundSourceId));
    }

    private function applyPeriod($query, FiscalPeriodClosure $period, int $fundSourceId): void
    {
        $query->where('fiscal_year_id', $period->fiscal_year_id)
            ->where('fund_source_id', $fundSourceId)
            ->whereMonth('transaction_date', '>=', ($period->quarter - 1) * 3 + 1)
            ->whereMonth('transaction_date', '<=', $period->quarter * 3);
    }
}
