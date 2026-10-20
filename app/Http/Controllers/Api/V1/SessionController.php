<?php

namespace App\Http\Controllers\Api\V1;

use App\Accounts\IssueApiToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @tags Auth
 */
class SessionController extends Controller
{
    /**
     * Log in.
     *
     * Returns an API token for this device. A player with two-factor authentication on must also send `code`: the
     * six-digit code from their authenticator app, or one of their recovery codes (each works once). Without it the
     * response is a 422 with an error on `code`.
     *
     * @unauthenticated
     */
    public function store(LoginRequest $request, IssueApiToken $issueApiToken): JsonResponse
    {
        $phone = PhoneNumber::normalize($request->string('phone')->toString());
        $user = $phone === null ? null : User::query()->where('phone', $phone)->first();

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages(['phone' => __('auth.failed')]);
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $this->checkTwoFactorCode($user, $request->string('code')->trim()->toString());
        }

        return response()->json($issueApiToken->handle($user, $request->string('device_name')->toString()));
    }

    /**
     * Log out.
     *
     * Revokes the token used for this request. Other devices stay logged in.
     */
    public function destroy(Request $request): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->noContent();
    }

    /**
     * Accepts a current authenticator code, or a recovery code, which is then used up.
     *
     * @throws ValidationException
     */
    private function checkTwoFactorCode(User $user, string $code): void
    {
        if ($code === '') {
            throw ValidationException::withMessages(['code' => __('Enter the code from your authenticator app, or a recovery code.')]);
        }

        $recoveryCode = collect($user->recoveryCodes())->first(fn (string $recoveryCode): bool => hash_equals($recoveryCode, $code));

        if (is_string($recoveryCode)) {
            $user->replaceRecoveryCode($recoveryCode);

            return;
        }

        $isValid = app(TwoFactorAuthenticationProvider::class)->verify(
            Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret),
            $code,
        );

        if (! $isValid) {
            throw ValidationException::withMessages(['code' => __('The provided two factor authentication code was invalid.')]);
        }
    }
}
