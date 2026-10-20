<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Pages\Reports as ReportsPage;
use App\Reports\Reports;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;

/**
 * Tax withheld by calendar month, counting only the days of each month inside the chosen range.
 */
class TaxByMonth extends TableWidget
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
            ->heading(__('Tax by month (EAT)'))
            ->description(__('A month the range only partly covers shows only its days inside the range.'))
            ->records(fn (): array => collect(app(Reports::class)->taxByMonth(ReportsPage::period($this->pageFilters)))->keyBy('month')->all())
            ->paginated(false)
            ->columns([
                TextColumn::make('month')->label(__('Month'))->formatStateUsing(fn (string $state): string => CarbonImmutable::createFromFormat('!Y-m', $state)->format('F Y')),
                TextColumn::make('deposit_tax')->label(__('On deposits'))->formatStateUsing($money)->alignEnd(),
                TextColumn::make('winnings_tax')->label(__('On winnings'))->formatStateUsing($money)->alignEnd(),
                TextColumn::make('total')->label(__('Total'))->formatStateUsing($money)->weight('bold')->alignEnd(),
            ]);
    }
}
