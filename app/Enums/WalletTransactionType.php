<?php

namespace App\Enums;

enum WalletTransactionType: string
{
    case Deposit = 'deposit';
    case DepositTax = 'deposit-tax';
    case Adjustment = 'adjustment';
    case Withdrawal = 'withdrawal';
    case WithdrawalRefund = 'withdrawal-refund';
    case Stake = 'stake';
    case Winnings = 'winnings';
    case StakeRefund = 'stake-refund';
    case WinningsTax = 'winnings-tax';
    /** A corrected result: the earlier payout is taken back before the bet settles again. */
    case SettlementReversal = 'settlement-reversal';

    /**
     * Name shown to the player in their transaction history.
     */
    public function label(): string
    {
        return match ($this) {
            self::Deposit => __('Deposit'),
            self::DepositTax => __('Tax on deposit'),
            self::Adjustment => __('Adjustment'),
            self::Withdrawal => __('Withdrawal'),
            self::WithdrawalRefund => __('Withdrawal returned'),
            self::Stake => __('Bet placed'),
            self::Winnings => __('Winnings'),
            self::StakeRefund => __('Stake returned'),
            self::WinningsTax => __('Tax on winnings'),
            self::SettlementReversal => __('Result corrected'),
        };
    }
}
