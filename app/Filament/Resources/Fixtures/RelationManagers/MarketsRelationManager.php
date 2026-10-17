<?php

namespace App\Filament\Resources\Fixtures\RelationManagers;

use App\Enums\MarketStatus;
use App\Models\Admin;
use App\Models\Market;
use App\Models\OddsSnapshot;
use App\Models\Selection;
use App\Sportsbook\Trading\MarketCannotBeReopened;
use App\Sportsbook\Trading\OverrideSelectionPrices;
use App\Sportsbook\Trading\ReopenMarket;
use App\Sportsbook\Trading\SuspendMarket;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * A fixture's markets with the trading actions: suspend, reopen, override prices and price history.
 */
class MarketsRelationManager extends RelationManager
{
    protected static string $relationship = 'markets';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn (Market $record): string => $record->name())
            ->modifyQueryUsing(fn (Builder $query) => $query->with('selections')->orderByRaw('line is not null')->orderBy('line'))
            ->paginated(false)
            ->columns([
                TextColumn::make('market')
                    ->state(fn (Market $record): string => $record->name()),
                TextColumn::make('prices')
                    ->state(fn (Market $record): array => $record->selections
                        ->map(fn (Selection $selection): string => "{$selection->name}: ".($selection->price() ?? '—')
                            .($selection->hasOverride() ? ' ('.__('override').')' : ''))
                        ->all())
                    ->listWithLineBreaks(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (MarketStatus $state): string => $state === MarketStatus::Open ? 'success' : 'danger')
                    ->formatStateUsing(fn (MarketStatus $state): string => ucfirst($state->value))
                    ->description(fn (Market $record): ?string => $record->suspension_reason?->label()),
                TextColumn::make('bookmaker_count')
                    ->label(__('Bookmakers'))
                    ->numeric(),
                TextColumn::make('priced_at')
                    ->label(__('Priced'))
                    ->since()
                    ->placeholder(__('Never')),
            ])
            ->recordActions([
                Action::make('suspend')
                    ->icon(Heroicon::OutlinedPause)
                    ->color('danger')
                    ->authorize(fn (Market $record): bool => $this->canTrade($record))
                    ->visible(fn (Market $record): bool => $record->isOpen())
                    ->schema([
                        Textarea::make('reason')->required()->maxLength(255),
                    ])
                    ->action(function (Market $record, array $data): void {
                        app(SuspendMarket::class)->handle($record, $this->admin(), $data['reason']);
                        Notification::make()->title(__('Market suspended'))->success()->send();
                    }),
                Action::make('reopen')
                    ->icon(Heroicon::OutlinedPlay)
                    ->color('success')
                    ->requiresConfirmation()
                    ->authorize(fn (Market $record): bool => $this->canTrade($record))
                    ->visible(fn (Market $record): bool => ! $record->isOpen())
                    ->action(function (Market $record): void {
                        try {
                            app(ReopenMarket::class)->handle($record, $this->admin());
                            Notification::make()->title(__('Market reopened'))->success()->send();
                        } catch (MarketCannotBeReopened $exception) {
                            Notification::make()->title(__('Cannot reopen'))->body($exception->getMessage())->danger()->send();
                        }
                    }),
                Action::make('overridePrices')
                    ->label(__('Override prices'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->authorize(fn (Market $record): bool => $this->canTrade($record))
                    ->visible(fn (Market $record): bool => $record->selections->isNotEmpty())
                    ->fillForm(fn (Market $record): array => [
                        'prices' => $record->selections->mapWithKeys(fn (Selection $selection): array => [$selection->id => $selection->override_price])->all(),
                    ])
                    ->schema(fn (Market $record): array => [
                        ...$record->selections->map(fn (Selection $selection): TextInput => TextInput::make("prices.{$selection->id}")
                            ->label($selection->name)
                            ->helperText(__('Feed price: :price. Leave blank to use it.', ['price' => $selection->feed_price ?? '—']))
                            ->numeric()
                            ->minValue(OverrideSelectionPrices::MINIMUM_PRICE)
                            ->maxValue(OverrideSelectionPrices::MAXIMUM_PRICE)
                            ->step(0.01))->all(),
                        Textarea::make('reason')->required()->maxLength(255),
                    ])
                    ->action(function (Market $record, array $data): void {
                        $prices = [];

                        foreach (is_array($data['prices'] ?? null) ? $data['prices'] : [] as $selectionId => $price) {
                            $prices[(int) $selectionId] = is_numeric($price) ? (string) $price : null;
                        }

                        app(OverrideSelectionPrices::class)->handle($record, $this->admin(), $prices, $data['reason']);
                        Notification::make()->title(__('Prices updated'))->success()->send();
                    }),
                Action::make('history')
                    ->icon(Heroicon::OutlinedClock)
                    ->color('gray')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close'))
                    ->modalContent(fn (Market $record) => view('filament.markets.price-history', [
                        'snapshots' => OddsSnapshot::query()
                            ->whereIn('selection_id', $record->selections->modelKeys())
                            ->with('selection')
                            ->latest('captured_at')
                            ->latest('id')
                            ->limit(60)
                            ->get(),
                    ])),
            ]);
    }

    private function canTrade(Market $market): bool
    {
        return Filament::auth()->user()?->can('update', $market) ?? false;
    }

    private function admin(): Admin
    {
        $admin = Filament::auth()->user();

        abort_unless($admin instanceof Admin, 403);

        return $admin;
    }
}
