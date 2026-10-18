<?php

namespace App\Filament\Resources\Fixtures\Pages;

use App\Filament\Resources\Fixtures\FixtureResource;
use App\Models\Fixture;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListFixtures extends ListRecords
{
    protected static string $resource = FixtureResource::class;

    public function getTabs(): array
    {
        $needingResult = Fixture::query()->needingResult()->count();

        return [
            'upcoming' => Tab::make(__('Not started'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('kicks_off_at', '>', now())),
            'needs_result' => Tab::make(__('Needs a result'))
                ->modifyQueryUsing(fn (Builder $query) => self::needingResultLatestFirst($query))
                ->badge($needingResult > 0 ? $needingResult : null)
                ->badgeColor('danger'),
            'kicked_off' => Tab::make(__('Kicked off'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('kicks_off_at', '<=', now())->reorder('kicks_off_at', 'desc')),
            'all' => Tab::make(__('All')),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'upcoming';
    }

    /**
     * @param  Builder<Fixture>  $query
     * @return Builder<Fixture>
     */
    private static function needingResultLatestFirst(Builder $query): Builder
    {
        return $query->needingResult()->reorder('kicks_off_at', 'desc');
    }
}
