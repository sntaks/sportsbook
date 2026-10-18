<?php

namespace App\Enums;

enum MpesaDepositStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    /** M-Pesa reported a different amount or phone than we asked for. Not credited; for admin. */
    case Mismatch = 'mismatch';
    /** A Paybill payment whose account number matched no player. Not credited; for admin. */
    case Unmatched = 'unmatched';

    /**
     * Whether the deposit can still change on its own (a callback or a status check may still arrive).
     */
    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Waiting for M-Pesa'),
            self::Completed => __('Completed'),
            self::Failed => __('Failed'),
            self::Cancelled => __('Cancelled'),
            self::Expired => __('Expired'),
            self::Mismatch => __('Under review'),
            self::Unmatched => __('Unmatched'),
        };
    }
}
