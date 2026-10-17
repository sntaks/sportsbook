<?php

namespace App\Models;

use App\Enums\MarketStatus;
use App\Enums\MarketType;
use App\Enums\SuspensionReason;
use Carbon\CarbonImmutable;
use Database\Factories\MarketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One bettable market on a fixture, e.g. 1X2 or Over/Under 2.5. The key is unique per fixture.
 *
 * @property int $id
 * @property int $fixture_id
 * @property MarketType $type
 * @property string|null $line
 * @property string $key
 * @property MarketStatus $status
 * @property SuspensionReason|null $suspension_reason
 * @property int $bookmaker_count
 * @property CarbonImmutable|null $priced_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['fixture_id', 'type', 'line', 'key', 'status', 'suspension_reason', 'bookmaker_count', 'priced_at'])]
class Market extends Model
{
    /** @use HasFactory<MarketFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MarketType::class,
            'line' => 'decimal:2',
            'status' => MarketStatus::class,
            'suspension_reason' => SuspensionReason::class,
            'priced_at' => 'datetime',
        ];
    }

    /**
     * The unique key for a market of this type and line, e.g. "h2h" or "totals:2.5".
     */
    public static function keyFor(MarketType $type, ?float $line = null): string
    {
        return $line === null ? $type->value : $type->value.':'.rtrim(rtrim(number_format($line, 2, '.', ''), '0'), '.');
    }

    /**
     * @return BelongsTo<Fixture, $this>
     */
    public function fixture(): BelongsTo
    {
        return $this->belongsTo(Fixture::class);
    }

    /**
     * Selections in the order players read them: 1, X, 2 and Over before Under. Without an explicit order the
     * database returns them in index order (away, draw, home), which reverses the 1X2.
     *
     * @return HasMany<Selection, $this>
     */
    public function selections(): HasMany
    {
        return $this->hasMany(Selection::class)
            ->orderByRaw("case outcome when 'home' then 1 when 'draw' then 2 when 'away' then 3 when 'over' then 4 when 'under' then 5 else 6 end")
            ->orderBy('id');
    }

    public function name(): string
    {
        return $this->line === null ? $this->type->label() : $this->type->label().' '.rtrim(rtrim($this->line, '0'), '.');
    }

    public function isOpen(): bool
    {
        return $this->status === MarketStatus::Open;
    }

    public function suspend(SuspensionReason $reason): void
    {
        $this->update(['status' => MarketStatus::Suspended, 'suspension_reason' => $reason]);
    }

    public function reopen(): void
    {
        $this->update(['status' => MarketStatus::Open, 'suspension_reason' => null]);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where('status', MarketStatus::Open);
    }
}
