<?php

namespace App\Http\Resources\V1;

use App\Models\MpesaDeposit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An M-Pesa deposit. Poll it while is_pending is true.
 *
 * @mixin MpesaDeposit
 */
class DepositResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel' => $this->channel->value,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_pending' => $this->status->isPending(),
            'amount_cents' => $this->amount,
            'mpesa_receipt' => $this->mpesa_receipt,
            'created_at' => $this->created_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
