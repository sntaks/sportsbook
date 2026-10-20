<?php

namespace App\Filament\Exports;

use App\Enums\MpesaDepositChannel;
use App\Enums\MpesaDepositStatus;
use App\Models\MpesaDeposit;
use Filament\Actions\Exports\ExportColumn;
use Illuminate\Database\Eloquent\Builder;

/**
 * M-Pesa deposits as filtered on the deposits list.
 */
class MpesaDepositExporter extends SpreadsheetExporter
{
    protected static ?string $model = MpesaDeposit::class;

    protected static function rowsName(): string
    {
        return __('deposits');
    }

    protected static function columns(): array
    {
        return [
            ExportColumn::make('id')->label(__('ID')),
            static::eatTime('created_at', __('Started (EAT)')),
            static::eatTime('completed_at', __('Completed (EAT)')),
            ExportColumn::make('user.phone')->label(__('Player phone')),
            ExportColumn::make('account_reference')->label(__('Paybill account')),
            ExportColumn::make('channel')->label(__('Channel'))->formatStateUsing(fn (MpesaDepositChannel $state): string => $state === MpesaDepositChannel::Stk ? __('STK push') : __('Paybill')),
            ExportColumn::make('status')->label(__('Status'))->formatStateUsing(fn (MpesaDepositStatus $state): string => $state->label()),
            static::money('amount', __('Amount (KES)')),
            static::money('paid_amount', __('M-Pesa received (KES)')),
            ExportColumn::make('mpesa_receipt')->label(__('M-Pesa receipt')),
            ExportColumn::make('result_description')->label(__('M-Pesa result')),
            ExportColumn::make('resolution_note')->label(__('Resolution note')),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with('user');
    }
}
