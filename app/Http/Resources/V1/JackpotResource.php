<?php

namespace App\Http\Resources\V1;

use App\Enums\JackpotStatus;
use App\Models\Jackpot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A jackpot with its prize tiers and matches. Amounts are in cents.
 *
 * @mixin Jackpot
 */
class JackpotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $closesAt = $this->closesAt();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status->value,
            'entry_price_cents' => $this->entry_price,
            'closes_at' => $closesAt?->toIso8601String(),
            'is_taking_entries' => $this->status === JackpotStatus::Open && ($closesAt?->isFuture() ?? false),
            'void_limit' => $this->void_limit,
            /** Most matches right first. */
            'prize_tiers' => collect($this->prize_tiers)
                ->map(fn (array $tier): array => ['correct' => (int) $tier['correct'], 'pool_cents' => (int) $tier['pool']])
                ->values()
                ->all(),
            'matches' => JackpotMatchResource::collection($this->whenLoaded('jackpotFixtures')),
        ];
    }
}
