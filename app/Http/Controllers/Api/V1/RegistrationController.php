<?php

namespace App\Http\Controllers\Api\V1;

use App\Accounts\IssueApiToken;
use App\Accounts\RegisterPlayer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ResendRegistrationCodeRequest;
use App\Http\Requests\Api\V1\StartRegistrationRequest;
use App\Http\Requests\Api\V1\VerifyRegistrationRequest;
use Illuminate\Http\JsonResponse;

/**
 * @tags Auth
 */
class RegistrationController extends Controller
{
    /**
     * Start signing up.
     *
     * Checks the details and texts a code to the phone. The account is created by verify once the code matches.
     *
     * @unauthenticated
     */
    public function start(StartRegistrationRequest $request, RegisterPlayer $registerPlayer): JsonResponse
    {
        $phone = $registerPlayer->start($request->validated(), $request->ip());

        return response()->json([
            /** The number the code was sent to, normalized to 2547XXXXXXXX. Send it back with the code. */
            'phone' => $phone,
            'message' => __('We have sent a code to your phone.'),
        ], 202);
    }

    /**
     * Resend the sign-up code.
     *
     * Only for a number that has started signing up and not finished.
     *
     * @unauthenticated
     */
    public function resend(ResendRegistrationCodeRequest $request, RegisterPlayer $registerPlayer): JsonResponse
    {
        $registerPlayer->resend($request->string('phone')->toString(), $request->ip());

        return response()->json(['message' => __('We have sent a new code to your phone.')], 202);
    }

    /**
     * Finish signing up.
     *
     * Creates the account once the code matches, and returns an API token for this device.
     *
     * @unauthenticated
     */
    public function verify(VerifyRegistrationRequest $request, RegisterPlayer $registerPlayer, IssueApiToken $issueApiToken): JsonResponse
    {
        $user = $registerPlayer->complete($request->string('phone')->toString(), $request->string('code')->toString());

        return response()->json($issueApiToken->handle($user, $request->string('device_name')->toString()), 201);
    }
}
