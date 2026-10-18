<?php

namespace App\Filament\Pages;

use App\Enums\AdminPermission;
use App\Filament\CurrentAdmin;
use App\Filament\Pages\Reconciliation\AccountBalances;
use App\Filament\Pages\Reconciliation\DailyTotals;
use App\Filament\Pages\Reconciliation\NeedsAttention;
use App\Models\Admin;
use App\Wallet\Mpesa\BalanceReports;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Daily M-Pesa totals against the ledger, what needs a person, and the latest M-Pesa account balances.
 */
class Reconciliation extends Page
{
    protected string $view = 'filament.pages.reconciliation';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->can(AdminPermission::ReconcilePayments->value) ?? false;
    }

    protected function getHeaderActions(): array
    {
        return collect(BalanceReports::ACCOUNTS)
            ->map(fn (string $account): Action => Action::make("checkBalance-{$account}")
                ->label($account === 'b2c' ? __('Check B2C balance') : __('Check Paybill balance'))
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('gray')
                ->action(fn () => CurrentAdmin::run(
                    fn (Admin $admin) => app(BalanceReports::class)->request($account, $admin),
                    __('Balance check sent; the answer appears here when M-Pesa replies'),
                )))
            ->all();
    }

    protected function getHeaderWidgets(): array
    {
        return [NeedsAttention::class];
    }

    protected function getFooterWidgets(): array
    {
        return [DailyTotals::class, AccountBalances::class];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }
}
