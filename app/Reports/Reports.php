<?php

namespace App\Reports;

use App\Enums\MarketType;
use App\Enums\WalletTransactionType;
use App\Models\Bet;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * The business figures behind the admin dashboard and Reports page, read through the reports database connection.
 *
 * Money figures come from the ledger and land on the EAT day an entry was posted: a corrected result counts on the
 * day it was corrected, not the day of the match. A settlement reversal is split back into what it reversed
 * (winnings, multibet bonus, tax, a void refund, a jackpot prize) so each figure stays right after a correction.
 *
 * - Sportsbook GGR: cash stakes less stakes returned and cash-bet winnings before tax.
 * - Jackpot GGR: entries less entries returned and prizes before tax.
 * - Promotion costs: multibet bonuses and free-bet winnings.
 * - NGR: both GGRs less promotion costs. Taxes are reported on their own and never enter GGR or NGR.
 * - Hold: sportsbook GGR as a percentage of sportsbook stakes.
 */
class Reports
{
    /**
     * Each ledger type the figures use: the figure it feeds and the sign that makes that figure positive.
     *
     * @var array<string, array{string, int}>
     */
    private const FIGURES = [
        'stake' => ['stakes', -1],
        'stake-refund' => ['refunds', 1],
        'winnings' => ['winnings', 1],
        'multibet-bonus' => ['bonuses', 1],
        'winnings-tax' => ['winnings_tax', -1],
        'deposit-tax' => ['deposit_tax', -1],
        'jackpot-entry' => ['jackpot_entries', -1],
        'jackpot-refund' => ['jackpot_refunds', 1],
        'jackpot-prize' => ['jackpot_prizes', 1],
        'deposit' => ['deposits', 1],
        'withdrawal' => ['withdrawals', -1],
        'withdrawal-refund' => ['withdrawals', -1],
    ];

    /** Free-bet winnings are a promotion cost, so they are kept apart from cash winnings. */
    private const FREE_BET_WINNINGS = 'free_bet_winnings';

    /**
     * Entries that make up a player's result on bets and jackpots.
     *
     * @var list<string>
     */
    private const BETTING_TYPES = [
        'stake', 'stake-refund', 'winnings', 'multibet-bonus', 'winnings-tax', 'settlement-reversal',
        'jackpot-entry', 'jackpot-refund', 'jackpot-prize',
    ];

    /**
     * The headline figures for a period.
     *
     * @return array{turnover: int, stakes: int, jackpot_entries: int, ggr: int, sportsbook_ggr: int, jackpot_ggr: int, promotion_costs: int, ngr: int, hold: ?float, deposits: int, withdrawals: int, taxes: int, active_players: int, sign_ups: int}
     */
    public function summary(Period $period): array
    {
        $figures = $this->derive(array_reduce(
            $this->ledgerByDay($period),
            function (array $total, array $day): array {
                foreach ($day as $figure => $amount) {
                    $total[$figure] = ($total[$figure] ?? 0) + $amount;
                }

                return $total;
            },
            [],
        ));

        return [
            'turnover' => $figures['stakes'] + $figures['jackpot_entries'],
            'stakes' => $figures['stakes'],
            'jackpot_entries' => $figures['jackpot_entries'],
            'ggr' => $figures['sportsbook_ggr'] + $figures['jackpot_ggr'],
            'sportsbook_ggr' => $figures['sportsbook_ggr'],
            'jackpot_ggr' => $figures['jackpot_ggr'],
            'promotion_costs' => $figures['promotion_costs'],
            'ngr' => $figures['ngr'],
            'hold' => $figures['hold'],
            'deposits' => $figures['deposits'],
            'withdrawals' => $figures['withdrawals'],
            'taxes' => $figures['deposit_tax'] + $figures['winnings_tax'],
            'active_players' => $this->activePlayers($period),
            'sign_ups' => $this->range($this->db()->table('users'), 'created_at', $period)->count(),
        ];
    }

