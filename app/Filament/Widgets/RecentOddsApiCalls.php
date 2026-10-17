<?php

namespace App\Filament\Widgets;

use App\Models\OddsApiCall;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The latest calls in the Odds API ledger.
 */
class RecentOddsApiCalls extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Odds API calls';

    public static function canView(): bool
    {
        return OddsApiCreditsOverview::canView();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => OddsApiCall::query()->latest('created_at')->latest('id'))
            ->paginated([10])
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('When (EAT)'))
                    ->dateTime('D j M, H:i:s')
                    ->timezone(config('sportsbook.timezone')),
                TextColumn::make('endpoint'),
                TextColumn::make('sport_key')
                    ->label(__('Competition'))
                    ->placeholder('—'),
                TextColumn::make('credits_spent')
                    ->label(__('Credits'))
                    ->numeric(),
                TextColumn::make('credits_remaining')
                    ->label(__('Remaining after'))
                    ->numeric()
                    ->placeholder('—'),
                IconColumn::make('succeeded')
                    ->boolean()
                    ->tooltip(fn (OddsApiCall $record): ?string => $record->error),
            ]);
    }
}
