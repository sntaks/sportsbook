<?php

namespace App\Models;

use App\Enums\MpesaDepositChannel;
use App\Enums\MpesaDepositStatus;
use Carbon\CarbonImmutable;
use Database\Factories\MpesaDepositFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An M-Pesa payment in: an STK push we started, or a Paybill payment the player made themselves.
 *
 * @property int $id
 * @property int|null $user_id
 * @property MpesaDepositChannel $channel
 * @property MpesaDepositStatus $status
 * @property string $phone Masked by Safaricom on Paybill payments.
 * @property int $amount Cents.
 * @property string|null $account_reference
 * @property string|null $merchant_request_id
 * @property string|null $checkout_request_id
 * @property string|null $mpesa_receipt
 * @property string|null $result_code
 * @property string|null $result_description
 * @property array<string, mixed>|null $callback_payload
 * @property int|null $wallet_transaction_id
 * @property int $query_attempts
 * @property CarbonImmutable|null $last_queried_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['user_id', 'channel', 'status', 'phone', 'amount', 'account_reference', 'merchant_request_id', 'checkout_request_id', 'mpesa_receipt', 'result_code', 'result_description', 'callback_payload', 'wallet_transaction_id', 'query_attempts', 'last_queried_at', 'completed_at'])]
class MpesaDeposit extends Model
{
    /** @use HasFactory<MpesaDepositFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => MpesaDepositChannel::class,
            'status' => MpesaDepositStatus::class,
            'amount' => 'integer',
            'callback_payload' => 'array',
            'query_attempts' => 'integer',
            'last_queried_at' => 'datetime',
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
     * @return BelongsTo<WalletTransaction, $this>
     */
    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', MpesaDepositStatus::Pending);
    }
}
