<?php

namespace App\Filament\Exports;

use App\Support\Money;
use Carbon\CarbonInterface;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

/**
 * Shared shape of the staff exports: amounts as plain numbers (1250.50) so spreadsheets can add them up, times in
 * EAT, files on the exports disk, and every column guarded against spreadsheet formula injection (player names and
 * M-Pesa texts are typed by others).
 */
abstract class SpreadsheetExporter extends Exporter
{
    /** What the rows are, for the finished notification: "bets", "deposits". */
    abstract protected static function rowsName(): string;

    /**
     * @return array<ExportColumn>
     */
    abstract protected static function columns(): array;

    public static function getColumns(): array
    {
        return array_map(fn (ExportColumn $column): ExportColumn => $column->preventFormulaInjection(), static::columns());
    }

    public static function money(string $name, string $label): ExportColumn
    {
        return ExportColumn::make($name)
            ->label($label)
            ->formatStateUsing(fn (int|string|null $state): ?string => $state === null ? null : Money::decimal((int) $state));
    }

    public static function eatTime(string $name, string $label): ExportColumn
    {
        return ExportColumn::make($name)
            ->label($label)
            ->formatStateUsing(fn (?CarbonInterface $state): ?string => $state?->setTimezone((string) config('sportsbook.timezone'))->format('Y-m-d H:i:s'));
    }

    public function getFileDisk(): string
    {
        return (string) config('sportsbook.exports.disk');
    }

    public static function getCompletedNotificationTitle(Export $export): string
    {
        return __('Export ready');
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = __(':count :rows exported. The file is kept for :days days.', [
            'count' => Number::format($export->successful_rows),
            'rows' => static::rowsName(),
            'days' => config('sportsbook.exports.retention_days'),
        ]);

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.__(':count rows failed to export.', ['count' => Number::format($failedRowsCount)]);
        }

        return $body;
    }
}
