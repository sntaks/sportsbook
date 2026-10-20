<?php

namespace App\Http\Resources\V1;

use App\Models\Fixture;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A match. In lists, market is the one market chosen for the list column, or null when the match does not have it.
 *
 * @mixin Fixture
 */
class FixtureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name(),
            'home_team' => $this->home_team,
            'away_team' => $this->away_team,
            'kicks_off_at' => $this->kicks_off_at->toIso8601String(),
            'status' => $this->status->value,
            'competition' => CompetitionResource::make($this->whenLoaded('competition')),
            'markets_count' => $this->whenCounted('markets'),
            'market' => $this->whenLoaded('markets', fn (): ?MarketResource => $this->markets->isEmpty() ? null : MarketResource::make($this->markets->first())),
        ];
    }
}
