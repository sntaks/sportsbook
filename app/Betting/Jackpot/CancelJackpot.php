<?php

namespace App\Betting\Jackpot;

use App\Enums\JackpotEntryStatus;
use App\Enums\JackpotStatus;
use App\Enums\WalletTransactionType;
use App\Models\Admin;
use App\Models\Jackpot;
use App\Wallet\Ledger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Calls a jackpot off and gives every entry its price back: by an admin, or by settlement when more matches were
 * void than the jackpot allows. A settled jackpot is corrected through results instead, never cancelled, and a
 * cancelled one is final.
 */
class CancelJackpot
{
    public function __construct(private Ledger $ledger) {}

    /**
     * @throws ValidationException
     */
    public function handle(Jackpot $jackpot, string $reason, ?Admin $admin = null): Jackpot
    {
        return DB::transaction(function () use ($jackpot, $reason, $admin): Jackpot {
            $jackpot = Jackpot::query()->whereKey($jackpot->id)->lockForUpdate()->firstOrFail();

            if (! in_array($jackpot->status, [JackpotStatus::Draft, JackpotStatus::Open], true)) {
                throw ValidationException::withMessages(['jackpot' => __('Only a draft or open jackpot can be cancelled.')]);
            }

            $refunded = 0;

            foreach ($jackpot->entries()->where('status', JackpotEntryStatus::Open)->lockForUpdate()->get() as $entry) {
                $refund = $this->ledger->post($entry->user, WalletTransactionType::JackpotRefund, $entry->stake, "jackpot-refund:{$entry->id}", source: $entry, description: __(':jackpot cancelled', ['jackpot' => $jackpot->name]));
                $entry->update(['status' => JackpotEntryStatus::Refunded, 'prize' => $entry->stake, 'prize_transaction_id' => $refund->id, 'settled_at' => now()]);
                $refunded++;
            }

            $jackpot->update(['status' => JackpotStatus::Cancelled, 'cancel_reason' => mb_substr($reason, 0, 255), 'settled_at' => now()]);

            activity('settlement')
                ->causedBy($admin)
                ->performedOn($jackpot)
                ->event('jackpot-cancelled')
                ->withProperties(['jackpot' => $jackpot->name, 'reason' => $reason, 'entries_refunded' => $refunded])
                ->log('Jackpot cancelled');

            return $jackpot;
        });
    }
}
