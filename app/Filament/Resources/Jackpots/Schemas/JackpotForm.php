<?php

namespace App\Filament\Resources\Jackpots\Schemas;

use App\Models\Fixture;
use App\Models\Jackpot;
use App\Support\Money;
use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * The draft jackpot form. Amounts are entered in whole shillings and stored in cents; new jackpots start from the
 * defaults in `sportsbook.jackpot`.
 */
class JackpotForm
{
    /** How far ahead matches are offered. */
    private const MATCH_DAYS_AHEAD = 14;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(100)
                    ->placeholder(__('Weekend Mega Jackpot')),
                TextInput::make('entry_price')
                    ->label(__('Entry price'))
                    ->prefix('KES')
                    ->integer()
                    ->minValue(1)
                    ->required()
                    ->default(fn (): int => (int) config('sportsbook.jackpot.entry_price')),
                TextInput::make('void_limit')
                    ->label(__('Void matches allowed'))
                    ->integer()
                    ->minValue(0)
                    ->maxValue(fn (): int => (int) config('sportsbook.jackpot.max_matches'))
                    ->required()
                    ->default(fn (): int => (int) config('sportsbook.jackpot.void_limit'))
                    ->helperText(__('A void match counts as right for everyone. More than this cancels the jackpot and refunds every entry.')),
                Select::make('fixture_ids')
                    ->label(__('Matches'))
                    ->multiple()
                    ->searchable()
                    ->required()
                    ->maxItems(fn (): int => (int) config('sportsbook.jackpot.max_matches'))
                    ->options(fn (): array => self::fixtureOptions())
                    ->helperText(fn (): string => __('Between :min and :max matches that have not kicked off. They are numbered in kick-off order, and entries close when the first one kicks off.', [
                        'min' => config('sportsbook.jackpot.min_matches'),
                        'max' => config('sportsbook.jackpot.max_matches'),
                    ]))
                    ->columnSpanFull(),
                Section::make(__('Prize tiers'))
                    ->description(__('Each tier\'s prize is split equally between the entries with exactly that many right. A prize nobody wins is not paid out.'))
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('prize_tiers')
                            ->hiddenLabel()
                            ->columns(2)
                            ->minItems(1)
                            ->reorderable(false)
                            ->addActionLabel(__('Add tier'))
                            ->default(fn (): array => self::defaultTiers())
                            ->schema([
                                TextInput::make('correct')
                                    ->label(__('Matches right'))
                                    ->integer()
                                    ->minValue(1)
                                    ->required()
                                    ->distinct(),
                                TextInput::make('pool')
                                    ->label(__('Prize'))
                                    ->prefix('KES')
                                    ->integer()
                                    ->minValue(1)
                                    ->required(),
                            ]),
                    ]),
            ]);
    }

    /**
     * The form's shillings as the cents DraftJackpot takes.
     *
     * @param  array<string, mixed>  $data
     * @return array{name: string, entry_price: int, void_limit: int, prize_tiers: list<array{correct: int, pool: int}>, fixture_ids: list<int>}
     */
    public static function toDraft(array $data): array
    {
        return [
            'name' => (string) $data['name'],
            'entry_price' => Money::fromShillings((int) $data['entry_price']),
            'void_limit' => (int) $data['void_limit'],
            'prize_tiers' => collect($data['prize_tiers'] ?? [])
                ->map(fn (array $tier): array => ['correct' => (int) $tier['correct'], 'pool' => Money::fromShillings((int) $tier['pool'])])
                ->values()
                ->all(),
            'fixture_ids' => array_values(array_map('intval', $data['fixture_ids'] ?? [])),
        ];
    }

    /**
     * A jackpot as the form shows it, in shillings.
     *
     * @return array<string, mixed>
     */
    public static function fromJackpot(Jackpot $jackpot): array
    {
        return [
            'name' => $jackpot->name,
            'entry_price' => Money::toShillings($jackpot->entry_price),
            'void_limit' => $jackpot->void_limit,
            'prize_tiers' => collect($jackpot->prize_tiers)
                ->map(fn (array $tier): array => ['correct' => $tier['correct'], 'pool' => Money::toShillings((int) $tier['pool'])])
                ->all(),
            'fixture_ids' => $jackpot->jackpotFixtures()->pluck('fixture_id')->all(),
        ];
    }

    /**
     * Run the save, showing a refusal from the action against the form field it is about.
     *
     * @template T
     *
     * @param  Closure(): T  $save
     * @return T
     *
     * @throws ValidationException
     */
    public static function reportingErrors(Closure $save): mixed
    {
        try {
            return $save();
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn (array $messages, string $field): array => ["data.{$field}" => $messages])->all());
        }
    }

    /**
     * Upcoming matches in synced competitions, labelled with kick-off (EAT), teams and competition.
     *
     * @return array<int, string>
     */
    private static function fixtureOptions(): array
    {
        return Fixture::query()
            ->upcoming()
            ->where('kicks_off_at', '<=', now()->addDays(self::MATCH_DAYS_AHEAD))
            ->whereHas('competition', fn (Builder $competitions) => $competitions->where('is_synced', true))
            ->with('competition')
            ->orderBy('kicks_off_at')
            ->get()
            ->mapWithKeys(fn (Fixture $fixture): array => [
                $fixture->id => $fixture->kicks_off_at->timezone(config('sportsbook.timezone'))->format('D j M H:i').' · '.$fixture->name().' ('.$fixture->competition->name.')',
            ])
            ->all();
    }

    /**
     * @return list<array{correct: int, pool: int}>
     */
    private static function defaultTiers(): array
    {
        return collect(config('sportsbook.jackpot.prize_tiers'))
            ->map(fn (int $pool, int $correct): array => ['correct' => $correct, 'pool' => $pool])
            ->values()
            ->all();
    }
}
