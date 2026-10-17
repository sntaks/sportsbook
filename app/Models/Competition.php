<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\CompetitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A league or cup as listed by The Odds API (one "sport key" there, e.g. soccer_epl).
 *
 * @property int $id
 * @property int $sport_id
 * @property string $odds_api_key
 * @property string $name
 * @property string|null $description
 * @property bool $is_active_in_feed
 * @property bool $is_synced
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['sport_id', 'odds_api_key', 'name', 'description', 'is_active_in_feed', 'is_synced', 'sort_order'])]
class Competition extends Model
{
    /** @use HasFactory<CompetitionFactory> */
    use HasFactory, LogsActivity;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active_in_feed' => 'boolean',
            'is_synced' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Sport, $this>
     */
    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    /**
     * @return HasMany<Fixture, $this>
     */
    public function fixtures(): HasMany
    {
        return $this->hasMany(Fixture::class);
    }

    /**
     * @return HasMany<Fixture, $this>
     */
    public function upcomingFixtures(): HasMany
    {
        return $this->fixtures()->upcoming();
    }

    /**
     * Competitions whose fixtures and odds are pulled from the feed.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function synced(Builder $query): void
    {
        $query->where('is_synced', true)->where('is_active_in_feed', true);
    }

    /**
     * Record who switched syncing on or off; each switch changes what the scheduled sync spends.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('odds')
            ->logOnly(['is_synced', 'sort_order'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $eventName): string => "Competition {$eventName}");
    }
}
