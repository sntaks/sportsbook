<?php

namespace App\Wallet\Deposits;

use App\Enums\MpesaDepositStatus;
use App\Models\Admin;
use App\Models\MpesaDeposit;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Finance settles money M-Pesa received that nobody was credited for: an unmatched payment is assigned to the right
 * player, a mismatched one is credited with what M-Pesa actually received, or either is dismissed with a note
 * (for example after refunding it outside the app). Each is in the activity log.
 */
class ResolveDeposit
{
    public function __construct(private CompleteDeposit $completeDeposit) {}

    /**
     * Credit the payment to a player: the one chosen for an unmatched payment, or the deposit's own player for a
     * mismatched one. The amount credited is what M-Pesa received.
     *
     * @throws ValidationException
     */
    public function credit(MpesaDeposit $deposit, Admin $admin, ?User $player, string $note): MpesaDeposit
    {
        $deposit = DB::transaction(function () use ($deposit, $admin, $player, $note): MpesaDeposit {
            $deposit = $this->lockUnresolved($deposit);
            $player ??= $deposit->user;

            if ($player === null) {
                throw ValidationException::withMessages(['player' => __('Choose the player to credit.')]);
            }

            $deposit->update([
                'user_id' => $player->id,
                'resolved_by_admin_id' => $admin->id,
                'resolved_at' => now(),
                'resolution_note' => mb_substr($note, 0, 255),
            ]);

            return $this->completeDeposit->handle($deposit->fresh() ?? $deposit, $deposit->mpesa_receipt);
        });

        $this->log($deposit, $admin, 'deposit-credited', 'Deposit credited by hand', $note);

        return $deposit;
    }

    /**
     * @throws ValidationException
     */
    public function dismiss(MpesaDeposit $deposit, Admin $admin, string $note): MpesaDeposit
    {
        $deposit = DB::transaction(function () use ($deposit, $admin, $note): MpesaDeposit {
            $deposit = $this->lockUnresolved($deposit);

            $deposit->update([
                'status' => MpesaDepositStatus::Dismissed,
                'resolved_by_admin_id' => $admin->id,
                'resolved_at' => now(),
                'resolution_note' => mb_substr($note, 0, 255),
            ]);

            return $deposit;
        });

        $this->log($deposit, $admin, 'deposit-dismissed', 'Deposit dismissed without crediting', $note);

        return $deposit;
    }

    /**
     * @throws ValidationException
     */
    private function lockUnresolved(MpesaDeposit $deposit): MpesaDeposit
    {
        $deposit = MpesaDeposit::query()->whereKey($deposit->id)->lockForUpdate()->firstOrFail();

        if (! $deposit->needsResolution()) {
            throw ValidationException::withMessages(['deposit' => __('This deposit has already been settled.')]);
        }

        return $deposit;
    }

    private function log(MpesaDeposit $deposit, Admin $admin, string $event, string $description, string $note): void
    {
        activity('payments')
            ->causedBy($admin)
            ->performedOn($deposit)
            ->event($event)
            ->withProperties(['amount' => Money::format($deposit->paid_amount ?? $deposit->amount), 'player_id' => $deposit->user_id, 'note' => $note])
            ->log($description);
    }
}
