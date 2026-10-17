<?php

namespace App\Filament\Resources\Fixtures\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class FixtureInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                TextEntry::make('competition.name')
                    ->label(__('Competition')),
                TextEntry::make('kicks_off_at')
                    ->label(__('Kick-off (EAT)'))
                    ->dateTime('D j M Y, H:i')
                    ->timezone(config('sportsbook.timezone')),
                TextEntry::make('last_seen_in_feed_at')
                    ->label(__('Last seen in feed'))
                    ->since()
                    ->placeholder(__('Never')),
                TextEntry::make('home_team'),
                TextEntry::make('away_team'),
                TextEntry::make('external_id')
                    ->label(__('Odds API id'))
                    ->copyable(),
            ]);
    }
}
