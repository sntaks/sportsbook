<?php

namespace App\Filament\Pages;

use App\Enums\AdminPermission;
use App\Filament\Actions\ExportActions;
use App\Filament\CurrentAdmin;
use App\Filament\Exports\WalletTransactionExporter;
use App\Filament\Pages\Reports\PaymentsByDay;
use App\Filament\Pages\Reports\PeriodSummary;
use App\Filament\Pages\Reports\RevenueByDay;
use App\Filament\Pages\Reports\TaxByDay;
use App\Filament\Pages\Reports\TaxByMonth;
use App\Filament\Pages\Reports\TopPlayers;
use App\Filament\Pages\Reports\TurnoverBreakdown;
use App\Reports\Period;
use App\Reports\TaxReportFile;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Revenue, turnover, top players, payments and tax over a range of EAT days, read from the ledger.
 */
class Reports extends Dashboard
{
    use HasFiltersForm;

    protected static string $routePath = 'reports';

    protected static ?string $title = 'Reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->can(AdminPermission::ViewReports->value) ?? false;
    }

    /**
     * The days chosen in the page's filters.
     *
     * @param  array<string, mixed>|null  $filters
     */
    public static function period(?array $filters): Period
    {
        return Period::between($filters['from'] ?? null, $filters['until'] ?? null);
    }

    protected function getHeaderActions(): array
    {
        $dates = fn (): array => [
            'from' => self::period($this->filters)->firstDay->format('j M Y'),
            'until' => self::period($this->filters)->lastDay->format('j M Y'),
        ];

        return [
            ExportActions::make(WalletTransactionExporter::class, __('Export ledger'))
                ->modalDescription(fn (): string => __('Ledger entries posted from :from to :until (EAT).', $dates()))
                ->modifyQueryUsing(function (Builder $query): Builder {
                    $period = self::period($this->filters);

                    return $query->where('created_at', '>=', $period->start())->where('created_at', '<', $period->end());
                }),
            ActionGroup::make([
                Action::make('downloadTaxReportCsv')
                    ->label(__('CSV'))
                    ->visible(fn (): bool => ExportActions::canExport())
                    ->action(fn (): StreamedResponse => $this->downloadTaxReport('csv')),
                Action::make('downloadTaxReportXlsx')
                    ->label(__('Excel'))
                    ->visible(fn (): bool => ExportActions::canExport())
                    ->action(fn (): StreamedResponse => $this->downloadTaxReport('xlsx')),
            ])
                ->label(__('Download tax report'))
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->button()
                ->visible(fn (): bool => ExportActions::canExport()),
        ];
    }

    /**
     * @param  'csv'|'xlsx'  $format
     */
    private function downloadTaxReport(string $format): StreamedResponse
    {
        return app(TaxReportFile::class)->download(self::period($this->filters), $format, CurrentAdmin::get());
    }

    public function filtersForm(Schema $schema): Schema
    {
        $today = fn (): string => now(config('sportsbook.timezone'))->toDateString();

        return $schema->components([
            DatePicker::make('from')
                ->label(__('From (EAT)'))
                ->default(fn (): string => now(config('sportsbook.timezone'))->subDays(29)->toDateString())
                ->maxDate($today),
            DatePicker::make('until')
                ->label(__('Until (EAT)'))
                ->default($today)
                ->maxDate($today),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFiltersFormContentComponent(),
            ...$this->getWidgetsSchemaComponents([PeriodSummary::class]),
            Tabs::make(__('Reports'))
                ->persistTabInQueryString()
                ->tabs([
                    $this->tab(__('Revenue'), [RevenueByDay::class]),
                    $this->tab(__('Turnover'), [
                        TurnoverBreakdown::make(['dimension' => 'sport']),
                        TurnoverBreakdown::make(['dimension' => 'market']),
                        TurnoverBreakdown::make(['dimension' => 'competition']),
                    ]),
                    $this->tab(__('Top players'), [TopPlayers::make(['winners' => true]), TopPlayers::make(['winners' => false])]),
                    $this->tab(__('Payments'), [PaymentsByDay::class]),
                    $this->tab(__('Tax'), [TaxByDay::class, TaxByMonth::class]),
                ]),
        ]);
    }

    /**
     * @param  list<class-string<Widget>|WidgetConfiguration>  $widgets
     */
    private function tab(string $label, array $widgets): Tab
    {
        return Tab::make($label)->schema([
            Grid::make(['default' => 1, 'xl' => 2])->schema($this->getWidgetsSchemaComponents($widgets)),
        ]);
    }
}
