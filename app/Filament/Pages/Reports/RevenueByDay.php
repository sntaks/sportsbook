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
 * Revenue for each day of the chosen range, sportsbook and jackpots apart.
 */
class RevenueByDay extends TableWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    /** Loaded with the page: a lazy widget in a hidden tab would keep the dates it was first given. */
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return ReportsPage::canAccess();
    }

    public function table(Table $table): Table
    {
        $money = fn (int $state): string => Money::format($state);

        return $table
            ->heading(__('Revenue by day (EAT)'))
            ->description(__('A corrected result counts on the day it was corrected. Promotion costs are multibet bonuses and free-bet winnings.'))
            ->records(fn (): array => collect(app(Reports::class)->revenueByDay(ReportsPage::period($this->pageFilters)))->keyBy('date')->all())
            ->paginated([31, 'all'])
            ->columns([
                TextColumn::make('date')->label(__('Day'))->date('D j M Y'),
                TextColumn::make('stakes')->label(__('Bet stakes'))->formatStateUsing($money)->alignEnd(),
                TextColumn::make('sportsbook_ggr')->label(__('Sportsbook GGR'))->formatStateUsing($money)->color(fn (int $state): ?string => $state < 0 ? 'danger' : null)->alignEnd(),
                TextColumn::make('hold')->label(__('Hold'))->formatStateUsing(fn (?float $state): string => $state === null ? '—' : number_format($state, 1).'%')->placeholder('—')->alignEnd(),
                TextColumn::make('jackpot_entries')->label(__('Jackpot entries'))->formatStateUsing($money)->alignEnd(),
                TextColumn::make('jackpot_ggr')->label(__('Jackpot GGR'))->formatStateUsing($money)->color(fn (int $state): ?string => $state < 0 ? 'danger' : null)->alignEnd(),
                TextColumn::make('promotion_costs')->label(__('Promotion costs'))->formatStateUsing($money)->alignEnd(),
                TextColumn::make('ngr')->label(__('NGR'))->formatStateUsing($money)->color(fn (int $state): ?string => $state < 0 ? 'danger' : null)->weight('bold')->alignEnd(),
            ]);
    }
}
