<?php

namespace App\Wallet\Mpesa;

/**
 * Strips secrets and personal data from M-Pesa payloads before they are logged or stored on an exception.
 *
 * The STK `Password` is plain base64 of shortcode + passkey + timestamp, so logging it leaks the passkey.
 * Phone numbers appear as top-level keys in requests and as {Name: PhoneNumber, Value: …} items in callbacks.
 */
class MpesaRedactor
{
    private const REDACTED = '[redacted]';

    private const KEYS = ['Password', 'SecurityCredential', 'PhoneNumber', 'PartyA', 'PartyB', 'Msisdn', 'MSISDN', 'access_token'];

    /**
     * @param  array<mixed>  $payload
     * @return array<mixed>
     */
    public static function redact(array $payload): array
    {
        $isPhoneItem = ($payload['Name'] ?? null) === 'PhoneNumber' && array_key_exists('Value', $payload);

        foreach ($payload as $key => $value) {
            if (in_array($key, self::KEYS, true) || ($isPhoneItem && $key === 'Value')) {
                $payload[$key] = self::REDACTED;
            } elseif (is_array($value)) {
                $payload[$key] = self::redact($value);
            }
        }

        return $payload;
    }
}
