<?php

namespace App\Http\Resources\V1;

use App\Models\JackpotFixture;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One match in a jackpot. `id` is what a pick refers to; `outcome` is 1, X or 2 once there is a result.
 *
 * @mixin JackpotFixture
 */
class JackpotMatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'fixture_id' => $this->fixture_id,
            'fixture_name' => $this->fixture_name,
            'kicks_off_at' => $this->kicks_off_at->toIso8601String(),
            'outcome' => $this->outcome,
            'is_void' => $this->is_void,
            'void_reason' => $this->void_reason,
        ];
    }
}
