<?php

namespace App\Http\Resources\V1;

use App\Models\Competition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A competition players can browse. Fixture lists are filtered by its slug.
 *
 * @mixin Competition
 */
class CompetitionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'upcoming_fixtures_count' => $this->whenCounted('upcomingFixtures'),
        ];
    }
}
