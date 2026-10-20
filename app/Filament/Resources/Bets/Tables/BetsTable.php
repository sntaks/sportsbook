<?php

namespace App\Filament\Resources\Bets\Tables;

use App\Enums\BetStatus;
use App\Enums\BetType;
use App\Models\Bet;
use App\Support\Money;
use App\Support\PhoneNumber;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The bets table, used by the bets list and on each player's page.
 */
class BetsTable
{
    public static function configure(Table $table, bool $showPlayer = true): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user')->withCount('legs'))
            ->defaultSort('placed_at', 'desc')
            ->columns([
                TextColumn::make('placed_at')
                    ->label(__('Placed (EAT)'))
                    ->dateTime('j M, H:i')
                    ->timezone(config('sportsbook.timezone'))
                    ->sortable(),
                TextColumn::make('reference')
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('user.phone')
                    ->label(__('Player'))
                    ->description(fn (Bet $record): ?string => $record->user->name)
                    // Only a number is matched against phones: a reference like "ABCD7EFG" would otherwise match every phone containing a 7.
                    ->searchable(query: fn (Builder $query, string $search) => $query->when(
                        preg_match('/^[\d\s+()-]+$/', $search) === 1,
                        fn (Builder $query) => $query->whereHas('user', fn (Builder $users) => $users
                            ->where('phone', 'like', '%'.PhoneNumber::normalize($search).'%')),
                    ))
                    ->visible($showPlayer),
                TextColumn::make('type')
                    ->formatStateUsing(fn (BetType $state, Bet $record): string => $state === BetType::Multi
                        ? __('Multi (:legs)', ['legs' => $record->legs_count])
                        : $state->label()),
                TextColumn::make('stake')
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->description(fn (Bet $record): ?string => $record->isFreeBet() ? __('Free bet') : null)
                    ->sortable(),
                TextColumn::make('total_odds')
                    ->label(__('Odds'))
                    ->fontFamily('mono'),
                TextColumn::make('potential_payout')
                    ->label(__('To win'))
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (BetStatus $state): string => $state->label())
                    ->color(fn (BetStatus $state): string => self::statusColor($state)),
                TextColumn::make('payout')
                    ->label(__('Paid'))
                    ->formatStateUsing(fn (?int $state): string => Money::format($state ?? 0))
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(BetStatus::cases())->mapWithKeys(fn (BetStatus $status): array => [$status->value => $status->label()])->all()),
                SelectFilter::make('type')
                    ->options(collect(BetType::cases())->mapWithKeys(fn (BetType $type): array => [$type->value => $type->label()])->all()),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function statusColor(BetStatus $status): string
    {
        return match ($status) {
            BetStatus::Open => 'info',
            BetStatus::Won => 'success',
            BetStatus::Lost => 'danger',
            BetStatus::Void, BetStatus::Cancelled => 'gray',
        };
    }
}
