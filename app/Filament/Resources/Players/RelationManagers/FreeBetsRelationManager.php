<?php

namespace App\Filament\Resources\Players\RelationManagers;

use App\Enums\FreeBetSource;
use App\Enums\FreeBetStatus;
use App\Filament\CurrentAdmin;
use App\Filament\Resources\Bets\BetResource;
use App\Models\Admin;
use App\Models\FreeBet;
use App\Promotions\VoidFreeBet;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The player's free bets: given by staff, or replacements for free bets whose bet ended void or was cancelled.
 */
class FreeBetsRelationManager extends RelationManager
{
    protected static string $relationship = 'freeBets';

    protected static ?string $title = 'Free bets';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['admin', 'bet']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Given (EAT)'))
                    ->dateTime('j M Y, H:i')
                    ->timezone(config('sportsbook.timezone'))
                    ->sortable(),
                TextColumn::make('amount')
                    ->formatStateUsing(fn (int $state): string => Money::format($state)),
                TextColumn::make('min_odds')
                    ->label(__('Min odds'))
                    ->fontFamily('mono'),
                TextColumn::make('expires_at')
                    ->label(__('Use by (EAT)'))
                    ->dateTime('j M Y, H:i')
                    ->timezone(config('sportsbook.timezone')),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (FreeBet $record): string => $record->stateLabel())
                    ->color(fn (FreeBet $record): string => match ($record->state()) {
                        'available' => 'success',
                        'used' => 'info',
                        default => 'gray',
                    })
                    ->description(fn (FreeBet $record): ?string => $record->void_reason),
                TextColumn::make('source')
                    ->formatStateUsing(fn (FreeBetSource $state): string => $state->label())
                    ->description(fn (FreeBet $record): ?string => $record->admin?->name ?? $record->note),
                TextColumn::make('bet.reference')
                    ->label(__('Used on'))
                    ->fontFamily('mono')
                    ->url(fn (FreeBet $record): ?string => $record->bet === null ? null : BetResource::getUrl('view', ['record' => $record->bet]))
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('void')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->authorize(fn (FreeBet $record): bool => Filament::auth()->user()?->can('update', $record) ?? false)
                    ->visible(fn (FreeBet $record): bool => $record->status === FreeBetStatus::Available)
                    ->modalDescription(__('The player can no longer use this free bet.'))
                    ->schema([Textarea::make('reason')->required()->maxLength(255)])
                    ->action(fn (FreeBet $record, array $data) => CurrentAdmin::run(
                        fn (Admin $admin) => app(VoidFreeBet::class)->handle($record, $admin, $data['reason']),
                        __('Free bet voided'),
                    )),
            ]);
    }
}
