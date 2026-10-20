<?php

namespace App\Filament\Resources\Players\Pages;

use App\Accounts\PlayerStatus;
use App\Filament\CurrentAdmin;
use App\Filament\Resources\Players\PlayerResource;
use App\Models\Admin;
use App\Models\User;
use App\Promotions\GrantFreeBet;
use App\Support\Money;
use App\Wallet\AdjustBalance;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewPlayer extends ViewRecord
{
    protected static string $resource = PlayerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('suspend')
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('danger')
                ->authorize(fn (): bool => $this->can('update'))
                ->visible(fn (): bool => ! $this->player()->isSuspended())
                ->modalDescription(__('The player can still log in and see their wallet, but cannot deposit, withdraw or bet.'))
                ->schema([Textarea::make('reason')->required()->maxLength(255)])
                ->action(fn (array $data) => CurrentAdmin::run(
                    fn (Admin $admin) => app(PlayerStatus::class)->suspend($this->player(), $admin, $data['reason']),
                    __('Player suspended'),
                )),
            Action::make('reinstate')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->authorize(fn (): bool => $this->can('update'))
                ->visible(fn (): bool => $this->player()->isSuspended())
                ->schema([Textarea::make('reason')->required()->maxLength(255)])
                ->action(fn (array $data) => CurrentAdmin::run(
                    fn (Admin $admin) => app(PlayerStatus::class)->reinstate($this->player(), $admin, $data['reason']),
                    __('Player reinstated'),
                )),
            Action::make('adjustBalance')
                ->label(__('Adjust balance'))
                ->icon(Heroicon::OutlinedScale)
                ->color('gray')
                ->authorize(fn (): bool => $this->can('adjustBalance'))
                ->modalDescription(__('Adds or takes away money as a ledger entry with your name and reason. Use a minus sign to take money away.'))
                ->schema([
                    TextInput::make('amount')
                        ->label(__('Amount (KES)'))
                        ->numeric()
                        ->required()
                        ->step(0.01)
                        ->minValue(-1_000_000)
                        ->maxValue(1_000_000)
                        ->notIn([0]),
                    Textarea::make('reason')->required()->maxLength(255),
                ])
                ->action(fn (array $data) => CurrentAdmin::run(
                    fn (Admin $admin) => app(AdjustBalance::class)->handle($this->player(), $admin, Money::fromAmount($data['amount']), $data['reason']),
                    __('Balance adjusted'),
                )),
            Action::make('grantFreeBet')
                ->label(__('Give free bet'))
                ->icon(Heroicon::OutlinedGift)
                ->color('gray')
                ->authorize(fn (): bool => $this->can('grantFreeBet'))
                ->modalDescription(__('The player uses it whole as the stake on one bet. A win pays the winnings above the stake, as cash.'))
                ->schema([
                    TextInput::make('amount')
                        ->label(__('Amount (KES)'))
                        ->integer()
                        ->required()
                        ->minValue((int) config('sportsbook.limits.min_stake'))
                        ->maxValue((int) config('sportsbook.limits.max_stake')),
                    TextInput::make('min_odds')
                        ->label(__('Minimum total odds'))
                        ->numeric()
                        ->required()
                        ->step(0.01)
                        ->minValue(1.01)
                        ->default(config('sportsbook.promotions.free_bets.min_odds')),
                    TextInput::make('days')
                        ->label(__('Days to use it'))
                        ->integer()
                        ->required()
                        ->minValue(1)
                        ->maxValue(90)
                        ->default(config('sportsbook.promotions.free_bets.expiry_days')),
                    Textarea::make('note')->label(__('Why'))->required()->maxLength(255),
                ])
                ->action(fn (array $data) => CurrentAdmin::run(
                    fn (Admin $admin) => app(GrantFreeBet::class)->byAdmin($this->player(), $admin, Money::fromShillings((int) $data['amount']), (string) $data['min_odds'], now()->addDays((int) $data['days']), $data['note']),
                    __('Free bet given'),
                )),
        ];
    }

    private function player(): User
    {
        $record = $this->getRecord();

        abort_unless($record instanceof User, 404);

        return $record->refresh();
    }

    private function can(string $ability): bool
    {
        return Filament::auth()->user()?->can($ability, $this->player()) ?? false;
    }
}
