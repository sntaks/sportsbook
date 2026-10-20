<?php

namespace App\Http\Controllers\Api\V1;

use App\Accounts\ResetPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use Illuminate\Http\JsonResponse;

/**
 * @tags Auth
 */
class PasswordResetController extends Controller
{
    /**
     * Ask for a password reset code.
     *
     * Answers the same whether or not the number has an account; only numbers with an account get an SMS.
     *
     * @unauthenticated
     */
    public function store(ForgotPasswordRequest $request, ResetPassword $resetPassword): JsonResponse
    {
        $phone = $resetPassword->request($request->input('phone'), $request->ip());

        return response()->json([
            /** The number normalized to 2547XXXXXXXX. Send it back with the code. */
            'phone' => $phone,
            'message' => __('If this number has an account, we have sent it a code.'),
        ], 202);
    }

    /**
     * Set a new password.
     *
     * Every API token and web session on the account is signed out, so the player logs in again with the new
     * password.
     *
     * @unauthenticated
     */
    public function update(ResetPasswordRequest $request, ResetPassword $resetPassword): JsonResponse
    {
        $resetPassword->reset($request->string('phone')->toString(), $request->only('code', 'password', 'password_confirmation'));

        return response()->json(['message' => __('Your password has been reset. Log in with your new password.')]);
    }
}
