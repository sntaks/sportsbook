<?php

namespace App\Filament\Resources\Fixtures\Schemas;

use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Models\FixtureResult;
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
                TextEntry::make('result_score')
                    ->label(__('Result'))
                    ->state(fn (Fixture $record): ?string => $record->result?->scoreLine())
                    ->placeholder(__('No result yet'))
                    ->badge()
                    ->color(fn (Fixture $record): string => $record->result?->is_void ? 'danger' : 'success')
                    ->helperText(fn (Fixture $record): ?string => self::recordedBy($record->result)),
                TextEntry::make('result.note')
                    ->label(__('Result note'))
                    ->placeholder('—'),
                TextEntry::make('status')
                    ->badge()
                    ->formatStateUsing(fn (FixtureStatus $state): string => $state === FixtureStatus::Cancelled ? __('Called off') : __('Scheduled'))
                    ->color(fn (FixtureStatus $state): string => $state === FixtureStatus::Cancelled ? 'danger' : 'gray'),
            ]);
    }

    private static function recordedBy(?FixtureResult $result): ?string
    {
        if ($result === null) {
            return null;
        }

        return $result->source === FixtureResult::SOURCE_FEED
            ? __('From the scores feed')
            : __('Entered by :admin', ['admin' => $result->recordedBy->name ?? __('an admin')]);
    }
}
