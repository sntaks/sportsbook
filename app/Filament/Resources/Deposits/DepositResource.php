<?php

namespace App\Filament\Resources\Deposits;

use App\Enums\MpesaDepositStatus;
use App\Filament\Resources\Deposits\Pages\ManageDeposits;
use App\Filament\Resources\Deposits\Tables\DepositsTable;
use App\Models\MpesaDeposit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Every M-Pesa payment in. Unmatched and mismatched payments are settled here.
 */
class DepositResource extends Resource
{
    protected static ?string $model = MpesaDeposit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static string|UnitEnum|null $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'deposit';

    public static function getNavigationBadge(): ?string
    {
        $unsettled = MpesaDeposit::query()->whereIn('status', [MpesaDepositStatus::Mismatch, MpesaDepositStatus::Unmatched])->count();

        return $unsettled > 0 ? (string) $unsettled : null;
    }

    public static function table(Table $table): Table
    {
        return DepositsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDeposits::route('/'),
        ];
    }
}
