<?php

namespace App\Http\Controllers\Api\V1;

use App\Betting\Jackpot\EnterJackpot;
use App\Enums\JackpotStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\EnterJackpotRequest;
use App\Http\Resources\V1\JackpotEntryResource;
use App\Http\Resources\V1\JackpotResource;
use App\Models\Jackpot;
use App\Models\JackpotEntry;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Jackpot
 */
class JackpotController extends Controller
{
    /**
     * The current jackpot.
     *
     * The open jackpot with its matches in kick-off order and its prize tiers. Each tier's pool is shared equally by
     * every entry with exactly that many right; a void match counts as right for everyone. Entries close at
     * `closes_at`, when the first match kicks off. A 404 means no jackpot is open.
     *
     * @unauthenticated
     *
     * @throws ModelNotFoundException
     */
    public function current(): JackpotResource
    {
        $jackpot = Jackpot::query()->current()->with('jackpotFixtures')->firstOrFail();

        return JackpotResource::make($jackpot);
    }

    /**
     * Enter a jackpot.
     *
     * One pick (`1`, `X` or `2`) for every match, by the match `id` from the jackpot. The entry price leaves the
     * wallet. `entry_key` is any unique string per entry, up to 64 characters: sending the same entry again returns
     * the entry already made with a 200 instead of charging twice. A player may enter the same jackpot more than once.
     *
     * A refused entry is a 422 with a `reason` the app can act on: `not-allowed`, `closed`, `picks` or
     * `insufficient-funds`.
     *
     * @throws ModelNotFoundException
     */
    #[Response(status: 422, description: 'The request is invalid (`errors`) or the entry was refused (`reason`).', type: 'array{message: string, reason?: string, errors?: array<string, list<string>>}')]
    public function enter(EnterJackpotRequest $request, int $jackpot, EnterJackpot $enterJackpot): JsonResponse
    {
        $jackpot = Jackpot::query()->whereKey($jackpot)->where('status', '!=', JackpotStatus::Draft)->firstOrFail();

        $entry = $enterJackpot->handle(
            $request->user(),
            $jackpot,
            $request->picksByMatch(),
            $request->string('entry_key')->toString(),
        );

        $response = JackpotEntryResource::make($entry->load(['jackpot', 'picks.jackpotFixture']))->response();

        if (! $entry->wasRecentlyCreated) {
            /** The entry was already made: the entry made then. */
            return $response;
        }

        return $response->setStatusCode(201);
    }

    /**
     * My jackpot entries.
     *
     * Newest first, 20 a page, each with its picks and how they did.
     */
    public function entries(Request $request): AnonymousResourceCollection
    {
        $entries = JackpotEntry::query()
            ->where('user_id', $request->user()->id)
            ->with(['jackpot', 'picks.jackpotFixture'])
            ->latest('placed_at')
            ->latest('id')
            ->paginate(20);

        return JackpotEntryResource::collection($entries);
    }
}
