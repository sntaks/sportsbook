<?php

namespace App\Http\Resources\V1;

use App\Models\Sport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A sport with the competitions players can browse.
 *
 * @mixin Sport
 */
class SportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'competitions' => CompetitionResource::collection($this->whenLoaded('competitions')),
        ];
    }
}
