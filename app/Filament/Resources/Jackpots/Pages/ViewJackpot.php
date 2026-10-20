<?php

namespace App\Filament\Resources\Jackpots\Pages;

use App\Betting\Jackpot\CancelJackpot;
use App\Betting\Jackpot\PublishJackpot;
use App\Betting\Jackpot\SettleJackpot;
use App\Enums\JackpotStatus;
use App\Filament\CurrentAdmin;
use App\Filament\Resources\Jackpots\JackpotResource;
use App\Models\Admin;
use App\Models\Jackpot;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewJackpot extends ViewRecord
{
    protected static string $resource = JackpotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (): bool => $this->jackpot()->status === JackpotStatus::Draft),
            Action::make('publish')
                ->label(__('Publish'))
                ->icon(Heroicon::OutlinedMegaphone)
                ->color('success')
                ->authorize(fn (): bool => $this->canManage())
                ->visible(fn (): bool => $this->jackpot()->status === JackpotStatus::Draft)
                ->requiresConfirmation()
                ->modalDescription(fn (): string => __('Players can enter at :price until the first match kicks off. The matches and prizes cannot be changed after this.', ['price' => Money::format($this->jackpot()->entry_price)]))
                ->action(fn () => CurrentAdmin::run(
                    fn (Admin $admin) => app(PublishJackpot::class)->handle($this->jackpot(), $admin),
                    __('Jackpot published and taking entries'),
                ))
                ->after(fn () => $this->showLatest()),
            Action::make('settle')
                ->label(__('Settle now'))
                ->icon(Heroicon::OutlinedCheckBadge)
                ->authorize(fn (): bool => $this->canManage())
                ->visible(fn (): bool => $this->jackpot()->status === JackpotStatus::Open && $this->jackpot()->jackpotFixtures()->get()->every->isDecided())
                ->requiresConfirmation()
                ->modalDescription(__('Every match has a result, so prizes are paid now. A result corrected later reopens the jackpot and settles it again.'))
                ->action(fn () => CurrentAdmin::run(
                    fn () => app(SettleJackpot::class)->handle($this->jackpot()),
                    __('Jackpot settled'),
                ))
                ->after(fn () => $this->showLatest()),
            Action::make('cancel')
                ->label(__('Cancel jackpot'))
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->authorize(fn (): bool => $this->canManage())
                ->visible(fn (): bool => in_array($this->jackpot()->status, [JackpotStatus::Draft, JackpotStatus::Open], true))
                ->modalDescription(__('Every entry gets its price back and the jackpot closes. This cannot be undone.'))
                ->schema([Textarea::make('reason')->required()->maxLength(255)])
                ->action(fn (array $data) => CurrentAdmin::run(
                    fn (Admin $admin) => app(CancelJackpot::class)->handle($this->jackpot(), $data['reason'], $admin),
                    __('Jackpot cancelled and every entry refunded'),
                ))
                ->after(fn () => $this->showLatest()),
        ];
    }

    private function jackpot(): Jackpot
    {
        $record = $this->getRecord();

        abort_unless($record instanceof Jackpot, 404);

        return $record;
    }

    /**
     * Reload the jackpot and redraw the page and its tables with the new status.
     */
    private function showLatest(): void
    {
        $this->redirect(JackpotResource::getUrl('view', ['record' => $this->jackpot()]), navigate: true);
    }

    private function canManage(): bool
    {
        return Filament::auth()->user()?->can('update', $this->jackpot()) ?? false;
    }
}
