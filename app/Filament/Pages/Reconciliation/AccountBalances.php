<?php

namespace App\Filament\Pages\Reconciliation;

use App\Enums\MpesaBalanceReportStatus;
use App\Models\MpesaBalanceReport;
use App\Wallet\Mpesa\BalanceReports;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * The latest answer M-Pesa gave for each shortcode's balance, one row per account it reported.
 */
class AccountBalances extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('M-Pesa account balances'))
            ->description(__('Use the buttons at the top to ask M-Pesa; the answer arrives within a minute or so.'))
            ->records(fn (): array => $this->rows())
            ->paginated(false)
            ->columns([
                TextColumn::make('shortcode')->label(__('Shortcode')),
                TextColumn::make('account')->label(__('Account')),
                TextColumn::make('available')->label(__('Available'))->alignEnd(),
                TextColumn::make('as_of')->label(__('As of (EAT)')),
            ]);
    }

    /**
     * @return array<string, array{shortcode: string, account: string, available: string, as_of: string}>
     */
    private function rows(): array
    {
        $rows = [];

        foreach (BalanceReports::ACCOUNTS as $account) {
            $shortcode = $account === 'b2c' ? __('B2C (payouts)') : __('Paybill (deposits)');
            $report = MpesaBalanceReport::query()->where('account', $account)->latest('id')->first();

            if ($report === null || $report->status !== MpesaBalanceReportStatus::Received) {
                $rows[$account] = [
                    'shortcode' => $shortcode,
                    'account' => match ($report?->status) {
                        null => __('Not checked yet'),
                        MpesaBalanceReportStatus::Requested => __('Waiting for M-Pesa'),
                        default => (string) $report->result_description,
                    },
                    'available' => '—',
                    'as_of' => $report?->created_at?->timezone(config('sportsbook.timezone'))->format('j M, H:i') ?? '—',
                ];

                continue;
            }

            foreach ($report->balances ?? [] as $index => $balance) {
                $rows["{$account}-{$index}"] = [
                    'shortcode' => $shortcode,
                    'account' => $balance['name'],
                    'available' => $balance['currency'].' '.number_format($balance['available'], 2),
                    'as_of' => $report->received_at?->timezone(config('sportsbook.timezone'))->format('j M, H:i') ?? '—',
                ];
            }
        }

        return $rows;
    }
}
