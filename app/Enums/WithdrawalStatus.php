<?php

namespace App\Enums;

enum WithdrawalStatus: string
{
    /** At or above the approval threshold; waiting for finance. The money is held. */
    case AwaitingApproval = 'awaiting-approval';
    /** Waiting for the payout job. */
    case Queued = 'queued';
    /** Sent to M-Pesa, or possibly sent: never refunded without a definite answer. */
    case Processing = 'processing';
    case Completed = 'completed';
    /** M-Pesa refused the payout; the money is back in the wallet. */
    case Failed = 'failed';
    /** Turned down by an admin; the money is back in the wallet. */
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingApproval => __('Awaiting approval'),
            self::Queued => __('Queued'),
            self::Processing => __('Sending to M-Pesa'),
            self::Completed => __('Paid'),
            self::Failed => __('Failed, money returned'),
            self::Rejected => __('Rejected, money returned'),
        };
    }

    /**
     * Whether the outcome is final.
     */
    public function isSettled(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Rejected], true);
    }
}