    /**
     * Revenue for each day of the period, newest first.
     *
     * @return list<array{date: string, stakes: int, sportsbook_ggr: int, jackpot_entries: int, jackpot_ggr: int, promotion_costs: int, ngr: int, hold: ?float}>
     */
    public function revenueByDay(Period $period): array
    {
        return $this->eachDay($period, fn (array $figures): array => [
            'stakes' => $figures['stakes'],
            'sportsbook_ggr' => $figures['sportsbook_ggr'],
            'jackpot_entries' => $figures['jackpot_entries'],
            'jackpot_ggr' => $figures['jackpot_ggr'],
            'promotion_costs' => $figures['promotion_costs'],
            'ngr' => $figures['ngr'],
            'hold' => $figures['hold'],
        ]);
    }

    /**
     * The dashboard's figures for each day of the period, newest first, for its trend lines.
     *
     * @return list<array{date: string, turnover: int, ggr: int, ngr: int, promotion_costs: int, hold: ?float, deposits: int, withdrawals: int, active_players: int, sign_ups: int}>
     */
    public function dailyFigures(Period $period): array
    {
        $activePlayers = $this->activePlayersByDay($period);
        $day = $this->dayExpression('created_at', $period);
        $signUps = $this->range($this->db()->table('users'), 'created_at', $period)
            ->selectRaw("{$day} as day, count(*) as total")
            ->groupByRaw($day)
            ->pluck('total', 'day');

        return array_map(
            fn (array $figures): array => [
                ...$figures,
                'active_players' => (int) ($activePlayers[$figures['date']] ?? 0),
                'sign_ups' => (int) ($signUps[$figures['date']] ?? 0),
            ],
            $this->eachDay($period, fn (array $figures): array => [
                'turnover' => $figures['stakes'] + $figures['jackpot_entries'],
                'ggr' => $figures['sportsbook_ggr'] + $figures['jackpot_ggr'],
                'ngr' => $figures['ngr'],
                'promotion_costs' => $figures['promotion_costs'],
                'hold' => $figures['hold'],
                'deposits' => $figures['deposits'],
                'withdrawals' => $figures['withdrawals'],
            ]),
        );
    }

    /**
     * Money deposited and withdrawn each day, newest first. Withdrawals are what left wallets, less any returned.
     *
     * @return list<array{date: string, deposits: int, withdrawals: int, net: int}>
     */
    public function paymentsByDay(Period $period): array
    {
        return $this->eachDay($period, fn (array $figures): array => [
            'deposits' => $figures['deposits'],
            'withdrawals' => $figures['withdrawals'],
            'net' => $figures['deposits'] - $figures['withdrawals'],
        ]);
    }

    /**
     * Tax withheld each day, newest first. Winnings tax is net of any taken back by a corrected result.
     *
     * @return list<array{date: string, deposit_tax: int, winnings_tax: int, total: int}>
     */
    public function taxByDay(Period $period): array
    {
        return $this->eachDay($period, fn (array $figures): array => [
            'deposit_tax' => $figures['deposit_tax'],
            'winnings_tax' => $figures['winnings_tax'],
            'total' => $figures['deposit_tax'] + $figures['winnings_tax'],
        ]);
    }

    /**
     * Tax by calendar month, newest first, counting only the days of each month inside the period.
     *
     * @return list<array{month: string, deposit_tax: int, winnings_tax: int, total: int}>
     */
    public function taxByMonth(Period $period): array
    {
        return collect($this->taxByDay($period))
            ->groupBy(fn (array $day): string => substr($day['date'], 0, 7))
            ->map(fn ($days, string $month): array => [
                'month' => $month,
                'deposit_tax' => $days->sum('deposit_tax'),
                'winnings_tax' => $days->sum('winnings_tax'),
                'total' => $days->sum('total'),
            ])
            ->values()
            ->all();
    }

