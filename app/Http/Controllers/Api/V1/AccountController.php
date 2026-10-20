<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OddsChangePolicy;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateOddsPolicyRequest;
use App\Http\Resources\V1\UserResource;
use Illuminate\Http\Request;

/**
 * @tags Account
 */
class AccountController extends Controller
{
    /**
     * The logged-in player.
     *
     * Includes the wallet balance and what the account can do right now (bet, deposit, withdraw).
     */
    public function show(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }

    /**
     * Set the odds change policy.
     *
     * What to do when a price moves between adding a pick and placing the bet. Remembered for the next slip.
     */
    public function updateOddsPolicy(UpdateOddsPolicyRequest $request): UserResource
    {
        $user = $request->user();
        $user->forceFill(['odds_change_policy' => $request->enum('odds_change_policy', OddsChangePolicy::class)])->save();

        return UserResource::make($user);
    }
}
