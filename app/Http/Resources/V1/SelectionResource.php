<?php

namespace App\Http\Resources\V1;

use App\Models\Selection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One outcome of a market. price is a decimal string, or null while it has none. movement is up, down or null.
 *
 * @mixin Selection
 */
class SelectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'outcome' => $this->outcome,
            'name' => $this->name,
            'short_label' => $this->shortLabel(),
            'price' => $this->price(),
            'movement' => $this->recentMovement(),
        ];
    }
}