    /**
     * Cash stakes on bets placed in the period, by sport, competition or market, largest first. A multi's stake is
     * split equally across its legs, so a KES 300 treble adds KES 100 to each leg's row.
     *
     * @param  'sport'|'competition'|'market'  $dimension
     * @return list<array{label: string, bets: int, turnover: int, share: float}>
     */
    public function turnoverBy(string $dimension, Period $period): array
    {
        $query = $this->range($this->db()->table('bets'), 'bets.placed_at', $period)
            ->join('bet_legs', 'bet_legs.bet_id', '=', 'bets.id')
            ->whereNull('bets.free_bet_id')
            ->selectRaw('count(distinct bets.id) as bets')
            ->selectRaw('sum(bets.stake * 1.0 / (select count(*) from bet_legs as legs where legs.bet_id = bets.id)) as turnover')
            ->orderByDesc('turnover');

        match ($dimension) {
            'sport' => $query->join('fixtures', 'fixtures.id', '=', 'bet_legs.fixture_id')
                ->join('competitions', 'competitions.id', '=', 'fixtures.competition_id')
                ->join('sports', 'sports.id', '=', 'competitions.sport_id')
                ->addSelect('sports.name as label')
                ->groupBy('sports.id', 'sports.name'),
            'competition' => $query->join('fixtures', 'fixtures.id', '=', 'bet_legs.fixture_id')
                ->join('competitions', 'competitions.id', '=', 'fixtures.competition_id')
                ->addSelect('competitions.name as label')
                ->groupBy('competitions.id', 'competitions.name'),
            'market' => $query->addSelect('bet_legs.market_type as label')->groupBy('bet_legs.market_type'),
            default => throw new InvalidArgumentException("Unknown turnover breakdown [{$dimension}]."),
        };

        $rows = $query->get();
        $total = max(1, (int) round((float) $rows->sum('turnover')));

        return $rows->map(fn (object $row): array => [
            'label' => $dimension === 'market' ? (MarketType::tryFrom((string) $row->label)?->label() ?? (string) $row->label) : (string) $row->label,
            'bets' => (int) $row->bets,
            'turnover' => $turnover = (int) round((float) $row->turnover),
            'share' => round($turnover / $total * 100, 1),
        ])->all();
    }

    /**
     * The players who won most (or lost most) on bets and jackpots in the period, after tax. Net is from the player's
     * side: positive means they took money from the book.
     *
     * @return list<array{user_id: int, name: ?string, phone: ?string, net: int, staked: int}>
     */
    public function topPlayers(Period $period, bool $winners, int $limit = 20): array
    {
        $rows = $this->range($this->db()->table('wallet_transactions'), 'wallet_transactions.created_at', $period)
            ->join('wallets', 'wallets.id', '=', 'wallet_transactions.wallet_id')
            ->whereIn('wallet_transactions.type', self::BETTING_TYPES)
            ->groupBy('wallets.user_id')
            ->select('wallets.user_id')
            ->selectRaw('sum(wallet_transactions.amount) as net')
            ->selectRaw('sum(case when wallet_transactions.type in (?, ?) then -wallet_transactions.amount else 0 end) as staked', [
                WalletTransactionType::Stake->value, WalletTransactionType::JackpotEntry->value,
            ])
            ->havingRaw($winners ? 'sum(wallet_transactions.amount) > 0' : 'sum(wallet_transactions.amount) < 0')
            ->orderBy('net', $winners ? 'desc' : 'asc')
            ->orderBy('wallets.user_id')
            ->limit($limit)
            ->get();

        $players = $this->db()->table('users')->whereIn('id', $rows->pluck('user_id'))->get(['id', 'name', 'phone'])->keyBy('id');

        return $rows->map(fn (object $row): array => [
            'user_id' => (int) $row->user_id,
            'name' => $players->get($row->user_id)?->name,
            'phone' => $players->get($row->user_id)?->phone,
            'net' => (int) $row->net,
            'staked' => (int) $row->staked,
        ])->all();
    }

    /**
     * One row per day of the period, newest first, shaped by the given callback from that day's figures.
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  $shape
     * @return list<array<string, mixed>>
     */
    private function eachDay(Period $period, callable $shape): array
    {
        $ledger = $this->ledgerByDay($period);

        return array_map(
            fn (string $date): array => ['date' => $date, ...$shape($this->derive($ledger[$date] ?? []))],
            $period->days(),
        );
    }

