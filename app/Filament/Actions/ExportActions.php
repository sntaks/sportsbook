<?php

namespace App\Filament\Actions;

use App\Enums\AdminPermission;
use App\Filament\Exports\SpreadsheetExporter;
use Filament\Actions\ExportAction;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Context;
use Livewire\Component;

/**
 * Queued CSV/Excel exports for admins who may export data. Rows follow the list's current filters; the filters in
 * use are kept for the activity log entry written when the export starts.
 */
class ExportActions
{
    /** Where the filters in use wait for the export's activity log entry, for this request only. */
    public const FILTERS_CONTEXT_KEY = 'export_filters';

    /**
     * @param  class-string<SpreadsheetExporter>  $exporter
     */
    public static function make(string $exporter, string $label): ExportAction
    {
        return ExportAction::make()
            ->exporter($exporter)
            ->label($label)
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->chunkSize(1000)
            ->visible(fn (): bool => self::canExport())
            ->before(fn (Component $livewire) => Context::addHidden(self::FILTERS_CONTEXT_KEY, self::filtersOf($livewire)));
    }

    public static function canExport(): bool
    {
        return Filament::auth()->user()?->can(AdminPermission::ExportData->value) ?? false;
    }

    /**
     * The filters set on the list or page, flattened, with empty ones left out.
     *
     * @return array<string, mixed>
     */
    public static function filtersOf(Component $livewire): array
    {
        $filters = $livewire instanceof HasTable ? $livewire->tableFilters : ($livewire->filters ?? []);

        return collect(Arr::dot($filters ?? []))->filter(fn (mixed $value): bool => filled($value))->all();
    }
}
