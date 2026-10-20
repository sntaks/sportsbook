<?php

namespace App\Http\Resources\V1;

use App\Models\BetLeg;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One pick on a bet, as it was when the bet was placed.
 *
 * @mixin BetLeg
 */
class BetLegResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'fixture_id' => $this->fixture_id,
            'fixture_name' => $this->fixture_name,
            'kicks_off_at' => $this->kicks_off_at->toIso8601String(),
            'market_type' => $this->market_type->value,
            'market_line' => $this->market_line,
            'market_name' => $this->market_name,
            'outcome' => $this->outcome,
            'selection_name' => $this->selection_name,
            'price' => $this->price,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
        ];
    }
}
