<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListFixturesRequest;
use App\Http\Requests\Api\V1\SearchFixturesRequest;
use App\Http\Resources\V1\FixtureDetailResource;
use App\Http\Resources\V1\FixtureResource;
use App\Models\Competition;
use App\Models\Fixture;
use App\Sportsbook\Catalogue\FixtureListing;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Catalogue
 */
class FixtureController extends Controller
{
    /**
     * Upcoming matches.
     *
     * The same lists as the home page tabs, or one competition's matches. Each match carries the one market chosen
     * with `market` (1X2 by default) and a count of all its markets.
     *
     * @unauthenticated
     *
     * @throws ModelNotFoundException When `competition` is not a competition on offer.
     */
    public function index(ListFixturesRequest $request, FixtureListing $listing): AnonymousResourceCollection
    {
        $competition = null;

        if ($request->filled('competition')) {
            $competition = Competition::query()
                ->where('slug', $request->string('competition')->toString())
                ->where('is_synced', true)
                ->firstOrFail();
        }

        $tab = $competition === null ? $request->string('tab', 'highlights')->toString() : 'upcoming';

        return FixtureResource::collection($listing->forTab($tab, $request->string('market', 'h2h')->toString(), $competition));
    }

    /**
     * Matches in play.
     *
     * Kicked off and not yet finished. Betting on them is closed, so no markets are included.
     *
     * @unauthenticated
     */
    public function live(FixtureListing $listing): AnonymousResourceCollection
    {
        return FixtureResource::collection($listing->inPlay());
    }

    /**
     * Search matches.
     *
     * Upcoming matches whose team or competition name contains `q`.
     *
     * @unauthenticated
     */
    public function search(SearchFixturesRequest $request, FixtureListing $listing): AnonymousResourceCollection
    {
        return FixtureResource::collection($listing->search($request->string('q')->toString(), $request->string('market', 'h2h')->toString()));
    }

    /**
     * A match with all its markets.
     *
     * Markets are grouped into the same tabs as the match page: main, goals, correct score and half time. Empty
     * tabs are left out.
     *
     * @unauthenticated
     */
    public function show(Fixture $fixture): FixtureDetailResource
    {
        abort_unless($fixture->competition->is_synced, 404);

        return FixtureDetailResource::make($fixture->load('competition'));
    }
}
