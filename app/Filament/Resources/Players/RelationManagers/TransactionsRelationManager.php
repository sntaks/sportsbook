<?php

namespace App\Filament\Resources\Players\RelationManagers;

use App\Enums\WalletTransactionType;
use App\Models\WalletTransaction;
use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The player's ledger, read-only.
 */
class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'walletTransactions';

    protected static ?string $title = 'Ledger';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('admin'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('When (EAT)'))
                    ->dateTime('j M Y, H:i')
                    ->timezone(config('sportsbook.timezone')),
                TextColumn::make('type')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (WalletTransactionType $state): string => $state->label())
                    ->description(fn (WalletTransaction $record): ?string => $record->description),
                TextColumn::make('amount')
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray'),
                TextColumn::make('balance_after')
                    ->label(__('Balance after'))
                    ->formatStateUsing(fn (int $state): string => Money::format($state)),
                TextColumn::make('admin.name')
                    ->label(__('By admin'))
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(collect(WalletTransactionType::cases())->mapWithKeys(fn (WalletTransactionType $type): array => [$type->value => $type->label()])->all()),
            ]);
    }
}
