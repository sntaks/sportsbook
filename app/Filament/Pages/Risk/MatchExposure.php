<?php

namespace App\Filament\Pages\Risk;

use App\Betting\Exposure;
use App\Filament\Resources\Fixtures\FixtureResource;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Open bets by match, the riskiest first, with what each pick would cost if it won.
 */
class MatchExposure extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Open bets by match'))
            ->description(__('Matches still waiting on a result. A pick\'s payout assumes every other leg of its multis wins, so it is the worst case.'))
            ->records(fn (): array => collect(app(Exposure::class)->byMatch())->keyBy('fixture_id')->all())
            ->paginated(false)
            ->emptyStateHeading(__('No open bets on any match'))
            ->columns([
                TextColumn::make('kicks_off_at')
                    ->label(__('Kick-off (EAT)'))
                    ->formatStateUsing(fn (CarbonImmutable $state): string => $state->timezone(config('sportsbook.timezone'))->format('D j M, H:i')),
                TextColumn::make('match')
                    ->description(fn (array $record): string => $record['competition'])
                    ->url(fn (array $record): string => FixtureResource::getUrl('view', ['record' => $record['fixture_id']])),
                TextColumn::make('bets')
                    ->alignEnd(),
                TextColumn::make('stakes')
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->alignEnd(),
                TextColumn::make('worst_payout')
                    ->label(__('Worst case'))
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->description(fn (array $record): string => $record['worst_pick'])
                    ->color(fn (array $record): ?string => $record['worst_payout'] > $record['stakes'] ? 'danger' : null)
                    ->weight('bold')
                    ->alignEnd(),
                TextColumn::make('picks')
                    ->label(__('By pick'))
                    ->state(fn (array $record): array => array_map(
                        fn (array $pick): string => trans_choice(':pick: :payout (:count bet)|:pick: :payout (:count bets)', $pick['bets'], ['pick' => $pick['pick'], 'payout' => Money::format($pick['payout'])]),
                        $record['picks'],
                    ))
                    ->listWithLineBreaks()
                    ->limitList(4)
                    ->expandableLimitedList()
                    ->size('sm')
                    ->color('gray'),
            ]);
    }
}
