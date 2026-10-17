<?php

namespace App\Filament\Resources\Competitions;

use App\Filament\Resources\Competitions\Pages\ManageCompetitions;
use App\Models\Competition;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CompetitionResource extends Resource
{
    protected static ?string $model = Competition::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|UnitEnum|null $navigationGroup = 'Trading';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with('sport')
                ->withCount('upcomingFixtures'))
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->description(fn (Competition $record): string => $record->odds_api_key)
                    ->searchable(['name', 'odds_api_key']),
                TextColumn::make('sport.name')
                    ->label(__('Sport')),
                ToggleColumn::make('is_synced')
                    ->label(__('Synced'))
                    ->disabled(fn (Competition $record): bool => ! self::canManage($record)),
                TextInputColumn::make('sort_order')
                    ->label(__('Order'))
                    ->rules(['required', 'integer', 'min:0', 'max:1000'])
                    ->disabled(fn (Competition $record): bool => ! self::canManage($record))
                    ->sortable(),
                TextColumn::make('upcoming_fixtures_count')
                    ->label(__('Upcoming fixtures'))
                    ->numeric(),
                IconColumn::make('is_active_in_feed')
                    ->label(__('In season'))
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_synced')
                    ->label(__('Synced')),
                TernaryFilter::make('is_active_in_feed')
                    ->label(__('In season')),
            ]);
    }

    private static function canManage(Competition $competition): bool
    {
        return Filament::auth()->user()?->can('update', $competition) ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCompetitions::route('/'),
        ];
    }
}
