<?php

namespace App\Filament\Resources\Jackpots\Tables;

use App\Enums\JackpotStatus;
use App\Support\Money;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class JackpotsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['jackpotFixtures', 'entries'])->withSum('entries', 'stake'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (JackpotStatus $state): string => $state->label())
                    ->color(fn (JackpotStatus $state): string => self::statusColor($state)),
                TextColumn::make('jackpot_fixtures_count')
                    ->label(__('Matches')),
                TextColumn::make('entry_price')
                    ->label(__('Entry'))
                    ->formatStateUsing(fn (int $state): string => Money::format($state)),
                TextColumn::make('entries_count')
                    ->label(__('Entries'))
                    ->numeric(),
                TextColumn::make('entries_sum_stake')
                    ->label(__('Taken'))
                    ->formatStateUsing(fn (?int $state): string => Money::format((int) $state))
                    ->placeholder(Money::format(0)),
                TextColumn::make('published_at')
                    ->label(__('Published (EAT)'))
                    ->dateTime('j M, H:i')
                    ->timezone(config('sportsbook.timezone'))
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(JackpotStatus::cases())->mapWithKeys(fn (JackpotStatus $status): array => [$status->value => $status->label()])->all()),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function statusColor(JackpotStatus $status): string
    {
        return match ($status) {
            JackpotStatus::Draft => 'gray',
            JackpotStatus::Open => 'info',
            JackpotStatus::Settled => 'success',
            JackpotStatus::Cancelled => 'danger',
        };
    }
}
