<?php

namespace App\Filament\Resources\Jackpots\Pages;

use App\Betting\Jackpot\DraftJackpot;
use App\Filament\CurrentAdmin;
use App\Filament\Resources\Jackpots\JackpotResource;
use App\Filament\Resources\Jackpots\Schemas\JackpotForm;
use App\Models\Jackpot;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditJackpot extends EditRecord
{
    protected static string $resource = JackpotResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return JackpotForm::fromJackpot($this->jackpot());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return JackpotForm::reportingErrors(fn () => app(DraftJackpot::class)->handle(JackpotForm::toDraft($data), $this->jackpot(), CurrentAdmin::get()));
    }

    protected function getRedirectUrl(): string
    {
        return JackpotResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    private function jackpot(): Jackpot
    {
        $record = $this->getRecord();

        abort_unless($record instanceof Jackpot, 404);

        return $record;
    }
}
