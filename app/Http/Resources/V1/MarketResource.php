<?php

namespace App\Http\Resources\V1;

use App\Models\Market;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A market and its selections. Only open markets can be bet on.
 *
 * @mixin Market
 */
class MarketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'type' => $this->type->value,
            'line' => $this->line,
            'name' => $this->name(),
            'is_open' => $this->isOpen(),
            'selections' => SelectionResource::collection($this->whenLoaded('selections')),
        ];
    }
}
