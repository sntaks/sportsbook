<?php

namespace App\Filament\Resources\Withdrawals;

use App\Enums\WithdrawalStatus;
use App\Filament\Resources\Withdrawals\Pages\ManageWithdrawals;
use App\Filament\Resources\Withdrawals\Tables\WithdrawalsTable;
use App\Models\Withdrawal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The withdrawals queue: approvals, rejections, and payouts M-Pesa never answered for.
 */
class WithdrawalResource extends Resource
{
    protected static ?string $model = Withdrawal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $waiting = Withdrawal::query()->where('status', WithdrawalStatus::AwaitingApproval)->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function table(Table $table): Table
    {
        return WithdrawalsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWithdrawals::route('/'),
        ];
    }
}
