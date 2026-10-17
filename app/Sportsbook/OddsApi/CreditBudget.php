<?php

namespace App\Sportsbook\OddsApi;

use App\Models\OddsApiCall;

/**
 * Keeps paid Odds API usage inside the monthly cap and above the reserve, using the call ledger.
 */
class CreditBudget
{
    public function __construct(
        private readonly int $monthlyCap,
        private readonly int $reserve,
    ) {}

    /**
     * @throws CreditBudgetExceeded
     */
    public function ensureCanSpend(int $estimatedCost): void
    {
        $spentThisMonth = $this->spentThisMonth();

        if ($spentThisMonth + $estimatedCost > $this->monthlyCap) {
            throw CreditBudgetExceeded::monthlyCap($estimatedCost, $spentThisMonth, $this->monthlyCap);
        }

        $remaining = $this->remaining();

        if ($remaining !== null && $remaining - $estimatedCost < $this->reserve) {
            throw CreditBudgetExceeded::reserve($estimatedCost, $remaining, $this->reserve);
        }
    }

    public function spentThisMonth(): int
    {
        return (int) OddsApiCall::query()
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('credits_spent');
    }

    /**
     * Credits left on the plan as of the most recent call that reported them, or null when none has.
     */
    public function remaining(): ?int
    {
        return OddsApiCall::query()
            ->whereNotNull('credits_remaining')
            ->latest('created_at')
            ->latest('id')
            ->value('credits_remaining');
    }
}
