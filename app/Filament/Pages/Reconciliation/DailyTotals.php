<?php

namespace App\Filament\Pages\Reconciliation;

use App\Support\Money;
use App\Wallet\Reconciliation;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * M-Pesa totals beside the ledger's for the last 14 Nairobi days. A deposit difference is shown in red.
 */
class DailyTotals extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('M-Pesa against the ledger (last 14 days, EAT)'))
            ->description(__('Deposits must match exactly. Payouts differ by withdrawals still in flight or settled on another day.'))
            ->records(fn (): array => collect(app(Reconciliation::class)->days())->keyBy('date')->all())
            ->paginated(false)
            ->columns([
                TextColumn::make('date')->label(__('Day')),
                TextColumn::make('mpesa_deposits')
                    ->label(__('Deposits (M-Pesa)'))
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->alignEnd(),
                TextColumn::make('ledger_deposits')
                    ->label(__('Deposits (ledger)'))
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->color(fn (array $record): ?string => $record['mpesa_deposits'] !== $record['ledger_deposits'] ? 'danger' : null)
                    ->weight(fn (array $record): ?string => $record['mpesa_deposits'] !== $record['ledger_deposits'] ? 'bold' : null)
                    ->alignEnd(),
                TextColumn::make('mpesa_payouts')
                    ->label(__('Payouts paid (M-Pesa)'))
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->alignEnd(),
                TextColumn::make('ledger_payouts')
                    ->label(__('Withdrawn from wallets'))
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->alignEnd(),
            ]);
    }
}
