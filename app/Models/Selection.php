<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SelectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One outcome of a market. The offered price is the admin override when one is set, otherwise the feed price.
 *
 * @property int $id
 * @property int $market_id
 * @property string $outcome
 * @property string $name
 * @property string|null $fair_probability
 * @property string|null $feed_price
 * @property string|null $override_price
 * @property string|null $override_reason
 * @property int|null $override_by_admin_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['market_id', 'outcome', 'name', 'fair_probability', 'feed_price', 'override_price', 'override_reason', 'override_by_admin_id'])]
class Selection extends Model
{
    /** @use HasFactory<SelectionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fair_probability' => 'decimal:6',
            'feed_price' => 'decimal:2',
            'override_price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Market, $this>
     */
    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function overrideBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'override_by_admin_id');
    }

    /**
     * @return HasMany<OddsSnapshot, $this>
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(OddsSnapshot::class);
    }

    /**
     * The price players are offered.
     */
    public function price(): ?string
    {
        return $this->override_price ?? $this->feed_price;
    }

    public function hasOverride(): bool
    {
        return $this->override_price !== null;
    }
}
