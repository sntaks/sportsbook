<?php

namespace App\Filament\Resources\Players;

use App\Enums\UserStatus;
use App\Filament\Resources\Players\Pages\ListPlayers;
use App\Filament\Resources\Players\Pages\ViewPlayer;
use App\Filament\Resources\Players\RelationManagers\BetsRelationManager;
use App\Filament\Resources\Players\RelationManagers\DepositsRelationManager;
use App\Filament\Resources\Players\RelationManagers\TransactionsRelationManager;
use App\Filament\Resources\Players\RelationManagers\WithdrawalsRelationManager;
use App\Filament\Resources\Players\Schemas\PlayerInfolist;
use App\Models\User;
use App\Support\Money;
use App\Support\PhoneNumber;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Player accounts: who they are, their money, and the actions staff can take on them.
 */
class PlayerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'players';

    protected static ?string $modelLabel = 'player';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'phone';

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record instanceof User ? trim(self::identify($record).' '.$record->name) : parent::getRecordTitle($record);
    }

    /**
     * The player's number, or for accounts from before phone sign-up, their email or ID.
     */
    private static function identify(User $player): string
    {
        return $player->phone ?? $player->email ?? __('Player #:id', ['id' => $player->id]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PlayerInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('wallet'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('phone')
                    ->state(fn (User $record): string => self::identify($record))
                    ->description(fn (User $record): ?string => $record->name)
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                        ->where('phone', 'like', '%'.(PhoneNumber::normalize($search) ?? $search).'%')
                        ->orWhere('name', 'like', "%{$search}%"))),
                TextColumn::make('wallet.balance')
                    ->label(__('Balance'))
                    ->formatStateUsing(fn (?int $state): string => Money::format($state ?? 0))
                    ->placeholder(Money::format(0)),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (UserStatus $state): string => $state->label())
                    ->color(fn (UserStatus $state): string => $state === UserStatus::Active ? 'success' : 'danger')
                    ->description(fn (User $record) => $record->isExcluded()
                        ? __(':type until :date', ['type' => $record->exclusion_type?->label(), 'date' => $record->excluded_until?->timezone(config('sportsbook.timezone'))->format('j M Y')])
                        : null),
                TextColumn::make('created_at')
                    ->label(__('Joined'))
                    ->date('j M Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(UserStatus::cases())->mapWithKeys(fn (UserStatus $status): array => [$status->value => $status->label()])->all()),
                TernaryFilter::make('excluded')
                    ->label(__('On a break from betting'))
                    ->queries(
                        true: fn (Builder $query) => $query->where('excluded_until', '>', now()),
                        false: fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('excluded_until')->orWhere('excluded_until', '<=', now())),
                    ),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getRelations(): array
    {
        return [
            TransactionsRelationManager::class,
            BetsRelationManager::class,
            DepositsRelationManager::class,
            WithdrawalsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlayers::route('/'),
            'view' => ViewPlayer::route('/{record}'),
        ];
    }
}
