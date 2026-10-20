<?php

namespace App\Filament\Resources\Bets\Pages;

use App\Filament\Actions\ExportActions;
use App\Filament\Exports\BetExporter;
use App\Filament\Resources\Bets\BetResource;
use Filament\Resources\Pages\ListRecords;

class ListBets extends ListRecords
{
    protected static string $resource = BetResource::class;

    protected function getHeaderActions(): array
    {
        return [ExportActions::make(BetExporter::class, __('Export bets'))];
    }
}
