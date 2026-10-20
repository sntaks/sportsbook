<?php

namespace App\Http\Resources\V1;

use App\Models\JackpotEntry;
use App\Models\JackpotPick;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A player's jackpot entry with its picks. Amounts are in cents; `prize_cents` is what was paid after tax, or the
 * refund when the jackpot was cancelled.
 *
 * @mixin JackpotEntry
 */
class JackpotEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'jackpot' => $this->whenLoaded('jackpot', fn (): array => [
                'id' => $this->jackpot->id,
                'name' => $this->jackpot->name,
                'status' => $this->jackpot->status->value,
            ]),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'stake_cents' => $this->stake,
            'correct_count' => $this->correct_count,
            'prize_cents' => $this->prize,
            'placed_at' => $this->placed_at->toIso8601String(),
            'settled_at' => $this->settled_at?->toIso8601String(),
            /** In match order. `is_right` is null until the match has a result; a void match is right. */
            'picks' => $this->whenLoaded('picks', fn (): array => $this->picks
                ->sortBy(fn (JackpotPick $pick): int => $pick->jackpotFixture->position)
                ->map(fn (JackpotPick $pick): array => [
                    'match_id' => $pick->jackpot_fixture_id,
                    'position' => $pick->jackpotFixture->position,
                    'fixture_name' => $pick->jackpotFixture->fixture_name,
                    'pick' => $pick->pick,
                    'outcome' => $pick->jackpotFixture->outcome,
                    'is_void' => $pick->jackpotFixture->is_void,
                    'is_right' => $pick->jackpotFixture->judge($pick->pick),
                ])
                ->values()
                ->all()),
        ];
    }
}
