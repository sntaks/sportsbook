<?php

namespace App\Filament\Pages\Reconciliation;

use App\Filament\Resources\Deposits\DepositResource;
use App\Filament\Resources\Withdrawals\WithdrawalResource;
use App\Wallet\Reconciliation;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Counts of the payments a person has to look at, each linking to where it is settled.
 */
class NeedsAttention extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Needs attention';

    protected function getStats(): array
    {
        $counts = app(Reconciliation::class)->needsAttention();

        return [
            $this->stat(__('Unmatched deposits'), $counts['unmatched'], DepositResource::getUrl()),
            $this->stat(__('Mismatched deposits'), $counts['mismatch'], DepositResource::getUrl()),
            $this->stat(__('Withdrawals awaiting approval'), $counts['awaiting_approval'], WithdrawalResource::getUrl()),
            $this->stat(__('Payouts with no answer from M-Pesa'), $counts['stuck_payouts'], WithdrawalResource::getUrl()),
        ];
    }

    private function stat(string $label, int $count, string $url): Stat
    {
        return Stat::make($label, (string) $count)
            ->color($count > 0 ? 'danger' : 'success')
            ->description($count > 0 ? __('Open the list to settle them') : __('Nothing waiting'))
            ->url($url);
    }
}
