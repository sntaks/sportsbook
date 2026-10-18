<?php

namespace App\Filament\Resources\Bets\RelationManagers;

use App\Betting\Settlement\VoidLeg;
use App\Enums\BetLegStatus;
use App\Enums\BetStatus;
use App\Filament\CurrentAdmin;
use App\Filament\Resources\Bets\BetResource;
use App\Filament\Resources\Fixtures\FixtureResource;
use App\Models\Admin;
use App\Models\Bet;
use App\Models\BetLeg;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The bet's legs as they were placed, with voiding a leg priced in error.
 */
class LegsRelationManager extends RelationManager
{
    protected static string $relationship = 'legs';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->columns([
                TextColumn::make('fixture_name')
                    ->label(__('Match'))
                    ->description(fn (BetLeg $record): string => $record->kicks_off_at->timezone(config('sportsbook.timezone'))->format('D j M, H:i'))
                    ->url(fn (BetLeg $record): string => FixtureResource::getUrl('view', ['record' => $record->fixture_id])),
                TextColumn::make('market_name')
                    ->label(__('Market')),
                TextColumn::make('selection_name')
                    ->label(__('Pick')),
                TextColumn::make('price')
                    ->fontFamily('mono'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (BetLegStatus $state): string => $state->label())
                    ->color(fn (BetLegStatus $state): string => match ($state) {
                        BetLegStatus::Open => 'info',
                        BetLegStatus::Won => 'success',
                        BetLegStatus::Lost => 'danger',
                        BetLegStatus::Void => 'gray',
                    })
                    ->description(fn (BetLeg $record): ?string => $record->void_reason)
                    ->wrap(),
            ])
            ->recordActions([
                Action::make('voidLeg')
                    ->label(__('Void leg'))
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->authorize(fn (): bool => Filament::auth()->user()?->can('update', $this->bet()) ?? false)
                    ->visible(fn (BetLeg $record): bool => $record->void_reason === null && $this->bet()->status !== BetStatus::Cancelled)
                    ->modalDescription(__('For a pick priced in error. The leg counts as 1.00 and the bet settles again, taking back or paying the difference if it had already settled. It stays void even if the result is corrected later.'))
                    ->schema([Textarea::make('reason')->required()->maxLength(255)])
                    ->action(function (BetLeg $record, array $data): void {
                        CurrentAdmin::run(
                            fn (Admin $admin) => app(VoidLeg::class)->handle($record, $data['reason'], $admin),
                            __('Leg voided and the bet settled again'),
                        );

                        // The bet's status and payout are on the page around this table, so reload the whole page.
                        $this->redirect(BetResource::getUrl('view', ['record' => $this->bet()]), navigate: true);
                    }),
            ]);
    }

    private function bet(): Bet
    {
        $bet = $this->getOwnerRecord();

        abort_unless($bet instanceof Bet, 404);

        return $bet;
    }
}
