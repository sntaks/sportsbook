<?php

namespace App\Accounts\Sms;

use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Log;

/**
 * Local development sender: writes the message (and so the code) to the log instead of texting it,
 * so trying sign-up costs nothing. Refuses to run in production, where it would leak codes into logs
 * and leave players without their SMS.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $phone, string $message): bool
    {
        if (app()->isProduction()) {
            Log::error('SMS not sent: the log SMS driver is not allowed in production. Set SMS_DRIVER=textsms.');

            return false;
        }

        Log::info('SMS to '.PhoneNumber::mask($phone).': '.$message);

        return true;
    }
}
