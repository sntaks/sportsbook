<?php

namespace App\Models;

use App\Enums\WithdrawalStatus;
use Carbon\CarbonImmutable;
use Database\Factories\WithdrawalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payout to the player's registered M-Pesa number. The amount leaves the wallet when the player asks (the hold
 * entry) and only comes back (the refund entry) when M-Pesa or an admin says definitively that it was not paid.
 *
 * @property int $id
 * @property int $user_id
 * @property WithdrawalStatus $status
 * @property string $phone
 * @property int $amount Cents.
 * @property int|null $hold_transaction_id
 * @property int|null $refund_transaction_id
 * @property string|null $conversation_id
 * @property string|null $originator_conversation_id
 * @property string|null $status_query_conversation_id
 * @property string|null $mpesa_receipt
 * @property string|null $result_code
 * @property string|null $result_description
 * @property array<string, mixed>|null $result_payload
 * @property int|null $reviewed_by_admin_id
 * @property CarbonImmutable|null $reviewed_at
 * @property string|null $review_note
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['user_id', 'status', 'phone', 'amount', 'hold_transaction_id', 'refund_transaction_id', 'conversation_id', 'originator_conversation_id', 'status_query_conversation_id', 'mpesa_receipt', 'result_code', 'result_description', 'result_payload', 'reviewed_by_admin_id', 'reviewed_at', 'review_note', 'sent_at', 'completed_at'])]
class Withdrawal extends Model
{
    /** @use HasFactory<WithdrawalFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WithdrawalStatus::class,
            'amount' => 'integer',
            'result_payload' => 'array',
            'reviewed_at' => 'datetime',
            'sent_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }

    /**
     * Sent (or possibly sent) to M-Pesa for longer than the given minutes without a definite answer.
     */
    public function isStuck(int $minutes = 30): bool
    {
        $since = $this->sent_at ?? $this->updated_at;

        return $this->status === WithdrawalStatus::Processing && $since !== null && $since->lte(now()->subMinutes($minutes));
    }
}
