<?php

namespace App\Filament\Exports;

use App\Enums\WalletTransactionType;
use App\Models\WalletTransaction;
use Filament\Actions\Exports\ExportColumn;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ledger entries for the days chosen on the Reports page, oldest first.
 */
class WalletTransactionExporter extends SpreadsheetExporter
{
    protected static ?string $model = WalletTransaction::class;

    protected static function rowsName(): string
    {
        return __('ledger entries');
    }

    protected static function columns(): array
    {
        return [
            ExportColumn::make('id')->label(__('ID')),
            static::eatTime('created_at', __('Posted (EAT)')),
            ExportColumn::make('wallet.user.phone')->label(__('Player phone')),
            ExportColumn::make('type')->label(__('Type'))->formatStateUsing(fn (WalletTransactionType $state): string => $state->label()),
            static::money('amount', __('Amount (KES)')),
            static::money('balance_after', __('Balance after (KES)')),
            ExportColumn::make('description')->label(__('Description')),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with('wallet.user')->orderBy('id');
    }
}
