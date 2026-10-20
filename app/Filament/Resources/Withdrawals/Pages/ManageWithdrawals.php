<?php

namespace App\Filament\Resources\Withdrawals\Pages;

use App\Filament\Actions\ExportActions;
use App\Filament\Exports\WithdrawalExporter;
use App\Filament\Resources\Withdrawals\WithdrawalResource;
use Filament\Resources\Pages\ManageRecords;

class ManageWithdrawals extends ManageRecords
{
    protected static string $resource = WithdrawalResource::class;

    protected function getHeaderActions(): array
    {
        return [ExportActions::make(WithdrawalExporter::class, __('Export withdrawals'))];
    }
}
