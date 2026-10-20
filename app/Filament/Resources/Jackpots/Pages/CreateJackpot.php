<?php

namespace App\Filament\Resources\Jackpots\Pages;

use App\Betting\Jackpot\DraftJackpot;
use App\Filament\CurrentAdmin;
use App\Filament\Resources\Jackpots\JackpotResource;
use App\Filament\Resources\Jackpots\Schemas\JackpotForm;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateJackpot extends CreateRecord
{
    protected static string $resource = JackpotResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return JackpotForm::reportingErrors(fn () => app(DraftJackpot::class)->handle(JackpotForm::toDraft($data), admin: CurrentAdmin::get()));
    }

    protected function getRedirectUrl(): string
    {
        return JackpotResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
