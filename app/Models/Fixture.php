<?php

namespace App\Models;

use App\Enums\FixtureStatus;
use App\Enums\MarketType;
use Carbon\CarbonImmutable;
use Database\Factories\FixtureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A single match. Called "event" by The Odds API; named Fixture here to avoid confusion with Laravel events.
 *
 * @property int $id
 * @property int $competition_id
 * @property string $external_id
 * @property string $home_team
 * @property string $away_team
 * @property CarbonImmutable $kicks_off_at
 * @property FixtureStatus $status
 * @property CarbonImmutable|null $last_seen_in_feed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['competition_id', 'external_id', 'home_team', 'away_team', 'kicks_off_at', 'status', 'last_seen_in_feed_at'])]
class Fixture extends Model
{
    /** @use HasFactory<FixtureFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kicks_off_at' => 'datetime',
            'last_seen_in_feed_at' => 'datetime',
            'status' => FixtureStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Competition, $this>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * @return HasMany<Market, $this>
     */
    public function markets(): HasMany
    {
        return $this->hasMany(Market::class);
    }

    /**
     * @return HasOne<Market, $this>
     */
    public function matchResultMarket(): HasOne
    {
        return $this->hasOne(Market::class)->where('type', MarketType::MatchResult->value);
    }

    public function name(): string
    {
        return "{$this->home_team} v {$this->away_team}";
    }

    /**
     * Kick-off in Nairobi time, e.g. "Today 17:00", "Tomorrow 20:45" or "Sat 18 Oct 14:00".
     */
    public function kickoffLabel(): string
    {
        $timezone = (string) config('sportsbook.timezone');
        $kickoff = $this->kicks_off_at->setTimezone($timezone);
        $today = now($timezone);

        return match (true) {
            $kickoff->isSameDay($today) => __('Today').' '.$kickoff->format('H:i'),
            $kickoff->isSameDay($today->addDay()) => __('Tomorrow').' '.$kickoff->format('H:i'),
            default => $kickoff->format('D j M H:i'),
        };
    }

    public function hasKickedOff(): bool
    {
        return $this->kicks_off_at->isPast();
    }

    /**
     * Scheduled fixtures that have not kicked off yet.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function upcoming(Builder $query): void
    {
        $query->where('status', FixtureStatus::Scheduled)->where('kicks_off_at', '>', now());
    }
}
