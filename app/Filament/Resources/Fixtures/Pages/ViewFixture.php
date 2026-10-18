<?php

namespace App\Filament\Resources\Fixtures\Pages;

use App\Betting\Settlement\RecordResult;
use App\Filament\CurrentAdmin;
use App\Filament\Resources\Fixtures\FixtureResource;
use App\Models\Admin;
use App\Models\Fixture;
use App\Models\FixtureResult;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;

class ViewFixture extends ViewRecord
{
    protected static string $resource = FixtureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('enterResult')
                ->label(fn (): string => $this->fixture()->result === null ? __('Enter result') : __('Correct result'))
                ->icon(Heroicon::OutlinedFlag)
                ->authorize(fn (): bool => $this->canSettle())
                ->visible(fn (): bool => $this->fixture()->kicks_off_at->isPast())
                ->modalDescription(fn (): string => $this->fixture()->result === null
                    ? __('Bets on this match settle as soon as you save. The half-time score is only needed for Half-time/Full-time bets.')
                    : __('Bets already settled on this match are reopened, anything they paid is taken back, and they settle again on the new score.'))
                ->fillForm(fn (): array => $this->fixture()->result?->only(['home_score', 'away_score', 'half_time_home_score', 'half_time_away_score']) ?? [])
                ->schema([
                    Grid::make(2)->schema([
                        $this->scoreInput('home_score', __('Full time: :team', ['team' => $this->fixture()->home_team]))->required(),
                        $this->scoreInput('away_score', __('Full time: :team', ['team' => $this->fixture()->away_team]))->required(),
                        $this->scoreInput('half_time_home_score', __('Half time: :team', ['team' => $this->fixture()->home_team]))
                            ->requiredWith('half_time_away_score')
                            ->lte('home_score'),
                        $this->scoreInput('half_time_away_score', __('Half time: :team', ['team' => $this->fixture()->away_team]))
                            ->requiredWith('half_time_home_score')
                            ->lte('away_score'),
                    ]),
                    Textarea::make('note')
                        ->label(fn (): string => $this->fixture()->result === null ? __('Note') : __('Why is the result being corrected?'))
                        ->required(fn (): bool => $this->fixture()->result !== null)
                        ->maxLength(255),
                ])
                ->action(fn (array $data) => CurrentAdmin::run(
                    fn (Admin $admin) => app(RecordResult::class)->handle($this->fixture(), [
                        'home_score' => (int) $data['home_score'],
                        'away_score' => (int) $data['away_score'],
                        'half_time_home_score' => filled($data['half_time_home_score'] ?? null) ? (int) $data['half_time_home_score'] : null,
                        'half_time_away_score' => filled($data['half_time_away_score'] ?? null) ? (int) $data['half_time_away_score'] : null,
                        'note' => $data['note'] ?? null,
                    ], FixtureResult::SOURCE_ADMIN, $admin),
                    __('Result saved and bets settled'),
                ))
                ->after(fn () => $this->showLatestResult()),
            Action::make('voidMatch')
                ->label(__('Void match'))
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('danger')
                ->authorize(fn (): bool => $this->canSettle())
                ->visible(fn (): bool => ! ($this->fixture()->result->is_void ?? false))
                ->modalDescription(__('For a match that was abandoned or never played. Every bet leg on it counts as 1.00, singles get their stake back, and betting on it stops.'))
                ->schema([Textarea::make('reason')->required()->maxLength(255)])
                ->action(fn (array $data) => CurrentAdmin::run(
                    fn (Admin $admin) => app(RecordResult::class)->handle($this->fixture(), ['is_void' => true, 'note' => $data['reason']], FixtureResult::SOURCE_ADMIN, $admin),
                    __('Match voided and bets settled'),
                ))
                ->after(fn () => $this->showLatestResult()),
        ];
    }

    private function scoreInput(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->integer()
            ->minValue(0)
            ->maxValue(99);
    }

    private function fixture(): Fixture
    {
        $record = $this->getRecord();

        abort_unless($record instanceof Fixture, 404);

        return $record->loadMissing('result');
    }

    /**
     * Reload the fixture and redraw the whole page, so the infolist (which Filament otherwise leaves as it was after
     * an action) shows the new result and status.
     */
    private function showLatestResult(): void
    {
        $this->fixture()->refresh();
        $this->forceRender();
    }

    private function canSettle(): bool
    {
        return Filament::auth()->user()?->can('settle', $this->fixture()) ?? false;
    }
}
