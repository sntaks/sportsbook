<?php

namespace App\Filament\Resources\Jackpots;

use App\Enums\JackpotStatus;
use App\Filament\Resources\Jackpots\Pages\CreateJackpot;
use App\Filament\Resources\Jackpots\Pages\EditJackpot;
use App\Filament\Resources\Jackpots\Pages\ListJackpots;
use App\Filament\Resources\Jackpots\Pages\ViewJackpot;
use App\Filament\Resources\Jackpots\RelationManagers\EntriesRelationManager;
use App\Filament\Resources\Jackpots\RelationManagers\MatchesRelationManager;
use App\Filament\Resources\Jackpots\Schemas\JackpotForm;
use App\Filament\Resources\Jackpots\Schemas\JackpotInfolist;
use App\Filament\Resources\Jackpots\Tables\JackpotsTable;
use App\Models\Jackpot;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Jackpots: drafting one from upcoming matches, publishing it, and following its entries through settlement.
 */
class JackpotResource extends Resource
{
    protected static ?string $model = Jackpot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|UnitEnum|null $navigationGroup = 'Betting';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return JackpotForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return JackpotInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JackpotsTable::configure($table);
    }

    /**
     * Only a draft can be changed; once published, the matches and prizes are what players entered on.
     */
    public static function canEdit(Model $record): bool
    {
        return $record instanceof Jackpot && $record->status === JackpotStatus::Draft && parent::canEdit($record);
    }

    public static function getRelations(): array
    {
        return [
            MatchesRelationManager::class,
            EntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJackpots::route('/'),
            'create' => CreateJackpot::route('/create'),
            'view' => ViewJackpot::route('/{record}'),
            'edit' => EditJackpot::route('/{record}/edit'),
        ];
    }
}
