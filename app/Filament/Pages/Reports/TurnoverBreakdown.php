<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Pages\Reports as ReportsPage;
use App\Reports\Reports;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;

/**
 * Cash stakes on bets placed in the chosen range by sport, competition or market. A multi's stake is split equally
 * across its legs.
 */
class TurnoverBreakdown extends TableWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    /** Loaded with the page: a lazy widget in a hidden tab would keep the dates it was first given. */
    protected static bool $isLazy = false;

    /** @var 'sport'|'competition'|'market' */
    public string $dimension = 'sport';

    public static function canView(): bool
    {
        return ReportsPage::canAccess();
    }

    public function getColumnSpan(): int|string|array
    {
        return $this->dimension === 'competition' ? 'full' : 1;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(match ($this->dimension) {
                'competition' => __('Turnover by competition'),
                'market' => __('Turnover by market'),
                default => __('Turnover by sport'),
            })
            ->description(__('Cash bets placed in the range. A multi\'s stake is split equally across its legs.'))
            ->records(fn (): array => collect(app(Reports::class)->turnoverBy($this->dimension, ReportsPage::period($this->pageFilters)))->keyBy('label')->all())
            ->paginated([25, 'all'])
            ->emptyStateHeading(__('No bets placed in this range'))
            ->columns([
                TextColumn::make('label')->label(match ($this->dimension) {
                    'competition' => __('Competition'),
                    'market' => __('Market'),
                    default => __('Sport'),
                }),
                TextColumn::make('bets')->label(__('Bets'))->numeric()->alignEnd(),
                TextColumn::make('turnover')->label(__('Turnover'))->formatStateUsing(fn (int $state): string => Money::format($state))->alignEnd(),
                TextColumn::make('share')->label(__('Share'))->formatStateUsing(fn (float $state): string => number_format($state, 1).'%')->alignEnd(),
            ]);
    }
}