    /**
     * Every figure, with the totals worked out from the ledger sums.
     *
     * @param  array<string, int>  $sums
     * @return array<string, mixed>
     */
    private function derive(array $sums): array
    {
        $figures = array_fill_keys([...array_column(self::FIGURES, 0), self::FREE_BET_WINNINGS], 0);

        foreach ($sums as $figure => $amount) {
            $figures[$figure] += $amount;
        }

        $figures['sportsbook_ggr'] = $figures['stakes'] - $figures['refunds'] - $figures['winnings'];
        $figures['jackpot_ggr'] = $figures['jackpot_entries'] - $figures['jackpot_refunds'] - $figures['jackpot_prizes'];
        $figures['promotion_costs'] = $figures['bonuses'] + $figures[self::FREE_BET_WINNINGS];
        $figures['ngr'] = $figures['sportsbook_ggr'] + $figures['jackpot_ggr'] - $figures['promotion_costs'];
        $figures['hold'] = $figures['stakes'] > 0 ? round($figures['sportsbook_ggr'] / $figures['stakes'] * 100, 1) : null;

        return $figures;
    }

    /**
     * Ledger sums by EAT day (Y-m-d) and figure, with settlement reversals split into what they reversed.
     *
     * @return array<string, array<string, int>>
     */
    private function ledgerByDay(Period $period): array
    {
        $day = $this->dayExpression('wallet_transactions.created_at', $period);
        $byDay = [];

        $rows = $this->withFreeBetFlag($this->range($this->db()->table('wallet_transactions'), 'wallet_transactions.created_at', $period))
            ->whereIn('wallet_transactions.type', array_keys(self::FIGURES))
            ->selectRaw("{$day} as day")
            ->selectRaw('sum(wallet_transactions.amount) as total')
            ->groupByRaw("{$day}, wallet_transactions.type, bets.free_bet_id is not null")
            ->get();

        foreach ($rows as $row) {
            $this->add($byDay, (string) $row->day, (string) $row->entry_type, (bool) $row->is_free_bet, (int) $row->total);
        }

        foreach ($this->reversals($period) as $reversal) {
            $this->add($byDay, $reversal['day'], $reversal['type'], $reversal['is_free_bet'], $reversal['amount']);
        }

        return $byDay;
    }

    /**
     * Each settlement reversal in the period as the entries it undid, negated, on the day it was posted. A reversal
     * undoes one settlement version of a bet ("bet-reversal:{bet}:{version}") or a jackpot entry
     * ("jackpot-reversal:{entry}:{version}"); what the matching settlement entries do not explain counts as winnings
     * or a prize taken back.
     *
     * @return list<array{day: string, type: string, is_free_bet: bool, amount: int}>
     */
    private function reversals(Period $period): array
    {
        $reversals = $this->range($this->db()->table('wallet_transactions'), 'created_at', $period)
            ->where('type', WalletTransactionType::SettlementReversal->value)
            ->get(['idempotency_key', 'amount', 'created_at']);

        if ($reversals->isEmpty()) {
            return [];
        }

        $settledKeys = $reversals->mapWithKeys(fn (object $reversal): array => [$reversal->idempotency_key => $this->settlementKeys((string) $reversal->idempotency_key)]);
        $settled = $this->withFreeBetFlag($this->db()->table('wallet_transactions'))
            ->whereIn('wallet_transactions.idempotency_key', $settledKeys->flatten())
            ->addSelect('wallet_transactions.idempotency_key', 'wallet_transactions.amount')
            ->get()
            ->keyBy('idempotency_key');
        $timezone = (string) config('sportsbook.timezone');
        $parts = [];

        foreach ($reversals as $reversal) {
            $day = CarbonImmutable::parse($reversal->created_at, 'UTC')->setTimezone($timezone)->toDateString();
            $unexplained = (int) $reversal->amount;

            foreach ($settledKeys[$reversal->idempotency_key] as $key) {
                if ($entry = $settled->get($key)) {
                    $parts[] = ['day' => $day, 'type' => (string) $entry->entry_type, 'is_free_bet' => (bool) $entry->is_free_bet, 'amount' => -(int) $entry->amount];
                    $unexplained += (int) $entry->amount;
                }
            }

            if ($unexplained !== 0) {
                $type = str_starts_with((string) $reversal->idempotency_key, 'jackpot-') ? WalletTransactionType::JackpotPrize : WalletTransactionType::Winnings;
                $parts[] = ['day' => $day, 'type' => $type->value, 'is_free_bet' => false, 'amount' => $unexplained];
            }
        }

        return $parts;
    }

