<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\KeyFigures;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;

/**
 * The admin home page, with a period switch for the key figures shown to staff who may see reports.
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            ToggleButtons::make('period')
                ->hiddenLabel()
                ->options(array_map(fn (string $label): string => __($label), KeyFigures::PERIODS))
                ->default('today')
                ->inline()
                ->grouped()
                ->visible(fn (): bool => KeyFigures::canView()),
        ]);
    }
}
