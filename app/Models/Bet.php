<?php

namespace App\Models;

use App\Enums\BetStatus;
use App\Enums\BetType;
use App\Enums\OddsChangePolicy;
use Carbon\CarbonImmutable;
use Database\Factories\BetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A placed bet. Money is in cents. The stake left the wallet when it was placed (stake_transaction_id); winnings or a
 * refund arrive when it settles (payout_transaction_id).
 *
 * @property int $id
 * @property int $user_id
 * @property string $reference
 * @property BetType $type
 * @property BetStatus $status
 * @property int $stake
 * @property string $total_odds
 * @property int $potential_payout Capped at the maximum payout.
 * @property int|null $payout
 * @property OddsChangePolicy $odds_change_policy
 * @property string|null $client_key
 * @property int|null $stake_transaction_id
 * @property int|null $payout_transaction_id
 * @property CarbonImmutable $placed_at
 * @property CarbonImmutable|null $settled_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['user_id', 'reference', 'type', 'status', 'stake', 'total_odds', 'potential_payout', 'payout', 'odds_change_policy', 'client_key', 'stake_transaction_id', 'payout_transaction_id', 'placed_at', 'settled_at'])]
class Bet extends Model
{
    /** @use HasFactory<BetFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BetType::class,
            'status' => BetStatus::class,
            'stake' => 'integer',
            'total_odds' => 'decimal:2',
            'potential_payout' => 'integer',
            'payout' => 'integer',
            'odds_change_policy' => OddsChangePolicy::class,
            'placed_at' => 'datetime',
            'settled_at' => 'datetime',
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
     * Legs in the order they were added to the slip.
     *
     * @return HasMany<BetLeg, $this>
     */
    public function legs(): HasMany
    {
        return $this->hasMany(BetLeg::class)->orderBy('id');
    }
}
