<?php

namespace App\Accounts\Sms;

use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends one SMS through TextSMS (sms.textsms.co.ke). Ported from Kadi's TextSmsService.
 *
 * Endpoint : POST services/sendsms/  {apikey, partnerID, message, shortcode, mobile}
 * Success  : 200 {"responses": [{"response-code": 200, "response-description": "Success", ...}]}
 *            (TextSMS spells the key "respose-code" in some responses, so both are read.)
 *
 * Never retried automatically: a retry after a timeout could deliver a second code. Logs show the
 * masked number and TextSMS's status only, never the message (it carries the code) or the key.
 */
class TextSmsSender implements SmsSender
{
    public function send(string $phone, string $message): bool
    {
        $phone = PhoneNumber::normalize($phone);
        $config = config('services.textsms');

        if (! PhoneNumber::isValid($phone) || blank($config['api_key'] ?? null) || blank($config['partner_id'] ?? null)) {
            Log::warning('TextSMS not sent: invalid number or TextSMS is not configured.');

            return false;
        }

        try {
            $response = Http::acceptJson()->timeout(10)->post($config['url'], [
                'apikey' => $config['api_key'],
                'partnerID' => (string) $config['partner_id'],
                'message' => $message,
                'shortcode' => $config['shortcode'],
                'mobile' => $phone,
            ]);
        } catch (Throwable $e) {
            Log::warning('TextSMS send to '.PhoneNumber::mask($phone).' failed: '.class_basename($e));

            return false;
        }

        $first = $response->json('responses.0');
        $code = is_array($first) ? (int) ($first['response-code'] ?? $first['respose-code'] ?? 0) : 0;
        $sent = $response->successful() && $code === 200;

        if (! $sent) {
            $description = is_array($first) && is_string($first['response-description'] ?? null)
                ? mb_substr(strip_tags($first['response-description']), 0, 100)
                : '';

            Log::warning('TextSMS send to '.PhoneNumber::mask($phone).": HTTP {$response->status()}, code {$code} {$description}");
        }

        return $sent;
    }
}
