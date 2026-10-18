<?php

namespace App\Wallet\Withdrawals;

use App\Enums\WithdrawalStatus;
use App\Jobs\Wallet\SendWithdrawalPayout;
use App\Models\Admin;
use App\Models\Withdrawal;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Finance's decisions on withdrawals: approve or reject one awaiting approval, and settle by hand a payout M-Pesa
 * never gave a definite answer for. Every decision is in the activity log with who made it.
 */
class ReviewWithdrawal
{
    public function __construct(private SettleWithdrawal $settleWithdrawal) {}

    /**
     * @throws ValidationException
     */
    public function approve(Withdrawal $withdrawal, Admin $admin): Withdrawal
    {
        $approved = DB::transaction(function () use ($withdrawal, $admin): bool {
            $locked = Withdrawal::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== WithdrawalStatus::AwaitingApproval) {
                return false;
            }

            $locked->update([
                'status' => WithdrawalStatus::Queued,
                'reviewed_by_admin_id' => $admin->id,
                'reviewed_at' => now(),
            ]);

            return true;
        });

        if (! $approved) {
            throw ValidationException::withMessages(['withdrawal' => __('This withdrawal is no longer waiting for approval.')]);
        }

        $withdrawal->refresh();
        SendWithdrawalPayout::dispatch($withdrawal);
        $this->log($withdrawal, $admin, 'withdrawal-approved', 'Withdrawal approved');

        return $withdrawal;
    }

    /**
     * @throws ValidationException
     */
    public function reject(Withdrawal $withdrawal, Admin $admin, string $reason): Withdrawal
    {
        $this->ensureStatus($withdrawal, WithdrawalStatus::AwaitingApproval);

        $withdrawal = $this->settleWithdrawal->returnFunds($withdrawal, WithdrawalStatus::Rejected, $this->review($admin, $reason));
        $this->log($withdrawal, $admin, 'withdrawal-rejected', 'Withdrawal rejected', ['reason' => $reason]);

        return $withdrawal;
    }

    /**
     * Finance confirmed in the M-Pesa portal that the payout went through.
     *
     * @throws ValidationException
     */
    public function markPaid(Withdrawal $withdrawal, Admin $admin, string $receipt, string $note): Withdrawal
    {
        $this->ensureStatus($withdrawal, WithdrawalStatus::Processing);

        $withdrawal = $this->settleWithdrawal->markPaid($withdrawal, $receipt, $this->review($admin, $note));
        $this->log($withdrawal, $admin, 'withdrawal-marked-paid', 'Withdrawal marked paid by hand', ['receipt' => $receipt, 'note' => $note]);

        return $withdrawal;
    }

    /**
     * Finance confirmed in the M-Pesa portal that the payout did not go through: the money goes back to the wallet.
     *
     * @throws ValidationException
     */
    public function markFailed(Withdrawal $withdrawal, Admin $admin, string $note): Withdrawal
    {
        $this->ensureStatus($withdrawal, WithdrawalStatus::Processing);

        $withdrawal = $this->settleWithdrawal->returnFunds($withdrawal, WithdrawalStatus::Failed, $this->review($admin, $note));
        $this->log($withdrawal, $admin, 'withdrawal-marked-failed', 'Withdrawal marked failed by hand and money returned', ['note' => $note]);

        return $withdrawal;
    }

    /**
     * @throws ValidationException
     */
    private function ensureStatus(Withdrawal $withdrawal, WithdrawalStatus $status): void
    {
        if ($withdrawal->fresh()?->status !== $status) {
            throw ValidationException::withMessages(['withdrawal' => __('This withdrawal has changed since you opened it. Refresh and try again.')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function review(Admin $admin, string $note): array
    {
        return ['reviewed_by_admin_id' => $admin->id, 'reviewed_at' => now(), 'review_note' => mb_substr($note, 0, 255)];
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function log(Withdrawal $withdrawal, Admin $admin, string $event, string $description, array $properties = []): void
    {
        activity('payments')
            ->causedBy($admin)
            ->performedOn($withdrawal)
            ->event($event)
            ->withProperties(['amount' => Money::format($withdrawal->amount), 'player_id' => $withdrawal->user_id, ...$properties])
            ->log($description);
    }
}
