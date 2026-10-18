<?php

namespace App\Models;

use App\Enums\WalletTransactionType;
use Carbon\CarbonImmutable;
use Database\Factories\WalletTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * One line of the ledger. Append-only: a mistake is corrected with a new, opposite entry, never by editing or
 * deleting one, so the history always adds up to the balance.
 *
 * @property int $id
 * @property int $wallet_id
 * @property WalletTransactionType $type
 * @property int $amount Cents; positive is money in, negative is money out.
 * @property int $balance_after Cents.
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string $idempotency_key
 * @property string|null $description
 * @property int|null $admin_id
 * @property CarbonImmutable|null $created_at
 */
class WalletTransaction extends Model
{
    /** @use HasFactory<WalletTransactionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Wallet transactions are append-only and cannot be changed.'));
        static::deleting(fn (): never => throw new LogicException('Wallet transactions are append-only and cannot be deleted.'));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => WalletTransactionType::class,
            'amount' => 'integer',
            'balance_after' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * The record that caused this entry, such as an M-Pesa deposit.
     *
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
