<?php

namespace App\Filament\Resources\Players\RelationManagers;

use App\Filament\Resources\Bets\BetResource;
use App\Filament\Resources\Bets\Tables\BetsTable;
use App\Models\Bet;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class BetsRelationManager extends RelationManager
{
    protected static string $relationship = 'bets';

    public function table(Table $table): Table
    {
        return BetsTable::configure($table, showPlayer: false)
            ->recordActions([ViewAction::make()->url(fn (Bet $record): string => BetResource::getUrl('view', ['record' => $record]))]);
    }
}
