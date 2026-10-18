<?php

namespace App\Filament\Resources\Players\RelationManagers;

use App\Filament\Resources\Deposits\Tables\DepositsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class DepositsRelationManager extends RelationManager
{
    protected static string $relationship = 'mpesaDeposits';

    protected static ?string $title = 'Deposits';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return DepositsTable::configure($table, showPlayer: false);
    }
}
