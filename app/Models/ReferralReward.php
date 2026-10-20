<?php

namespace App\Models;

use App\Enums\ReferralRewardStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ReferralRewardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What was decided once an invited player qualified. created_at is when it was decided, which is the month the
 * monthly limit counts it in.
 *
 * @property int $id
 * @property int $referrer_id
 * @property int $referred_id
 * @property ReferralRewardStatus $status
 * @property int|null $free_bet_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['referrer_id', 'referred_id', 'status', 'free_bet_id'])]
class ReferralReward extends Model
{
    /** @use HasFactory<ReferralRewardFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReferralRewardStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_id');
    }

    /**
     * @return BelongsTo<FreeBet, $this>
     */
    public function freeBet(): BelongsTo
    {
        return $this->belongsTo(FreeBet::class);
    }
}
