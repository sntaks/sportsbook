<?php

namespace App\Filament\Resources\Players\RelationManagers;

use App\Filament\Resources\Withdrawals\Tables\WithdrawalsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class WithdrawalsRelationManager extends RelationManager
{
    protected static string $relationship = 'withdrawals';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return WithdrawalsTable::configure($table, showPlayer: false);
    }
}
