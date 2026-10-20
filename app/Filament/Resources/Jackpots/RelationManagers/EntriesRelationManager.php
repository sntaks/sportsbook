<?php

namespace App\Filament\Resources\Jackpots\RelationManagers;

use App\Enums\JackpotEntryStatus;
use App\Filament\Resources\Players\PlayerResource;
use App\Models\JackpotEntry;
use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who entered, with each entry's picks and how it settled.
 */
class EntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'entries';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'picks.jackpotFixture']))
            ->defaultSort('placed_at', 'desc')
            ->columns([
                TextColumn::make('placed_at')
                    ->label(__('Entered (EAT)'))
                    ->dateTime('j M, H:i')
                    ->timezone(config('sportsbook.timezone'))
                    ->sortable(),
                TextColumn::make('reference')
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('user.phone')
                    ->label(__('Player'))
                    ->url(fn (JackpotEntry $record): string => PlayerResource::getUrl('view', ['record' => $record->user])),
                TextColumn::make('picks')
                    ->label(__('Picks'))
                    ->state(fn (JackpotEntry $record): string => $record->picks->sortBy(fn ($pick) => $pick->jackpotFixture->position)->pluck('pick')->implode(' '))
                    ->fontFamily('mono'),
                TextColumn::make('correct_count')
                    ->label(__('Right'))
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (JackpotEntryStatus $state): string => $state->label())
                    ->color(fn (JackpotEntryStatus $state): string => match ($state) {
                        JackpotEntryStatus::Open => 'info',
                        JackpotEntryStatus::Won => 'success',
                        JackpotEntryStatus::Lost => 'danger',
                        JackpotEntryStatus::Refunded => 'gray',
                    }),
                TextColumn::make('prize')
                    ->label(__('Paid'))
                    ->formatStateUsing(fn (?int $state): string => Money::format($state ?? 0))
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(JackpotEntryStatus::cases())->mapWithKeys(fn (JackpotEntryStatus $status): array => [$status->value => $status->label()])->all()),
            ]);
    }
}
