<?php

namespace App\Models;

use App\Enums\DepositLimitPeriod;
use Carbon\CarbonImmutable;
use Database\Factories\DepositLimitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A player's own deposit limit for one rolling period. Cuts apply at once; a raise or removal is stored as a pending
 * change that takes over at pending_from (24 hours later), so a limit cannot be lifted in the heat of the moment.
 *
 * @property int $id
 * @property int $user_id
 * @property DepositLimitPeriod $period
 * @property int|null $amount Cents; null means no limit.
 * @property int|null $pending_amount Cents; null with has_pending_change means the limit is being removed.
 * @property bool $has_pending_change
 * @property CarbonImmutable|null $pending_from
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['user_id', 'period', 'amount', 'pending_amount', 'has_pending_change', 'pending_from'])]
class DepositLimit extends Model
{
    /** @use HasFactory<DepositLimitFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period' => DepositLimitPeriod::class,
            'amount' => 'integer',
            'pending_amount' => 'integer',
            'has_pending_change' => 'boolean',
            'pending_from' => 'datetime',
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
     * The limit in force right now, in cents, or null for none. A pending change counts once its time has come.
     */
    public function effectiveAmount(): ?int
    {
        return $this->pendingChangeIsDue() ? $this->pending_amount : $this->amount;
    }

    /**
     * Whether a raise or removal is still waiting for its 24 hours.
     */
    public function hasWaitingChange(): bool
    {
        return $this->has_pending_change && ! $this->pendingChangeIsDue();
    }

    private function pendingChangeIsDue(): bool
    {
        return $this->has_pending_change && $this->pending_from !== null && $this->pending_from->lte(now());
    }
}
