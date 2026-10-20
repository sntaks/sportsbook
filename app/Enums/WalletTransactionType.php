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
    /** Paid with a winning multi's winnings, as its own entry (decision 42). */
    case MultibetBonus = 'multibet-bonus';
    /** A corrected result: the earlier payout is taken back before the bet settles again. */
    case SettlementReversal = 'settlement-reversal';
    case JackpotEntry = 'jackpot-entry';
    case JackpotPrize = 'jackpot-prize';
    /** The jackpot was cancelled: the entry price goes back. */
    case JackpotRefund = 'jackpot-refund';

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
            self::MultibetBonus => __('Multibet bonus'),
            self::SettlementReversal => __('Result corrected'),
            self::JackpotEntry => __('Jackpot entry'),
            self::JackpotPrize => __('Jackpot prize'),
            self::JackpotRefund => __('Jackpot entry returned'),
        };
    }
}
