<?php

namespace App\Wallet\Mpesa;

use RuntimeException;

class MpesaApiException extends RuntimeException
{
    /**
     * @param  array<mixed>|null  $responseBody  Already redacted.
     */
    public function __construct(
        string $message = '',
        public readonly ?int $statusCode = null,
        public readonly ?string $errorCode = null,
        public readonly ?array $responseBody = null,
    ) {
        parent::__construct($message);
    }
}
