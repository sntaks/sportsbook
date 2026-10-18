<?php

namespace App\Filament\Resources\Bets;

use App\Filament\Resources\Bets\Pages\ListBets;
use App\Filament\Resources\Bets\Pages\ViewBet;
use App\Filament\Resources\Bets\RelationManagers\LegsRelationManager;
use App\Filament\Resources\Bets\Schemas\BetInfolist;
use App\Filament\Resources\Bets\Tables\BetsTable;
use App\Models\Bet;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Placed bets: looking one up for a player, cancelling one placed in error, or voiding a leg priced in error.
 */
class BetResource extends Resource
{
    protected static ?string $model = Bet::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'Betting';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function infolist(Schema $schema): Schema
    {
        return BetInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BetsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LegsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBets::route('/'),
            'view' => ViewBet::route('/{record}'),
        ];
    }
}
