<?php

namespace App\Filament\Resources\Activities;

use App\Filament\Resources\Activities\Pages\ManageActivities;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

/**
 * Read-only view of the audit log. Nothing here can create, edit or delete a record.
 */
class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Access';

    protected static ?string $navigationLabel = 'Activity Log';

    protected static ?string $modelLabel = 'activity';

    protected static ?string $pluralModelLabel = 'activity log';

    protected static ?string $recordTitleAttribute = 'description';

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('created_at')
                    ->label(__('When'))
                    ->dateTime(),
                TextEntry::make('log_name')
                    ->label(__('Log'))
                    ->badge(),
                TextEntry::make('event'),
                TextEntry::make('description'),
                TextEntry::make('causer.name')
                    ->label(__('By'))
                    ->placeholder(__('System / anonymous')),
                TextEntry::make('subject_type')
                    ->label(__('Subject'))
                    ->formatStateUsing(fn (?string $state, Activity $record): string => class_basename((string) $state).' #'.$record->subject_id)
                    ->placeholder('—'),
                KeyValueEntry::make('properties')
                    ->state(fn (Activity $record): array => collect($record->properties ?? [])
                        ->map(fn (mixed $value): string => is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_THROW_ON_ERROR))
                        ->all())
                    ->columnSpanFull(),
                KeyValueEntry::make('attribute_changes')
                    ->label(__('Changes'))
                    ->state(fn (Activity $record): array => collect($record->attribute_changes ?? [])
                        ->map(fn (mixed $value): string => json_encode($value, JSON_THROW_ON_ERROR))
                        ->all())
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->modifyQueryUsing(fn ($query) => $query->with('causer'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('When'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('log_name')
                    ->label(__('Log'))
                    ->badge(),
                TextColumn::make('event')
                    ->searchable(),
                TextColumn::make('description')
                    ->searchable(),
                TextColumn::make('causer.name')
                    ->label(__('By'))
                    ->placeholder(__('System / anonymous')),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->label(__('Log'))
                    ->options(fn (): array => Activity::query()->distinct()->pluck('log_name', 'log_name')->filter()->all()),
                SelectFilter::make('event')
                    ->options(fn (): array => Activity::query()->distinct()->pluck('event', 'event')->filter()->all()),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageActivities::route('/'),
        ];
    }
}
