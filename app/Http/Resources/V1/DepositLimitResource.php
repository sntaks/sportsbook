<?php

namespace App\Http\Resources\V1;

use App\Models\DepositLimit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A deposit limit. amount_cents is the limit in force now (null for none). A raise or removal waits in
 * pending_amount_cents until pending_from.
 *
 * @mixin DepositLimit
 */
class DepositLimitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isWaiting = $this->hasWaitingChange();

        return [
            'period' => $this->period->value,
            'period_label' => $this->period->label(),
            'amount_cents' => $this->effectiveAmount(),
            'has_pending_change' => $isWaiting,
            'pending_amount_cents' => $isWaiting ? $this->pending_amount : null,
            'pending_from' => $isWaiting ? $this->pending_from?->toIso8601String() : null,
        ];
    }
}
