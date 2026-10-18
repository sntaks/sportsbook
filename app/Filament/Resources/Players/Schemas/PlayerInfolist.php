<?php

namespace App\Filament\Resources\Players\Schemas;

use App\Enums\UserStatus;
use App\Models\DepositLimit;
use App\Models\User;
use App\Support\Money;
use App\Wallet\Ledger;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PlayerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make(__('Account'))
                    ->columns(2)
                    ->columnSpan(2)
                    ->schema([
                        TextEntry::make('phone'),
                        TextEntry::make('name')->placeholder('—'),
                        TextEntry::make('email')->placeholder('—'),
                        TextEntry::make('created_at')->label(__('Joined'))->dateTime('j M Y, H:i')->timezone(config('sportsbook.timezone')),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (UserStatus $state): string => $state->label())
                            ->color(fn (UserStatus $state): string => $state === UserStatus::Active ? 'success' : 'danger'),
                        TextEntry::make('suspension_reason')->placeholder('—'),
                        TextEntry::make('exclusion')
                            ->label(__('Break from betting'))
                            ->state(fn (User $record): string => $record->isExcluded()
                                ? __(':type until :date', ['type' => $record->exclusion_type?->label(), 'date' => $record->excluded_until?->timezone(config('sportsbook.timezone'))->format('j M Y, H:i')])
                                : __('None')),
                        TextEntry::make('deposit_limits')
                            ->label(__('Deposit limits'))
                            ->state(fn (User $record): array => self::limits($record))
                            ->listWithLineBreaks()
                            ->placeholder(__('None')),
                    ]),
                Section::make(__('Wallet'))
                    ->schema([
                        TextEntry::make('balance')
                            ->state(fn (User $record): string => Money::format(app(Ledger::class)->balanceOf($record)))
                            ->size('lg')
                            ->weight('bold'),
                    ]),
            ]);
    }

    /**
     * @return list<string>
     */
    private static function limits(User $player): array
    {
        return array_values($player->depositLimits()
            ->get()
            ->map(function (DepositLimit $limit): ?string {
                $amount = $limit->effectiveAmount();
                $line = $amount === null ? null : $limit->period->label().': '.Money::format($amount);

                if ($limit->hasWaitingChange()) {
                    $line = trim(($line ?? $limit->period->label().': '.__('none')).' '.__('(changing to :amount on :date)', [
                        'amount' => $limit->pending_amount === null ? __('no limit') : Money::format($limit->pending_amount),
                        'date' => $limit->pending_from?->timezone(config('sportsbook.timezone'))->format('j M, H:i'),
                    ]));
                }

                return $line;
            })
            ->filter()
            ->all());
    }
}
