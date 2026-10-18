<?php

namespace App\Wallet\Mpesa;

/**
 * The "Result" body Safaricom posts to result URLs (B2C, transaction status, account balance), read safely.
 */
class MpesaResult
{
    /**
     * @param  array<string, mixed>  $parameters  ResultParameters as Key => Value.
     */
    private function __construct(
        public readonly string $resultCode,
        public readonly string $resultDescription,
        public readonly ?string $conversationId,
        public readonly ?string $originatorConversationId,
        public readonly ?string $transactionId,
        public readonly array $parameters,
    ) {}

    /**
     * @param  array<mixed>  $payload
     */
    public static function fromPayload(array $payload): ?self
    {
        $result = $payload['Result'] ?? null;

        if (! is_array($result)) {
            return null;
        }

        $parameters = $result['ResultParameters']['ResultParameter'] ?? [];
        // A single parameter arrives as an object rather than a list.
        $parameters = is_array($parameters) && array_key_exists('Key', $parameters) ? [$parameters] : $parameters;

        return new self(
            resultCode: is_scalar($result['ResultCode'] ?? null) ? (string) $result['ResultCode'] : '',
            resultDescription: is_scalar($result['ResultDesc'] ?? null) ? mb_substr((string) $result['ResultDesc'], 0, 255) : '',
            conversationId: self::stringOrNull($result['ConversationID'] ?? null),
            originatorConversationId: self::stringOrNull($result['OriginatorConversationID'] ?? null),
            transactionId: self::stringOrNull($result['TransactionID'] ?? null),
            parameters: collect(is_array($parameters) ? $parameters : [])
                ->filter(fn (mixed $parameter): bool => is_array($parameter) && is_string($parameter['Key'] ?? null))
                ->mapWithKeys(fn (array $parameter): array => [$parameter['Key'] => $parameter['Value'] ?? null])
                ->all(),
        );
    }

    public function succeeded(): bool
    {
        return $this->resultCode === '0';
    }

    public function parameter(string $key): ?string
    {
        $value = $this->parameters[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
