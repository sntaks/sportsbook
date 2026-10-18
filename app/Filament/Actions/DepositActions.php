<?php

namespace App\Filament\Actions;

use App\Enums\MpesaDepositChannel;
use App\Enums\MpesaDepositStatus;
use App\Filament\CurrentAdmin;
use App\Models\Admin;
use App\Models\MpesaDeposit;
use App\Models\User;
use App\Support\Money;
use App\Support\PhoneNumber;
use App\Wallet\Deposits\ReconcilePendingDeposits;
use App\Wallet\Deposits\ResolveDeposit;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;

/**
 * Finance's deposit actions, shared by the deposits list and the player page.
 */
class DepositActions
{
    /**
     * @return list<Action>
     */
    public static function all(): array
    {
        return [
            Action::make('checkStatus')
                ->label(__('Check status'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->authorize(fn (MpesaDeposit $record): bool => self::canResolve($record))
                ->visible(fn (MpesaDeposit $record): bool => $record->status === MpesaDepositStatus::Pending
                    && $record->channel === MpesaDepositChannel::Stk
                    && $record->checkout_request_id !== null)
                ->action(fn (MpesaDeposit $record) => CurrentAdmin::run(
                    fn () => app(ReconcilePendingDeposits::class)->reconcileNow($record),
                    __('Checked with M-Pesa'),
                )),
            Action::make('credit')
                ->label(fn (MpesaDeposit $record): string => $record->status === MpesaDepositStatus::Unmatched ? __('Assign to player') : __('Credit amount received'))
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success')
                ->authorize(fn (MpesaDeposit $record): bool => self::canResolve($record))
                ->visible(fn (MpesaDeposit $record): bool => $record->needsResolution())
                ->modalDescription(fn (MpesaDeposit $record): string => __('Credits :amount, what M-Pesa received.', ['amount' => Money::format($record->paid_amount ?? $record->amount)]))
                ->schema(fn (MpesaDeposit $record): array => [
                    ...($record->status === MpesaDepositStatus::Unmatched ? [
                        Select::make('player_id')
                            ->label(__('Player'))
                            ->required()
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => User::query()
                                ->where('phone', 'like', '%'.(PhoneNumber::normalize($search) ?? $search).'%')
                                ->limit(20)
                                ->get()
                                ->mapWithKeys(fn (User $user): array => [$user->id => "{$user->phone} {$user->name}"])
                                ->all())
                            ->getOptionLabelUsing(fn (mixed $value): ?string => User::query()->whereKey($value)->value('phone')),
                    ] : []),
                    Textarea::make('note')->required()->maxLength(255),
                ])
                ->action(fn (MpesaDeposit $record, array $data) => CurrentAdmin::run(
                    fn (Admin $admin) => app(ResolveDeposit::class)->credit(
                        $record,
                        $admin,
                        isset($data['player_id']) ? User::query()->whereKey($data['player_id'])->first() : null,
                        $data['note'],
                    ),
                    __('Credited to the player'),
                )),
            Action::make('dismiss')
                ->icon(Heroicon::OutlinedArchiveBoxXMark)
                ->color('gray')
                ->authorize(fn (MpesaDeposit $record): bool => self::canResolve($record))
                ->visible(fn (MpesaDeposit $record): bool => $record->needsResolution())
                ->modalDescription(__('Closes this payment without crediting anyone, for example after refunding it in the M-Pesa portal.'))
                ->schema([Textarea::make('note')->required()->maxLength(255)])
                ->action(fn (MpesaDeposit $record, array $data) => CurrentAdmin::run(
                    fn (Admin $admin) => app(ResolveDeposit::class)->dismiss($record, $admin, $data['note']),
                    __('Dismissed'),
                )),
        ];
    }

    private static function canResolve(MpesaDeposit $deposit): bool
    {
        return Filament::auth()->user()?->can('update', $deposit) ?? false;
    }
}
