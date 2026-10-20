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
 * Tax withheld on deposits and winnings for each day of the chosen range.
 */
class TaxByDay extends TableWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    /** Loaded with the page: a lazy widget in a hidden tab would keep the dates it was first given. */
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return ReportsPage::canAccess();
    }

    public function table(Table $table): Table
    {
        $money = fn (int $state): string => Money::format($state);

        return $table
            ->heading(__('Tax by day (EAT)'))
            ->description(__('Winnings tax is net of tax given back when a result was corrected.'))
            ->records(fn (): array => collect(app(Reports::class)->taxByDay(ReportsPage::period($this->pageFilters)))->keyBy('date')->all())
            ->paginated([31, 'all'])
            ->columns([
                TextColumn::make('date')->label(__('Day'))->date('D j M Y'),
                TextColumn::make('deposit_tax')->label(__('On deposits'))->formatStateUsing($money)->alignEnd(),
                TextColumn::make('winnings_tax')->label(__('On winnings'))->formatStateUsing($money)->alignEnd(),
                TextColumn::make('total')->label(__('Total'))->formatStateUsing($money)->weight('bold')->alignEnd(),
            ]);
    }
}
