<?php

namespace App\Wallet\Mpesa;

/**
 * Strips secrets and personal data from M-Pesa payloads before they are logged or stored on an exception.
 *
 * The STK `Password` is plain base64 of shortcode + passkey + timestamp, so logging it leaks the passkey.
 * Phone numbers and names appear as top-level keys in requests, as {Name: PhoneNumber, Value: …} items in STK
 * callbacks, and as {Key: ReceiverPartyPublicName, Value: "2547… - Jane Doe"} items in B2C results.
 */
class MpesaRedactor
{
    private const REDACTED = '[redacted]';

    private const KEYS = ['Password', 'SecurityCredential', 'PhoneNumber', 'PartyA', 'PartyB', 'Msisdn', 'MSISDN', 'access_token'];

    private const ITEM_NAMES = ['PhoneNumber', 'ReceiverPartyPublicName', 'DebitPartyName', 'CreditPartyName'];

    /**
     * @param  array<mixed>  $payload
     * @return array<mixed>
     */
    public static function redact(array $payload): array
    {
        $itemName = $payload['Name'] ?? $payload['Key'] ?? null;
        $isPersonalItem = in_array($itemName, self::ITEM_NAMES, true) && array_key_exists('Value', $payload);

        foreach ($payload as $key => $value) {
            if (in_array($key, self::KEYS, true) || ($isPersonalItem && $key === 'Value')) {
                $payload[$key] = self::REDACTED;
            } elseif (is_array($value)) {
                $payload[$key] = self::redact($value);
            }
        }

        return $payload;
    }
}
