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
     * @unauthenticated
     */
    public function quote(QuoteSlipRequest $request, BettableSelections $bettableSelections): JsonResponse
    {
        $quote = BetSlipQuote::for($request->array('picks'), $request->string('stake')->toString(), $bettableSelections);

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
            /** Possible win in cents, capped at the maximum payout. */
            'possible_win_cents' => (int) round($quote->possibleWin * 100),
            'is_payout_capped' => $quote->isPayoutCapped,
            /** Selection id => current price, for each line whose price moved. @var array<string, string> */
            'changed_prices' => (object) $quote->changedPrices(),
            'bettable_count' => $quote->bettableCount(),
        ]);
    }
}
