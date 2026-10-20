<?php

namespace App\Reports;

use App\Models\Admin;
use App\Support\Money;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\WriterInterface;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The tax report as a file to download straight away: tax withheld by EAT day, oldest first, then each month's
 * total (only the days inside the range). Small enough not to need the export queue.
 */
class TaxReportFile
{
    public function __construct(private Reports $reports) {}

    /**
     * @param  'csv'|'xlsx'  $format
     */
    public function download(Period $period, string $format, Admin $admin): StreamedResponse
    {
        $writer = match ($format) {
            'csv' => new CsvWriter,
            'xlsx' => new XlsxWriter,
            default => throw new InvalidArgumentException("Unknown tax report format [{$format}]."),
        };
        $rows = $this->rows($period, numbersAsText: $format === 'csv');

        activity('exports')
            ->causedBy($admin)
            ->event('export')
            ->withProperties([
                'exporter' => 'TaxReport',
                'rows' => count($rows) - 1,
                'filters' => ['from' => $period->firstDay->toDateString(), 'until' => $period->lastDay->toDateString()],
            ])
            ->log('Tax report downloaded');

        return response()->streamDownload(
            fn () => $this->write($writer, $rows),
            "tax-report-{$period->firstDay->toDateString()}-to-{$period->lastDay->toDateString()}.{$format}",
        );
    }

    /**
     * A header row, then one row per day and one per month.
     *
     * @return list<list<string|float>>
     */
    public function rows(Period $period, bool $numbersAsText = true): array
    {
        $amount = fn (int $cents): string|float => $numbersAsText ? Money::decimal($cents) : (float) Money::decimal($cents);
        $row = fn (string $kind, string $label, array $tax): array => [$kind, $label, $amount($tax['deposit_tax']), $amount($tax['winnings_tax']), $amount($tax['total'])];

        return [
            [__('Kind'), __('Period (EAT)'), __('Tax on deposits (KES)'), __('Tax on winnings (KES)'), __('Total (KES)')],
            ...array_map(fn (array $day): array => $row(__('Day'), $day['date'], $day), array_reverse($this->reports->taxByDay($period))),
            ...array_map(fn (array $month): array => $row(__('Month'), $month['month'], $month), array_reverse($this->reports->taxByMonth($period))),
        ];
    }

    /**
     * @param  list<list<string|float>>  $rows
     */
    private function write(WriterInterface $writer, array $rows): void
    {
        $writer->openToFile('php://output');

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();
    }
}
