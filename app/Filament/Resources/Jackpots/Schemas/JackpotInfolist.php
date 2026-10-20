<?php

namespace App\Filament\Resources\Jackpots\Schemas;

use App\Betting\Jackpot\SettleJackpot;
use App\Enums\JackpotStatus;
use App\Filament\Resources\Jackpots\Tables\JackpotsTable;
use App\Models\Jackpot;
use App\Support\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class JackpotInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make(__('Jackpot'))
                    ->columns(3)
                    ->columnSpan(2)
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (JackpotStatus $state): string => $state->label())
                            ->color(fn (JackpotStatus $state): string => JackpotsTable::statusColor($state)),
                        TextEntry::make('entry_price')
                            ->label(__('Entry price'))
                            ->formatStateUsing(fn (int $state): string => Money::format($state)),
                        TextEntry::make('closes_at')
                            ->label(__('Entries close (EAT)'))
                            ->state(fn (Jackpot $record): ?string => $record->closesAt()?->timezone(config('sportsbook.timezone'))->format('D j M Y, H:i'))
                            ->placeholder(__('No matches yet')),
                        TextEntry::make('void_limit')
                            ->label(__('Void matches allowed')),
                        TextEntry::make('settlement_version')
                            ->label(__('Times reopened'))
                            ->state(fn (Jackpot $record): int => $record->settlement_version - 1)
                            ->helperText(__('Goes up each time a corrected result reopens the jackpot.')),
                        TextEntry::make('published_at')
                            ->label(__('Published (EAT)'))
                            ->dateTime('D j M Y, H:i')
                            ->timezone(config('sportsbook.timezone'))
                            ->placeholder('—'),
                        TextEntry::make('settled_at')
                            ->label(__('Closed (EAT)'))
                            ->dateTime('D j M Y, H:i')
                            ->timezone(config('sportsbook.timezone'))
                            ->placeholder('—'),
                        TextEntry::make('cancel_reason')
                            ->label(__('Why cancelled'))
                            ->visible(fn (Jackpot $record): bool => $record->cancel_reason !== null),
                    ]),
                Section::make(__('Money'))
                    ->schema([
                        TextEntry::make('entries_taken')
                            ->label(__('Entries'))
                            ->state(fn (Jackpot $record): string => __(':count for :amount', [
                                'count' => number_format($record->entries()->count()),
                                'amount' => Money::format((int) $record->entries()->sum('stake')),
                            ])),
                        TextEntry::make('prizes')
                            ->label(fn (Jackpot $record): string => $record->status === JackpotStatus::Settled ? __('Prizes paid') : __('Prizes if settled on results so far'))
                            ->state(fn (Jackpot $record): array => self::tierLines($record))
                            ->listWithLineBreaks(),
                    ]),
            ]);
    }

    /**
     * One line per tier: its pool, its winners on the results so far, and what each gets before tax.
     *
     * @return list<string>
     */
    private static function tierLines(Jackpot $jackpot): array
    {
        return array_map(fn (array $tier): string => __(':correct right: :pool, :winners winners, :each each', [
            'correct' => $tier['correct'],
            'pool' => Money::format($tier['pool']),
            'winners' => number_format($tier['winners']),
            'each' => $tier['winners'] === 0 ? '—' : Money::format($tier['each']),
        ]), SettleJackpot::tiers($jackpot));
    }
}
