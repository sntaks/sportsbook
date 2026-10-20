<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Pages\Reports as ReportsPage;
use App\Filament\Resources\Players\PlayerResource;
use App\Reports\Reports;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;

/**
 * The 20 players who won most, or lost most, on bets and jackpots in the chosen range, after tax.
 */
class TopPlayers extends TableWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    /** Loaded with the page: a lazy widget in a hidden tab would keep the dates it was first given. */
    protected static bool $isLazy = false;

    public bool $winners = true;

    public static function canView(): bool
    {
        return ReportsPage::canAccess();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading($this->winners ? __('Top 20 winners') : __('Top 20 losers'))
            ->description(__('Net result on bets and jackpots after tax, from the player\'s side.'))
            ->records(fn (): array => collect(app(Reports::class)->topPlayers(ReportsPage::period($this->pageFilters), $this->winners))->keyBy('user_id')->all())
            ->paginated(false)
            ->recordUrl(fn (array $record): ?string => PlayerResource::canViewAny() ? PlayerResource::getUrl('view', ['record' => $record['user_id']]) : null)
            ->emptyStateHeading($this->winners ? __('No player came out ahead') : __('No player came out behind'))
            ->columns([
                TextColumn::make('phone')
                    ->label(__('Player'))
                    ->placeholder(fn (array $record): string => __('Player #:id', ['id' => $record['user_id']]))
                    ->description(fn (array $record): ?string => $record['name']),
                TextColumn::make('net')
                    ->label(__('Net result'))
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->color($this->winners ? 'danger' : 'success')
                    ->alignEnd(),
                TextColumn::make('staked')
                    ->label(__('Staked'))
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->alignEnd(),
            ]);
    }
}
