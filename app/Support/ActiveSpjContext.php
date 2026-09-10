<?php

namespace App\Support;

use App\Models\School;
use App\Models\Transaction;

final class ActiveSpjContext
{
    public function __construct(
        private readonly ?int $schoolIdOverride = null,
        private readonly ?int $fiscalYearIdOverride = null,
        private readonly ?int $fundSourceIdOverride = null,
        private readonly ?int $actorIdOverride = null,
        private readonly ?bool $administratorOverride = null,
    ) {}

    public function schoolId(): int
    {
        return $this->schoolIdOverride ?? (int) session('active_school_id');
    }

    public function fiscalYearId(): int
    {
        return $this->fiscalYearIdOverride ?? (int) session('active_fiscal_year_id');
    }

    public function fundSourceId(): ?int
    {
        if ($this->fundSourceIdOverride !== null) {
            return $this->fundSourceIdOverride;
        }

        $fundSourceId = session('active_fund_source_id');

        return $fundSourceId === null ? null : (int) $fundSourceId;
    }

    public function actorId(): int
    {
        return $this->actorIdOverride ?? (int) auth()->id();
    }

    public function isAdministrator(): bool
    {
        return $this->administratorOverride ?? (auth()->user()?->isAdministrator() === true);
    }

    public function school(): School
    {
        return School::query()->findOrFail($this->schoolId());
    }

    public function matchesTransaction(Transaction $transaction): bool
    {
        $transactionFundSourceId = $transaction->fund_source_id === null
            ? null
            : (int) $transaction->fund_source_id;

        return (int) $transaction->fiscal_year_id === $this->fiscalYearId()
            && $transactionFundSourceId === $this->fundSourceId();
    }
}
