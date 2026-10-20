<?php

namespace App\Http\Resources\V1;

use App\Models\Bet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A placed bet. Amounts are in cents; odds are decimal strings.
 *
 * @mixin Bet
 */
class BetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'stake_cents' => $this->stake,
            /** Placed with a free bet: no money left the wallet, and a win pays only the winnings above the stake. */
            'is_free_bet' => $this->isFreeBet(),
            'total_odds' => $this->total_odds,
            /** Before tax, with the multibet bonus if every pick wins. */
            'potential_payout_cents' => $this->potential_payout,
            /** The multibet bonus the bet qualified for when placed; 0 for none. */
            'bonus_percent' => $this->bonus_percent,
            /** Cash paid, after tax. */
            'payout_cents' => $this->payout,
            /** Multibet bonus paid, before tax; included in `payout_cents`. */
            'bonus_cents' => $this->bonus,
            'odds_change_policy' => $this->odds_change_policy->value,
            'placed_at' => $this->placed_at->toIso8601String(),
            'settled_at' => $this->settled_at?->toIso8601String(),
            'legs' => BetLegResource::collection($this->whenLoaded('legs')),
        ];
    }
}
