<?php

namespace App\Accounts;

use App\Http\Resources\V1\UserResource;
use App\Models\User;

/**
 * An API token for one device, used by the mobile app. Each device gets its own token, so logging out on one
 * leaves the others signed in. Tokens expire after sportsbook.api.token_days.
 */
class IssueApiToken
{
    /**
     * @return array{token: string, token_type: string, expires_at: string|null, user: UserResource}
     */
    public function handle(User $user, string $deviceName): array
    {
        $token = $user->createToken($deviceName, ['*'], now()->addDays((int) config('sportsbook.api.token_days')));

        return [
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => UserResource::make($user),
        ];
    }
}
