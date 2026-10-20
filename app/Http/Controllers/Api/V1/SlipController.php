<?php

namespace App\Http\Controllers\Api\V1;

use App\Betting\BetSlipQuote;
use App\Betting\BettableSelections;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\QuoteSlipRequest;
use Illuminate\Http\JsonResponse;

/**
 * @tags Bet slip
 */
class SlipController extends Controller
{
    /**
     * Price a bet slip.
     *
     * Checks each pick against the current price and returns the combined odds and the possible win for `stake`.
     * Send the picks again whenever they change; prices sent by the app are never trusted. A line whose price moved
     * has status `price_changed` and appears in `changed_prices` with its current price. Only the first pick per
     * match counts.
     *
     * The possible win includes the multibet bonus the slip earns if every pick wins. To price a free bet, send its
     * amount as `stake` with `free_bet: true`: the possible win is then only the winnings above the stake, with no
     * bonus.
     *
     * @unauthenticated
     */
    public function quote(QuoteSlipRequest $request, BettableSelections $bettableSelections): JsonResponse
    {
        $quote = BetSlipQuote::for($request->array('picks'), $request->string('stake')->toString(), $bettableSelections, $request->boolean('free_bet'));
        $nextTier = $quote->nextBonusTier();

        return response()->json([
            'lines' => collect($quote->lines)->map(fn (array $line): array => [
                'selection_id' => $line['selection']->id,
                'fixture_id' => $line['selection']->market->fixture_id,
                'fixture_name' => $line['selection']->market->fixture->name(),
                'market_name' => $line['selection']->market->name(),
                'selection_name' => $line['selection']->name,
                'seen_price' => $line['seen_price'],
                /** The price now, or null when the selection cannot be bet on. */
                'price' => $line['status'] === 'unavailable' ? null : (string) $line['selection']->price(),
                /** @var 'ok'|'price_changed'|'unavailable' */
                'status' => $line['status'],
                'message' => $line['message'] === null ? null : __($line['message']),
            ])->all(),
            /** Combined odds of the lines that can be bet on, to two decimals. */
            'total_odds' => number_format($quote->totalOdds, 2, '.', ''),
            /** Why the stake cannot be used, if it cannot. */
            'stake_errors' => $quote->stakeErrors,
            /** Possible win in cents before tax, capped at the maximum payout, with the multibet bonus. */
            'possible_win_cents' => (int) round($quote->possibleWin * 100),
            'is_payout_capped' => $quote->isPayoutCapped,
            'multibet_bonus' => [
                /** Picks priced high enough to count towards the bonus. */
                'qualifying_picks' => $quote->qualifyingLegs,
                'percent' => $quote->bonusPercent,
                /** Included in `possible_win_cents`. */
                'amount_cents' => (int) round($quote->bonus * 100),
                'min_price' => (string) config('sportsbook.promotions.multibet_bonus.min_price'),
                /** The next tier: how many more qualifying picks reach it, and its percent. Null at the top or with a free bet. */
                'next_tier' => $nextTier === null ? null : ['more_picks' => (int) $nextTier['more'], 'percent' => (int) $nextTier['percent']],
            ],
            /** Selection id => current price, for each line whose price moved. @var array<string, string> */
            'changed_prices' => (object) $quote->changedPrices(),
            'bettable_count' => $quote->bettableCount(),
        ]);
    }
}
