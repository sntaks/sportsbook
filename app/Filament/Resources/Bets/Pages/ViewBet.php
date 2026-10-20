<?php

namespace App\Filament\Resources\Bets\Pages;

use App\Betting\Settlement\CancelBet;
use App\Enums\BetStatus;
use App\Filament\CurrentAdmin;
use App\Filament\Resources\Bets\BetResource;
use App\Models\Admin;
use App\Models\Bet;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewBet extends ViewRecord
{
    protected static string $resource = BetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cancel')
                ->label(__('Cancel bet'))
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->authorize(fn (): bool => Filament::auth()->user()?->can('update', $this->bet()) ?? false)
                ->visible(fn (): bool => $this->bet()->status === BetStatus::Open)
                ->modalDescription(fn (): string => $this->bet()->isFreeBet()
                    ? __('The player gets a new :stake free bet and the bet closes as cancelled. Only for a bet placed in error.', ['stake' => Money::format($this->bet()->stake)])
                    : __('The :stake stake goes back to the player and the bet closes as cancelled. Only for a bet placed in error.', ['stake' => Money::format($this->bet()->stake)]))
                ->schema([Textarea::make('reason')->required()->maxLength(255)])
                ->action(fn (array $data) => CurrentAdmin::run(
                    fn (Admin $admin) => app(CancelBet::class)->handle($this->bet(), $admin, $data['reason']),
                    $this->bet()->isFreeBet() ? __('Bet cancelled; the player has a new free bet') : __('Bet cancelled; the stake is back in the player\'s wallet'),
                ))
                ->after(function (): void {
                    $this->bet()->refresh();
                    $this->forceRender();
                }),
        ];
    }

    private function bet(): Bet
    {
        $record = $this->getRecord();

        abort_unless($record instanceof Bet, 404);

        return $record;
    }
}
