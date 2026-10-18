<?php

namespace App\Filament\Resources\Bets\Schemas;

use App\Enums\BetStatus;
use App\Enums\BetType;
use App\Enums\OddsChangePolicy;
use App\Filament\Resources\Bets\Tables\BetsTable;
use App\Filament\Resources\Players\PlayerResource;
use App\Models\Bet;
use App\Support\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class BetInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(4)
            ->components([
                TextEntry::make('reference')
                    ->fontFamily('mono')
                    ->copyable(),
                TextEntry::make('user.phone')
                    ->label(__('Player'))
                    ->state(fn (Bet $record): string => $record->user->phone ?? $record->user->email)
                    ->url(fn (Bet $record): string => PlayerResource::getUrl('view', ['record' => $record->user])),
                TextEntry::make('placed_at')
                    ->label(__('Placed (EAT)'))
                    ->dateTime('D j M Y, H:i:s')
                    ->timezone(config('sportsbook.timezone')),
                TextEntry::make('type')
                    ->formatStateUsing(fn (BetType $state): string => $state->label()),
                TextEntry::make('stake')
                    ->formatStateUsing(fn (int $state): string => Money::format($state)),
                TextEntry::make('total_odds')
                    ->label(__('Odds at placement')),
                TextEntry::make('potential_payout')
                    ->label(__('To win'))
                    ->formatStateUsing(fn (int $state): string => Money::format($state)),
                TextEntry::make('odds_change_policy')
                    ->label(__('Odds changes'))
                    ->formatStateUsing(fn (OddsChangePolicy $state): string => $state->label()),
                TextEntry::make('status')
                    ->badge()
                    ->formatStateUsing(fn (BetStatus $state): string => $state->label())
                    ->color(fn (BetStatus $state): string => BetsTable::statusColor($state)),
                TextEntry::make('payout')
                    ->label(__('Paid'))
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->placeholder('—'),
                TextEntry::make('settled_at')
                    ->label(__('Settled (EAT)'))
                    ->dateTime('D j M Y, H:i')
                    ->timezone(config('sportsbook.timezone'))
                    ->placeholder('—'),
                TextEntry::make('settlement_version')
                    ->label(__('Times resettled'))
                    ->helperText(__('Goes up each time a corrected result or a voided leg reopens the bet.')),
            ]);
    }
}
