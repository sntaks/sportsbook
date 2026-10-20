<?php

namespace App\Http\Resources\V1;

use App\Models\User;
use App\Wallet\Ledger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in player's account: what they may do right now, their settings and their balance.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'phone' => $this->phone,
            'display_name' => $this->displayName(),
            'status' => $this->status->value,
            'exclusion' => $this->isExcluded() ? [
                'type' => $this->exclusion_type?->value,
                'until' => $this->excluded_until?->toIso8601String(),
            ] : null,
            'can_bet' => $this->canBet(),
            'can_deposit' => $this->canDeposit(),
            'can_withdraw' => $this->canWithdraw(),
            'odds_change_policy' => $this->odds_change_policy->value,
            'two_factor_enabled' => $this->hasEnabledTwoFactorAuthentication(),
            'balance_cents' => app(Ledger::class)->balanceOf($this->resource),
        ];
    }
}
