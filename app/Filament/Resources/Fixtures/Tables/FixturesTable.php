<?php

namespace App\Filament\Resources\Fixtures\Tables;

use App\Enums\MarketStatus;
use App\Models\Fixture;
use App\Models\Selection;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FixturesTable
{
    private const OUTCOME_LABELS = ['home' => '1', 'draw' => 'X', 'away' => '2'];

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['competition', 'matchResultMarket.selections', 'result']))
            ->defaultSort('kicks_off_at')
            ->columns([
                TextColumn::make('kicks_off_at')
                    ->label(__('Kick-off (EAT)'))
                    ->dateTime('D j M, H:i')
                    ->timezone(config('sportsbook.timezone'))
                    ->sortable(),
                TextColumn::make('match')
                    ->state(fn (Fixture $record): string => $record->name())
                    ->description(fn (Fixture $record): string => $record->competition->name)
                    ->searchable(['home_team', 'away_team']),
                TextColumn::make('match_result_prices')
                    ->label(__('1X2'))
                    ->state(fn (Fixture $record): string => $record->matchResultMarket?->selections
                        ->map(fn (Selection $selection): string => (self::OUTCOME_LABELS[$selection->outcome] ?? $selection->outcome).' '
                            .($selection->price() ?? '—').($selection->hasOverride() ? '*' : ''))
                        ->implode('  ·  ') ?: '—')
                    ->fontFamily('mono'),
                TextColumn::make('matchResultMarket.status')
                    ->label(__('1X2 status'))
                    ->badge()
                    ->color(fn (?MarketStatus $state): string => $state === MarketStatus::Open ? 'success' : 'danger')
                    ->formatStateUsing(fn (?MarketStatus $state): string => $state === null ? '—' : ucfirst($state->value)),
                TextColumn::make('result_score')
                    ->label(__('Result'))
                    ->state(fn (Fixture $record): ?string => $record->result?->scoreLine())
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('competition')
                    ->relationship('competition', 'name', fn (Builder $query) => $query->where('is_synced', true)),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
