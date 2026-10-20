<?php

namespace App\Http\Resources\V1;

use App\Models\Fixture;
use App\Sportsbook\Catalogue\MarketTabs;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * A match with all its markets, grouped into the same tabs as the match page.
 *
 * @mixin Fixture
 */
class FixtureDetailResource extends FixtureResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'tabs' => MarketTabs::group($this->markets()->with('selections')->get())
                ->map(fn (Collection $markets, string $key): array => [
                    'key' => $key,
                    'label' => MarketTabs::label($key),
                    'markets' => MarketResource::collection($markets),
                ])
                ->values(),
        ];
    }
}
