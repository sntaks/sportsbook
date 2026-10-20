<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\SportResource;
use App\Models\Sport;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Catalogue
 */
class SportController extends Controller
{
    /**
     * Sports and competitions.
     *
     * The sports with at least one competition on offer, each with its competitions and how many upcoming matches
     * they have. Same as the site's sidebar.
     *
     * @unauthenticated
     */
    public function index(): AnonymousResourceCollection
    {
        $sports = Sport::query()
            ->whereHas('competitions', fn ($competitions) => $competitions->synced())
            ->with(['competitions' => fn ($competitions) => $competitions->synced()->withCount('upcomingFixtures')->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return SportResource::collection($sports);
    }
}
