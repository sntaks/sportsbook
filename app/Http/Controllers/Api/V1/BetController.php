<?php

namespace App\Http\Controllers\Api\V1;

use App\Betting\PlaceBet;
use App\Enums\BetStatus;
use App\Enums\OddsChangePolicy;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListBetsRequest;
use App\Http\Requests\Api\V1\PlaceBetRequest;
use App\Http\Resources\V1\BetResource;
use App\Models\Bet;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Bets
 */
class BetController extends Controller
{
    /**
     * My bets.
     *
     * Newest first, 20 a page. Filter with `status=open` or `status=settled` (anything no longer open);
     * leave it out for all bets.
     */
    public function index(ListBetsRequest $request): AnonymousResourceCollection
    {
        $bets = Bet::query()
            ->where('user_id', $request->user()->id)
            ->when($request->input('status') === 'open', fn ($query) => $query->where('status', BetStatus::Open))
            ->when($request->input('status') === 'settled', fn ($query) => $query->where('status', '!=', BetStatus::Open))
            ->with('legs')
            ->latest('placed_at')
            ->latest('id')
            ->paginate(20);

        return BetResource::collection($bets);
    }

    /**
     * Place a bet.
     *
     * One pick per match; more than one pick makes a multibet. Prices are checked again here: what happens when one
     * moved depends on `odds_change_policy` (the account's setting when left out). `slip_key` is any unique string
     * per slip, up to 64 characters: sending the same slip again returns the bet already placed with a 200 instead
     * of placing a second one.
     *
     * A refused bet is a 422 with a `reason` the app can act on: `not-allowed`, `empty-slip`, `unavailable`,
     * `price-changed`, `duplicate-match`, `stake` or `insufficient-funds`.
     */
    #[Response(status: 422, description: 'The slip is invalid (`errors`) or the bet was refused (`reason`).', type: 'array{message: string, reason?: string, errors?: array<string, list<string>>}')]
    public function store(PlaceBetRequest $request, PlaceBet $placeBet): JsonResponse
    {
        $bet = $placeBet->handle(
            $request->user(),
            $request->array('picks'),
            $request->input('stake'),
            $request->enum('odds_change_policy', OddsChangePolicy::class),
            $request->string('slip_key')->toString(),
        );

        $response = BetResource::make($bet->load('legs'))->response();

        if (! $bet->wasRecentlyCreated) {
            /** The slip was already placed: the bet placed then. */
            return $response;
        }

        return $response->setStatusCode(201);
    }

    /**
     * A bet.
     *
     * Found by its reference, the Bet ID shown to the player. Another player's bet is not found.
     *
     * @throws ModelNotFoundException
     */
    public function show(Request $request, string $reference): BetResource
    {
        $bet = Bet::query()
            ->where('user_id', $request->user()->id)
            ->where('reference', $reference)
            ->with('legs')
            ->firstOrFail();

        return BetResource::make($bet);
    }
}
