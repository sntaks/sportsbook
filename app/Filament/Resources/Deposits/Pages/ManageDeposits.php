<?php

namespace App\Filament\Resources\Deposits\Pages;

use App\Filament\Actions\ExportActions;
use App\Filament\Exports\MpesaDepositExporter;
use App\Filament\Resources\Deposits\DepositResource;
use Filament\Resources\Pages\ManageRecords;

class ManageDeposits extends ManageRecords
{
    protected static string $resource = DepositResource::class;

    protected function getHeaderActions(): array
    {
        return [ExportActions::make(MpesaDepositExporter::class, __('Export deposits'))];
    }
}
