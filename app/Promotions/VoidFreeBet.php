<?php

namespace App\Promotions;

use App\Enums\FreeBetStatus;
use App\Models\Admin;
use App\Models\FreeBet;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Staff take back a free bet the player has not used, for example one given by mistake.
 */
class VoidFreeBet
{
    /**
     * @throws ValidationException
     */
    public function handle(FreeBet $freeBet, Admin $admin, string $reason): FreeBet
    {
        $freeBet = DB::transaction(function () use ($freeBet, $reason): FreeBet {
            $freeBet = FreeBet::query()->whereKey($freeBet->id)->lockForUpdate()->firstOrFail();

            if ($freeBet->status !== FreeBetStatus::Available) {
                throw ValidationException::withMessages(['free_bet' => __('Only a free bet that has not been used can be voided.')]);
            }

            $freeBet->update(['status' => FreeBetStatus::Void, 'void_reason' => mb_substr($reason, 0, 255), 'voided_at' => now()]);

            return $freeBet;
        });

        activity('promotions')
            ->causedBy($admin)
            ->performedOn($freeBet->user)
            ->event('free-bet-voided')
            ->withProperties(['free_bet_id' => $freeBet->id, 'amount' => Money::format($freeBet->amount), 'reason' => $reason])
            ->log('Free bet voided');

        return $freeBet;
    }
}
