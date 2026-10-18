<?php

namespace App\Filament\Resources\Deposits\Tables;

use App\Enums\MpesaDepositChannel;
use App\Enums\MpesaDepositStatus;
use App\Filament\Actions\DepositActions;
use App\Models\MpesaDeposit;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The M-Pesa deposits table, used by the deposits list and on each player's page.
 */
class DepositsTable
{
    public static function configure(Table $table, bool $showPlayer = true): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'resolvedBy']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Started (EAT)'))
                    ->dateTime('j M, H:i')
                    ->timezone(config('sportsbook.timezone'))
                    ->sortable(),
                TextColumn::make('user.phone')
                    ->label(__('Player'))
                    ->placeholder(fn (MpesaDeposit $record): string => __('Account: :account', ['account' => $record->account_reference ?? '—']))
                    ->searchable()
                    ->visible($showPlayer),
                TextColumn::make('channel')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (MpesaDepositChannel $state): string => $state === MpesaDepositChannel::Stk ? __('STK push') : __('Paybill')),
                TextColumn::make('amount')
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->description(fn (MpesaDeposit $record) => $record->paid_amount !== null && $record->paid_amount !== $record->amount
                        ? __('M-Pesa received :amount', ['amount' => Money::format($record->paid_amount)])
                        : null)
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (MpesaDepositStatus $state): string => $state->label())
                    ->color(fn (MpesaDepositStatus $state): string => match ($state) {
                        MpesaDepositStatus::Completed => 'success',
                        MpesaDepositStatus::Pending => 'warning',
                        MpesaDepositStatus::Mismatch, MpesaDepositStatus::Unmatched => 'danger',
                        default => 'gray',
                    })
                    ->description(fn (MpesaDeposit $record): ?string => $record->resolution_note ?? $record->result_description)
                    ->wrap(),
                TextColumn::make('mpesa_receipt')
                    ->label(__('Receipt'))
                    ->placeholder('—')
                    ->searchable()
                    ->fontFamily('mono'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(MpesaDepositStatus::cases())->mapWithKeys(fn (MpesaDepositStatus $status): array => [$status->value => $status->label()])->all()),
                SelectFilter::make('channel')
                    ->options([MpesaDepositChannel::Stk->value => __('STK push'), MpesaDepositChannel::Paybill->value => __('Paybill')]),
                Filter::make('needs_attention')
                    ->label(__('Needs attention'))
                    ->query(fn (Builder $query) => $query->whereIn('status', [MpesaDepositStatus::Mismatch, MpesaDepositStatus::Unmatched])),
            ])
            ->recordActions(DepositActions::all());
    }
}
