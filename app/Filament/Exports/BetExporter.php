<?php

namespace App\Filament\Exports;

use App\Enums\BetStatus;
use App\Enums\BetType;
use App\Models\Bet;
use Filament\Actions\Exports\ExportColumn;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bets as filtered on the bets list, one row per bet.
 */
class BetExporter extends SpreadsheetExporter
{
    protected static ?string $model = Bet::class;

    protected static function rowsName(): string
    {
        return __('bets');
    }

    protected static function columns(): array
    {
        return [
            ExportColumn::make('reference')->label(__('Reference')),
            static::eatTime('placed_at', __('Placed (EAT)')),
            ExportColumn::make('user.phone')->label(__('Player phone')),
            ExportColumn::make('type')->label(__('Type'))->formatStateUsing(fn (BetType $state): string => $state->label()),
            ExportColumn::make('status')->label(__('Status'))->formatStateUsing(fn (BetStatus $state): string => $state->label()),
            static::money('stake', __('Stake (KES)')),
            ExportColumn::make('free_bet_id')->label(__('Free bet'))->formatStateUsing(fn (mixed $state): string => blank($state) ? __('No') : __('Yes')),
            ExportColumn::make('total_odds')->label(__('Odds')),
            ExportColumn::make('legs_count')->label(__('Legs'))->counts('legs'),
            static::money('potential_payout', __('Potential payout (KES)')),
            static::money('payout', __('Paid (KES)')),
            static::money('bonus', __('Multibet bonus (KES)')),
            static::eatTime('settled_at', __('Settled (EAT)')),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with('user');
    }
}
