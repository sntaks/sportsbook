<?php

namespace App\Filament\Exports;

use App\Enums\WithdrawalStatus;
use App\Models\Withdrawal;
use Filament\Actions\Exports\ExportColumn;
use Illuminate\Database\Eloquent\Builder;

/**
 * Withdrawals as filtered on the withdrawals list.
 */
class WithdrawalExporter extends SpreadsheetExporter
{
    protected static ?string $model = Withdrawal::class;

    protected static function rowsName(): string
    {
        return __('withdrawals');
    }

    protected static function columns(): array
    {
        return [
            ExportColumn::make('id')->label(__('ID')),
            static::eatTime('created_at', __('Requested (EAT)')),
            ExportColumn::make('user.phone')->label(__('Player phone')),
            ExportColumn::make('phone')->label(__('Paid to')),
            static::money('amount', __('Amount (KES)')),
            ExportColumn::make('status')->label(__('Status'))->formatStateUsing(fn (WithdrawalStatus $state): string => $state->label()),
            static::eatTime('reviewed_at', __('Reviewed (EAT)')),
            ExportColumn::make('review_note')->label(__('Review note')),
            static::eatTime('completed_at', __('Completed (EAT)')),
            ExportColumn::make('mpesa_receipt')->label(__('M-Pesa receipt')),
            ExportColumn::make('result_description')->label(__('M-Pesa result')),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with('user');
    }
}
