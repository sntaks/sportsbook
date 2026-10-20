<?php

namespace App\Http\Resources\V1;

use App\Models\FreeBet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A free bet. Its whole amount is the stake on one bet with total odds of at least `min_odds`, placed before
 * `expires_at`; a win pays only the winnings above the stake.
 *
 * @mixin FreeBet
 */
class FreeBetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount_cents' => $this->amount,
            'min_odds' => $this->min_odds,
            'expires_at' => $this->expires_at->toIso8601String(),
            /** @var 'available'|'used'|'expired'|'void' */
            'status' => $this->state(),
            'status_label' => $this->stateLabel(),
            'is_usable' => $this->isUsable(),
            /** The bet it was used on. */
            'bet_reference' => $this->bet?->reference,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
