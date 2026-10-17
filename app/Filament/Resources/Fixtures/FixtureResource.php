<?php

namespace App\Filament\Resources\Fixtures;

use App\Filament\Resources\Fixtures\Pages\ListFixtures;
use App\Filament\Resources\Fixtures\Pages\ViewFixture;
use App\Filament\Resources\Fixtures\RelationManagers\MarketsRelationManager;
use App\Filament\Resources\Fixtures\Schemas\FixtureInfolist;
use App\Filament\Resources\Fixtures\Tables\FixturesTable;
use App\Models\Fixture;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Read-only view of fixtures from the feed, with trading actions on each fixture's markets.
 */
class FixtureResource extends Resource
{
    protected static ?string $model = Fixture::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Trading';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'home_team';

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record instanceof Fixture ? $record->name() : parent::getRecordTitle($record);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FixtureInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FixturesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            MarketsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFixtures::route('/'),
            'view' => ViewFixture::route('/{record}'),
        ];
    }
}
