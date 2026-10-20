<?php

namespace App\Filament\Widgets;

use App\Enums\AdminPermission;
use App\Reports\Period;
use App\Reports\Reports;
use App\Support\Money;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * The business at a glance: today, the last 7 days and the last 30, each counted in whole EAT days up to today.
 */
class KeyFigures extends TableWidget
{
    protected static ?int $sort = -2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Filament::auth()->user()?->can(AdminPermission::ViewReports->value) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Key figures (EAT)'))
            ->description(__('Turnover is cash staked on bets and jackpots. GGR is before tax; NGR is GGR less multibet bonuses and free-bet winnings.'))
            ->records(fn (): array => $this->figures())
            ->paginated(false)
            ->columns([
                TextColumn::make('figure')->label(''),
                TextColumn::make('today')->label(__('Today'))->alignEnd(),
                TextColumn::make('week')->label(__('Last 7 days'))->alignEnd(),
                TextColumn::make('month')->label(__('Last 30 days'))->alignEnd(),
            ]);
    }

    /**
     * @return array<string, array{figure: string, today: string, week: string, month: string}>
     */
    private function figures(): array
    {
        $reports = app(Reports::class);
        $periods = [
            'today' => $reports->summary(Period::lastDays(1)),
            'week' => $reports->summary(Period::lastDays(7)),
            'month' => $reports->summary(Period::lastDays(30)),
        ];
        $money = fn (int $cents): string => Money::format($cents);
        $count = fn (int $count): string => number_format($count);
        $rows = [
            'turnover' => [__('Turnover'), $money],
            'ggr' => [__('GGR'), $money],
            'promotion_costs' => [__('Promotion costs'), $money],
            'ngr' => [__('NGR'), $money],
            'hold' => [__('Sportsbook hold'), fn (?float $hold): string => $hold === null ? '—' : number_format($hold, 1).'%'],
            'deposits' => [__('Deposits'), $money],
            'withdrawals' => [__('Withdrawals'), $money],
            'active_players' => [__('Active players'), $count],
            'sign_ups' => [__('New sign-ups'), $count],
        ];

        return collect($rows)
            ->map(fn (array $row, string $key): array => [
                'figure' => $row[0],
                ...collect($periods)->map(fn (array $summary): string => $row[1]($summary[$key]))->all(),
            ])
            ->all();
    }
}
