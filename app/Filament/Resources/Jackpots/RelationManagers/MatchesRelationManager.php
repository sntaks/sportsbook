<?php

namespace App\Filament\Resources\Jackpots\RelationManagers;

use App\Filament\Resources\Fixtures\FixtureResource;
use App\Models\JackpotFixture;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The jackpot's matches with their results. Results are entered on each match's page, as for bets.
 */
class MatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'jackpotFixtures';

    protected static ?string $title = 'Matches';

    public function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->columns([
                TextColumn::make('position')
                    ->label('#'),
                TextColumn::make('fixture_name')
                    ->label(__('Match'))
                    ->description(fn (JackpotFixture $record): string => $record->kicks_off_at->timezone(config('sportsbook.timezone'))->format('D j M, H:i'))
                    ->url(fn (JackpotFixture $record): string => FixtureResource::getUrl('view', ['record' => $record->fixture_id])),
                TextColumn::make('outcome')
                    ->label(__('Result'))
                    ->state(fn (JackpotFixture $record): string => $record->is_void ? __('Void') : ($record->outcome ?? '—'))
                    ->badge()
                    ->color(fn (JackpotFixture $record): string => match (true) {
                        $record->is_void => 'gray',
                        $record->outcome !== null => 'success',
                        default => 'info',
                    })
                    ->description(fn (JackpotFixture $record): ?string => $record->void_reason)
                    ->wrap(),
            ]);
    }
}
