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
 * Money into and out of wallets for each day of the chosen range.
 */
class PaymentsByDay extends TableWidget
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
            ->heading(__('Payments by day (EAT)'))
            ->description(__('Deposits credited, and money taken out of wallets for withdrawals less any returned. The Reconciliation page checks these against M-Pesa.'))
            ->records(fn (): array => collect(app(Reports::class)->paymentsByDay(ReportsPage::period($this->pageFilters)))->keyBy('date')->all())
            ->paginated([31, 'all'])
            ->columns([
                TextColumn::make('date')->label(__('Day'))->date('D j M Y'),
                TextColumn::make('deposits')->label(__('Deposits'))->formatStateUsing($money)->alignEnd(),
                TextColumn::make('withdrawals')->label(__('Withdrawals'))->formatStateUsing($money)->alignEnd(),
                TextColumn::make('net')->label(__('Net in'))->formatStateUsing($money)->color(fn (int $state): ?string => $state < 0 ? 'danger' : null)->alignEnd(),
            ]);
    }
}
