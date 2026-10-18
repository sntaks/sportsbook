<?php

namespace App\Filament\Actions;

use App\Enums\WithdrawalStatus;
use App\Filament\CurrentAdmin;
use App\Models\Admin;
use App\Models\Withdrawal;
use App\Support\Money;
use App\Wallet\Withdrawals\CheckWithdrawalStatus;
use App\Wallet\Withdrawals\ReviewWithdrawal;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * Finance's withdrawal actions, shared by the withdrawals queue and the player page.
 */
class WithdrawalActions
{
    /**
     * @return list<Action>
     */
    public static function all(): array
    {
        return [
            Action::make('approve')
                ->icon(Heroicon::OutlinedCheck)
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription(fn (Withdrawal $record): string => __('Pay :amount to :phone through M-Pesa now.', ['amount' => Money::format($record->amount), 'phone' => $record->phone]))
                ->authorize(fn (Withdrawal $record): bool => self::canReview($record))
                ->visible(fn (Withdrawal $record): bool => $record->status === WithdrawalStatus::AwaitingApproval)
                ->action(fn (Withdrawal $record) => CurrentAdmin::run(
                    fn (Admin $admin) => app(ReviewWithdrawal::class)->approve($record, $admin),
                    __('Approved; the payout is on its way'),
                )),
            Action::make('reject')
                ->icon(Heroicon::OutlinedXMark)
                ->color('danger')
                ->authorize(fn (Withdrawal $record): bool => self::canReview($record))
                ->visible(fn (Withdrawal $record): bool => $record->status === WithdrawalStatus::AwaitingApproval)
                ->schema([Textarea::make('reason')->required()->maxLength(255)])
                ->action(fn (Withdrawal $record, array $data) => CurrentAdmin::run(
                    fn (Admin $admin) => app(ReviewWithdrawal::class)->reject($record, $admin, $data['reason']),
                    __('Rejected; the money is back in the player\'s wallet'),
                )),
            Action::make('checkStatus')
                ->label(__('Check status'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->authorize(fn (Withdrawal $record): bool => self::canReview($record))
                ->visible(fn (Withdrawal $record): bool => $record->status === WithdrawalStatus::Processing)
                ->action(fn (Withdrawal $record) => CurrentAdmin::run(
                    fn (Admin $admin) => app(CheckWithdrawalStatus::class)->request($record, $admin),
                    __('Status check sent; the answer will update this payout'),
                )),
            Action::make('markPaid')
                ->label(__('Mark paid'))
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->authorize(fn (Withdrawal $record): bool => self::canReview($record))
                ->visible(fn (Withdrawal $record): bool => $record->isStuck((int) config('sportsbook.wallet.payout_stuck_after_minutes')))
                ->modalDescription(__('Only after confirming in the M-Pesa portal that this payout reached the player.'))
                ->schema([
                    TextInput::make('receipt')->label(__('M-Pesa receipt'))->required()->maxLength(20)->alphaNum(),
                    Textarea::make('note')->required()->maxLength(255),
                ])
                ->action(fn (Withdrawal $record, array $data) => CurrentAdmin::run(
                    fn (Admin $admin) => app(ReviewWithdrawal::class)->markPaid($record, $admin, strtoupper($data['receipt']), $data['note']),
                    __('Marked paid'),
                )),
            Action::make('markFailed')
                ->label(__('Mark failed'))
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('danger')
                ->authorize(fn (Withdrawal $record): bool => self::canReview($record))
                ->visible(fn (Withdrawal $record): bool => $record->isStuck((int) config('sportsbook.wallet.payout_stuck_after_minutes')))
                ->modalDescription(__('Only after confirming in the M-Pesa portal that this payout did NOT go through. The money goes back to the player\'s wallet.'))
                ->schema([Textarea::make('note')->required()->maxLength(255)])
                ->action(fn (Withdrawal $record, array $data) => CurrentAdmin::run(
                    fn (Admin $admin) => app(ReviewWithdrawal::class)->markFailed($record, $admin, $data['note']),
                    __('Marked failed; the money is back in the player\'s wallet'),
                )),
        ];
    }

    private static function canReview(Withdrawal $withdrawal): bool
    {
        return Filament::auth()->user()?->can('update', $withdrawal) ?? false;
    }
}