    /**
     * The idempotency keys of the settlement entries a reversal undid.
     *
     * @return list<string>
     */
    private function settlementKeys(string $reversalKey): array
    {
        if (preg_match('/^bet-reversal:(\d+):(\d+)$/', $reversalKey, $match)) {
            return ["bet-payout:{$match[1]}:{$match[2]}", "bet-bonus:{$match[1]}:{$match[2]}", "bet-winnings-tax:{$match[1]}:{$match[2]}"];
        }

        if (preg_match('/^jackpot-reversal:(\d+):(\d+)$/', $reversalKey, $match)) {
            return ["jackpot-prize:{$match[1]}:{$match[2]}", "jackpot-winnings-tax:{$match[1]}:{$match[2]}"];
        }

        return [];
    }

    /**
     * @param  array<string, array<string, int>>  $byDay
     */
    private function add(array &$byDay, string $day, string $type, bool $isFreeBet, int $amount): void
    {
        if (! isset(self::FIGURES[$type])) {
            return;
        }

        [$figure, $sign] = self::FIGURES[$type];
        $figure = $type === WalletTransactionType::Winnings->value && $isFreeBet ? self::FREE_BET_WINNINGS : $figure;
        $byDay[$day][$figure] = ($byDay[$day][$figure] ?? 0) + $sign * $amount;
    }

    /**
     * Ledger entries with their type and whether they came from a free bet.
     */
    private function withFreeBetFlag(Builder $query): Builder
    {
        return $query
            ->leftJoin('bets', function ($join): void {
                $join->on('bets.id', '=', 'wallet_transactions.source_id')
                    ->where('wallet_transactions.source_type', (new Bet)->getMorphClass());
            })
            ->select('wallet_transactions.type as entry_type')
            ->selectRaw('bets.free_bet_id is not null as is_free_bet');
    }

    /**
     * Players who placed a bet or entered a jackpot in the period, free bets included.
     */
    private function activePlayers(Period $period): int
    {
        $bettors = $this->range($this->db()->table('bets'), 'placed_at', $period)->select('user_id');
        $entrants = $this->range($this->db()->table('jackpot_entries'), 'placed_at', $period)->select('user_id');

        return $this->db()->query()->fromSub($bettors->union($entrants), 'players')->count();
    }

    /**
     * Players who placed a bet or entered a jackpot, counted per EAT day.
     *
     * @return array<string, int>
     */
    private function activePlayersByDay(Period $period): array
    {
        $bettors = $this->range($this->db()->table('bets'), 'placed_at', $period)
            ->selectRaw($this->dayExpression('placed_at', $period).' as day, user_id');
        $entrants = $this->range($this->db()->table('jackpot_entries'), 'placed_at', $period)
            ->selectRaw($this->dayExpression('placed_at', $period).' as day, user_id');

        return $this->db()->query()
            ->fromSub($bettors->union($entrants), 'players')
            ->selectRaw('day, count(distinct user_id) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();
    }

    private function range(Builder $query, string $column, Period $period): Builder
    {
        return $query->where($column, '>=', $period->start())->where($column, '<', $period->end());
    }

    /**
     * SQL for the EAT calendar day (Y-m-d) of a UTC timestamp column.
     */
    private function dayExpression(string $column, Period $period): string
    {
        $minutes = $period->utcOffsetMinutes();

        return match ($this->db()->getDriverName()) {
            'mysql', 'mariadb' => "date(date_add({$column}, interval {$minutes} minute))",
            'sqlite' => sprintf("date(%s, '%+d minutes')", $column, $minutes),
            default => throw new LogicException('Reports support MySQL and SQLite only.'),
        };
    }

    private function db(): Connection
    {
        return DB::connection(config('sportsbook.reports.connection'));
    }
}
