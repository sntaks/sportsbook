<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListFreeBetsRequest;
use App\Http\Resources\V1\FreeBetResource;
use App\Models\FreeBet;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Free bets
 */
class FreeBetController extends Controller
{
    /**
     * My free bets.
     *
     * Newest first, 20 a page. `status=usable` lists only those that can be bet with now, soonest to expire first.
     * Bet with one by sending its `id` as `free_bet_id` when placing a bet.
     */
    public function index(ListFreeBetsRequest $request): AnonymousResourceCollection
    {
        $freeBets = FreeBet::query()
            ->where('user_id', $request->user()->id)
            ->when(
                $request->input('status') === 'usable',
                fn ($query) => $query->usable(),
                fn ($query) => $query->latest('id'),
            )
            ->with('bet')
            ->paginate(20);

        return FreeBetResource::collection($freeBets);
    }
}
