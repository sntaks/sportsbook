<?php

namespace App\Wallet;

use App\Enums\MpesaDepositStatus;
use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalStatus;
use App\Models\MpesaDeposit;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

/**
 * Daily M-Pesa totals beside the ledger's own. Deposits must match exactly: a difference means money was credited
 * without a completed M-Pesa payment, or the other way round. Payouts differ by whatever was still in flight.
 * Days are Nairobi calendar days. This compares our own records; matching them to Safaricom's statement is still a
 * job for finance in the M-Pesa portal.
 */
class Reconciliation
{
    /**
     * @return list<array{date: string, mpesa_deposits: int, ledger_deposits: int, mpesa_payouts: int, ledger_payouts: int}>
     */
    public function days(int $days = 14): array
    {
        $timezone = (string) config('sportsbook.timezone');
        $rows = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $day = Date::now()->setTimezone($timezone)->subDays($offset)->startOfDay();
            [$from, $to] = [$day->setTimezone('UTC'), $day->addDay()->setTimezone('UTC')];

            $rows[] = [
                'date' => $day->format('D j M'),
                'mpesa_deposits' => $this->completedDeposits($from, $to),
                'ledger_deposits' => $this->ledgerTotal(WalletTransactionType::Deposit, $from, $to),
                'mpesa_payouts' => (int) Withdrawal::query()->where('status', WithdrawalStatus::Completed)->whereBetween('completed_at', [$from, $to])->sum('amount'),
                'ledger_payouts' => $this->ledgerPayouts($from, $to),
            ];
        }

        return $rows;
    }

    /**
     * Things a person needs to look at, by kind.
     *
     * @return array{unmatched: int, mismatch: int, awaiting_approval: int, stuck_payouts: int}
     */
    public function needsAttention(): array
    {
        return [
            'unmatched' => MpesaDeposit::query()->where('status', MpesaDepositStatus::Unmatched)->count(),
            'mismatch' => MpesaDeposit::query()->where('status', MpesaDepositStatus::Mismatch)->count(),
            'awaiting_approval' => Withdrawal::query()->where('status', WithdrawalStatus::AwaitingApproval)->count(),
            'stuck_payouts' => Withdrawal::query()
                ->where('status', WithdrawalStatus::Processing)
                ->where('sent_at', '<=', now()->subMinutes((int) config('sportsbook.wallet.payout_stuck_after_minutes')))
                ->count(),
        ];
    }

    private function completedDeposits(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) MpesaDeposit::query()
            ->where('status', MpesaDepositStatus::Completed)
            ->whereBetween('completed_at', [$from, $to])
            ->selectRaw('coalesce(sum(coalesce(paid_amount, amount)), 0) as total')
            ->value('total');
    }

    private function ledgerTotal(WalletTransactionType $type, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) WalletTransaction::query()->where('type', $type)->whereBetween('created_at', [$from, $to])->sum('amount');
    }

    /**
     * Money taken out of wallets for withdrawals that day, less any returned: withdrawal holds minus refunds. It
     * differs from what M-Pesa paid that day by payouts still in flight or settled on another day.
     */
    private function ledgerPayouts(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return -(int) WalletTransaction::query()
            ->whereIn('type', [WalletTransactionType::Withdrawal, WalletTransactionType::WithdrawalRefund])
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');
    }
}
