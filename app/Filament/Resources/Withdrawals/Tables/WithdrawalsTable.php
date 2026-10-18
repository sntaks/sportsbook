<?php

namespace App\Filament\Resources\Withdrawals\Tables;

use App\Enums\WithdrawalStatus;
use App\Filament\Actions\WithdrawalActions;
use App\Models\Withdrawal;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The withdrawals table, used by the withdrawals queue and on each player's page.
 */
class WithdrawalsTable
{
    public static function configure(Table $table, bool $showPlayer = true): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'reviewedBy']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Requested (EAT)'))
                    ->dateTime('j M, H:i')
                    ->timezone(config('sportsbook.timezone'))
                    ->sortable(),
                TextColumn::make('user.phone')
                    ->label(__('Player'))
                    ->description(fn (Withdrawal $record): ?string => $record->user?->name)
                    ->searchable()
                    ->visible($showPlayer),
                TextColumn::make('amount')
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (WithdrawalStatus $state): string => $state->label())
                    ->color(fn (WithdrawalStatus $state): string => match ($state) {
                        WithdrawalStatus::Completed => 'success',
                        WithdrawalStatus::AwaitingApproval, WithdrawalStatus::Processing, WithdrawalStatus::Queued => 'warning',
                        WithdrawalStatus::Failed, WithdrawalStatus::Rejected => 'danger',
                    })
                    ->description(fn (Withdrawal $record) => $record->isStuck((int) config('sportsbook.wallet.payout_stuck_after_minutes'))
                        ? __('No answer from M-Pesa: check the portal')
                        : $record->result_description)
                    ->wrap(),
                TextColumn::make('mpesa_receipt')
                    ->label(__('Receipt'))
                    ->placeholder('—')
                    ->searchable()
                    ->fontFamily('mono'),
                TextColumn::make('reviewedBy.name')
                    ->label(__('Reviewed by'))
                    ->description(fn (Withdrawal $record): ?string => $record->review_note)
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(WithdrawalStatus::cases())->mapWithKeys(fn (WithdrawalStatus $status): array => [$status->value => $status->label()])->all()),
                Filter::make('needs_attention')
                    ->label(__('Needs attention'))
                    ->query(fn (Builder $query) => $query->where(fn (Builder $query) => $query
                        ->where('status', WithdrawalStatus::AwaitingApproval)
                        ->orWhere(fn (Builder $query) => $query
                            ->where('status', WithdrawalStatus::Processing)
                            ->where('sent_at', '<=', now()->subMinutes((int) config('sportsbook.wallet.payout_stuck_after_minutes')))))),
            ])
            ->recordActions(WithdrawalActions::all());
    }
}
