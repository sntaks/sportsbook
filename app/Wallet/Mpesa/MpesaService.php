<?php

namespace App\Wallet\Mpesa;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Safaricom Daraja client, ported from sites/api app/Services/MpesaService.php.
 *
 * Changes from the original:
 * - Requests are logged with credentials and phone numbers redacted (MpesaRedactor). The original logged the
 *   full payload, so the STK passkey could be recovered from its error logs.
 * - The STK `Password` and `Timestamp` come from one clock reading, in Nairobi time. The original read the
 *   clock twice, so a second ticking over between them made Safaricom reject the push at random.
 * - Sandbox or production is chosen by `mpesa.environment` (MPESA_ENV), not APP_ENV.
 * - The access-token cache key includes the environment, so switching never reuses the other one's token.
 * - Transaction status, account balance and reversal send real result/timeout URLs (the original sent empty
 *   ones, so those calls could never report back).
 * - c2bSimulate() has no made-up default account and refuses to run in production; a failed
 *   openssl_public_encrypt() is an error instead of an empty credential.
 * - The referral shortcode methods and the `sandbox` app are not needed here and were dropped.
 * - Added STK Push Query.
 */
class MpesaService
{
    private const SANDBOX_BASE = 'https://sandbox.safaricom.co.ke';

    private const PRODUCTION_BASE = 'https://api.safaricom.co.ke';

    private string $environment;

    public function __construct(?string $environment = null)
    {
        $this->environment = $environment ?? (string) config('mpesa.environment');

        if (! in_array($this->environment, ['sandbox', 'production'], true)) {
            throw new InvalidArgumentException("Unknown M-Pesa environment [{$this->environment}]; use sandbox or production.");
        }
    }

    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }

    /**
     * A full callback URL for one of our /api/mpesa/{secret}/… routes.
     */
    public static function callbackUrl(string $path): string
    {
        $secret = (string) config('mpesa.callback_secret');

        if (strlen($secret) < 32) {
            throw new InvalidArgumentException('MPESA_CALLBACK_SECRET must be set to a random string of at least 32 characters.');
        }

        $base = rtrim((string) (config('mpesa.callback_base_url') ?: config('app.url')), '/');

        return "{$base}/api/mpesa/{$secret}/".ltrim($path, '/');
    }

    // -------------------------------------------------------------------------
    // Access Token
    // -------------------------------------------------------------------------

    /**
     * Get a cached OAuth access token for the given app.
     *
     * @throws MpesaApiException
     * @throws ConnectionException when Safaricom cannot be reached.
     * @throws InvalidArgumentException when credentials or the callback secret are not configured.
     */
    public function getAccessToken(string $app = 'c2b'): string
    {
        return Cache::remember(
            "mpesa:{$this->environment}:access-token:{$app}",
            now()->addMinutes(50),
            fn (): string => $this->fetchAccessToken($app),
        );
    }

    /**
     * @throws MpesaApiException
     */
    private function fetchAccessToken(string $app): string
    {
        $response = Http::withHeaders(['Authorization' => 'Basic '.$this->consumerCredentials($app)])
            ->timeout(30)
            ->get($this->baseUrl().'/oauth/v1/generate?grant_type=client_credentials');

        $body = $response->json();

        if (! $response->successful() || ! is_array($body) || ! is_string($body['access_token'] ?? null)) {
            $redacted = is_array($body) ? MpesaRedactor::redact($body) : null;

            Log::channel('mpesa')->error('Failed to retrieve access token', [
                'app' => $app,
                'status' => $response->status(),
                'response' => $redacted,
            ]);

            throw new MpesaApiException(
                'Failed to retrieve M-Pesa access token: '.(is_array($body) && is_string($body['error_description'] ?? null) ? $body['error_description'] : 'Unknown error'),
                statusCode: $response->status(),
                responseBody: $redacted,
            );
        }

        return $body['access_token'];
    }

    // -------------------------------------------------------------------------
    // STK Push (Lipa Na M-Pesa Online)
    // -------------------------------------------------------------------------

    /**
     * Send an STK push asking the phone's owner to pay. Returns Safaricom's acknowledgement, which carries the
     * MerchantRequestID and CheckoutRequestID; the outcome arrives later on the callback.
     *
     * @return array<mixed>
     *
     * @throws MpesaApiException
     * @throws ConnectionException when Safaricom cannot be reached.
     * @throws InvalidArgumentException when credentials or the callback secret are not configured.
     */
    public function stkPush(int $amount, string $phone, string $accountReference, string $description): array
    {
        $config = config('mpesa.lnmo');
        $timestamp = $this->timestamp();

        return $this->request('/mpesa/stkpush/v1/processrequest', [
            'BusinessShortCode' => $config['short_code'],
            'Password' => $this->lnmoPassword($timestamp),
            'Timestamp' => $timestamp,
            'TransactionType' => $config['transaction_type'],
            'Amount' => $amount,
            'PartyA' => $phone,
            'PartyB' => $config['short_code'],
            'PhoneNumber' => $phone,
            'CallBackURL' => self::callbackUrl('stk'),
            // Safaricom limits these to 12 and 13 characters.
            'AccountReference' => mb_substr($accountReference, 0, 12),
            'TransactionDesc' => mb_substr($description, 0, 13),
        ], 'c2b');
    }

    /**
     * Ask Safaricom how an STK push ended, for when its callback never arrived. While the player is still
     * deciding, Safaricom answers with an error (errorCode 500.001.1001), which arrives as an MpesaApiException.
     *
     * @return array<mixed>
     *
     * @throws MpesaApiException
     * @throws ConnectionException when Safaricom cannot be reached.
     * @throws InvalidArgumentException when credentials or the callback secret are not configured.
     */
    public function stkQuery(string $checkoutRequestId): array
    {
        $config = config('mpesa.lnmo');
        $timestamp = $this->timestamp();

        return $this->request('/mpesa/stkpushquery/v1/query', [
            'BusinessShortCode' => $config['short_code'],
            'Password' => $this->lnmoPassword($timestamp),
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
        ], 'c2b');
    }

    // -------------------------------------------------------------------------
    // B2C (Business to Customer — Payouts). Used from phase 3c.
    // -------------------------------------------------------------------------

    /**
     * Initiate a B2C payment request (e.g. withdrawal payout).
     *
     * @param  array{Amount: int, PartyB: string, Remarks?: string, Occasion?: string, OriginatorConversationID?: string}  $params
     * @return array<mixed>
     *
     * @throws MpesaApiException
     * @throws ConnectionException when Safaricom cannot be reached.
     * @throws InvalidArgumentException when credentials or the callback secret are not configured.
     */
    public function b2c(array $params): array
    {
        $config = config('mpesa.b2c');

        return $this->request('/mpesa/b2c/v1/paymentrequest', array_merge([
            'InitiatorName' => $config['initiator_name'],
            'SecurityCredential' => $this->securityCredential($config['security_credential']),
            'CommandID' => $config['command_id'],
            'PartyA' => $config['short_code'],
            'QueueTimeOutURL' => self::callbackUrl('b2c/timeout'),
            'ResultURL' => self::callbackUrl('b2c/result'),
            'Remarks' => 'Withdrawal',
            'Occasion' => '',
        ], $params), 'b2c');
    }

    /**
     * Query the status of a B2C transaction.
     *
     * @param  array{TransactionID?: string, OriginatorConversationID?: string}  $params
     * @return array<mixed>
     *
     * @throws MpesaApiException
     * @throws ConnectionException when Safaricom cannot be reached.
     * @throws InvalidArgumentException when credentials or the callback secret are not configured.
     */
    public function b2cTransactionStatus(array $params): array
    {
        $config = config('mpesa.b2c');

        return $this->request('/mpesa/transactionstatus/v1/query', array_merge([
            'Initiator' => $config['initiator_name'],
            'SecurityCredential' => $this->securityCredential($config['security_credential']),
            'CommandID' => 'TransactionStatusQuery',
            'PartyA' => $config['short_code'],
            'IdentifierType' => '4',
            'Remarks' => 'Transaction Status Query',
            'Occasion' => '',
            'QueueTimeOutURL' => self::callbackUrl('b2c/status/timeout'),
            'ResultURL' => self::callbackUrl('b2c/status/result'),
        ], $params), 'b2c');
    }

    /**
     * Query the B2C account balance. The answer arrives on the balance result callback.
     *
     * @return array<mixed>
     *
     * @throws MpesaApiException
     * @throws ConnectionException when Safaricom cannot be reached.
     * @throws InvalidArgumentException when credentials or the callback secret are not configured.
     */
    public function b2cAccountBalance(): array
    {
        $config = config('mpesa.b2c');

        return $this->request('/mpesa/accountbalance/v1/query', [
            'Initiator' => $config['initiator_name'],
            'SecurityCredential' => $this->securityCredential($config['security_credential']),
            'CommandID' => 'AccountBalance',
            'PartyA' => $config['short_code'],
            'IdentifierType' => '4',
            'Remarks' => 'Account Balance Query',
            'QueueTimeOutURL' => self::callbackUrl('balance/timeout'),
            'ResultURL' => self::callbackUrl('balance/result'),
        ], 'b2c');
    }

    /**
     * Reverse a B2C transaction.
     *
     * UNVERIFIED against current Daraja docs: the field names, receiver party and identifier type are kept exactly
     * as the original sent them (our own shortcode, type 4). Check them before this is first used.
     *
     * @param  array{TransactionID: string, Amount: string}  $params
     * @return array<mixed>
     *
     * @throws MpesaApiException
     * @throws ConnectionException when Safaricom cannot be reached.
     * @throws InvalidArgumentException when credentials or the callback secret are not configured.
     */
    public function reverseB2C(array $params): array
    {
        $config = config('mpesa.b2c');

        return $this->request('/mpesa/reversal/v1/request', array_merge([
            'InitiatorName' => $config['initiator_name'],
            'SecurityCredential' => $this->securityCredential($config['security_credential']),
            'CommandID' => 'TransactionReversal',
            'ReceiverParty' => $config['short_code'],
            'ReceiverIdentifierType' => '4',
            'ResultURL' => self::callbackUrl('reversal/result'),
            'QueueTimeOutURL' => self::callbackUrl('reversal/timeout'),
            'Remarks' => 'Reversal',
            'Occasion' => '',
        ], $params), 'b2c');
    }

    // -------------------------------------------------------------------------
    // C2B (Customer to Business — Paybill deposits)
    // -------------------------------------------------------------------------

    /**
     * Register the Paybill validation and confirmation URLs (run once per shortcode and environment).
     *
     * @return array<mixed>
     *
     * @throws MpesaApiException
     * @throws ConnectionException when Safaricom cannot be reached.
     * @throws InvalidArgumentException when credentials or the callback secret are not configured.
     */
    public function c2bRegister(): array
    {
        $config = config('mpesa.c2b');

        return $this->request('/mpesa/c2b/v2/registerurl', [
            'ShortCode' => $config['short_code'],
            'ResponseType' => $config['response_type'],
            'ConfirmationURL' => self::callbackUrl('c2b/confirmation'),
            'ValidationURL' => self::callbackUrl('c2b/validation'),
        ], 'c2b');
    }

    /**
     * Simulate a Paybill payment. Sandbox only.
     *
     * @return array<mixed>
     *
     * @throws MpesaApiException
     * @throws ConnectionException when Safaricom cannot be reached.
     * @throws InvalidArgumentException when credentials or the callback secret are not configured.
     */
    public function c2bSimulate(int $amount, string $msisdn, string $billRefNumber): array
    {
        if ($this->isProduction()) {
            throw new InvalidArgumentException('C2B simulation only exists in the M-Pesa sandbox.');
        }

        $config = config('mpesa.c2b');

        return $this->request('/mpesa/c2b/v1/simulate', [
            'ShortCode' => $config['short_code'],
            'CommandID' => $config['command_id'],
            'Amount' => $amount,
            'Msisdn' => $msisdn,
            'BillRefNumber' => $billRefNumber,
        ], 'c2b');
    }

    /**
     * Query the Paybill account balance. The answer arrives on the balance result callback.
     *
     * @return array<mixed>
     *
     * @throws MpesaApiException
     * @throws ConnectionException when Safaricom cannot be reached.
     * @throws InvalidArgumentException when credentials or the callback secret are not configured.
     */
    public function c2bAccountBalance(): array
    {
        $config = config('mpesa.c2b');

        return $this->request('/mpesa/accountbalance/v1/query', [
            'Initiator' => $config['initiator_name'],
            'SecurityCredential' => $this->securityCredential($config['security_credential']),
            'CommandID' => 'AccountBalance',
            'PartyA' => $config['short_code'],
            'IdentifierType' => '4',
            'Remarks' => 'Account Balance Query',
            'QueueTimeOutURL' => self::callbackUrl('balance/timeout'),
            'ResultURL' => self::callbackUrl('balance/result'),
        ], 'c2b');
    }

    // -------------------------------------------------------------------------
    // Internal Helpers
    // -------------------------------------------------------------------------

    /**
     * Make an authenticated POST request to the M-Pesa API. Never retried: a retried payment request could
     * move money twice.
     *
     * @param  array<string, mixed>  $payload
     * @return array<mixed>
     *
     * @throws MpesaApiException
     */
    private function request(string $path, array $payload, string $app): array
    {
        $response = Http::withToken($this->getAccessToken($app))
            ->acceptJson()
            ->timeout(30)
            ->post($this->baseUrl().$path, $payload);

        $body = $response->json();
        $redactedBody = is_array($body) ? MpesaRedactor::redact($body) : null;

        if (! $response->successful()) {
            Log::channel('mpesa')->error('M-Pesa API error', [
                'path' => $path,
                'status' => $response->status(),
                'response' => $redactedBody,
                'payload' => MpesaRedactor::redact($payload),
            ]);

            throw new MpesaApiException(
                'M-Pesa API error: '.(is_array($body) ? ($body['errorMessage'] ?? $body['ResponseDescription'] ?? 'Unknown error') : 'Unknown error'),
                statusCode: $response->status(),
                errorCode: is_array($body) && is_string($body['errorCode'] ?? null) ? $body['errorCode'] : null,
                responseBody: $redactedBody,
            );
        }

        Log::channel('mpesa')->info('M-Pesa API request', ['path' => $path, 'response' => $redactedBody]);

        return is_array($body) ? $body : [];
    }

    private function baseUrl(): string
    {
        return $this->isProduction() ? self::PRODUCTION_BASE : self::SANDBOX_BASE;
    }

    private function consumerCredentials(string $app): string
    {
        $credentials = config("mpesa.apps.{$app}");

        if (! is_array($credentials)) {
            throw new InvalidArgumentException("No M-Pesa app credentials defined for [{$app}].");
        }

        $key = (string) ($credentials['consumer_key'] ?? '');
        $secret = (string) ($credentials['consumer_secret'] ?? '');

        if ($key === '' || $secret === '') {
            throw new InvalidArgumentException("M-Pesa [{$app}] consumer key/secret is not set.");
        }

        return base64_encode("{$key}:{$secret}");
    }

    /**
     * Safaricom reads the timestamp as Nairobi time. This app runs in UTC, so it is converted explicitly.
     */
    private function timestamp(): string
    {
        return Date::now()->setTimezone('Africa/Nairobi')->format('YmdHis');
    }

    /**
     * The LNMO (STK) password for a timestamp. The same timestamp must be sent alongside it.
     */
    private function lnmoPassword(string $timestamp): string
    {
        $config = config('mpesa.lnmo');

        return base64_encode($config['short_code'].$config['passkey'].$timestamp);
    }

    /**
     * Encrypt the initiator password with Safaricom's public certificate for this environment.
     */
    private function securityCredential(?string $initiatorPassword): string
    {
        if ($initiatorPassword === null || $initiatorPassword === '') {
            throw new InvalidArgumentException('M-Pesa security credential is not configured.');
        }

        $certificate = file_get_contents(__DIR__.'/Certificates/'.($this->isProduction() ? 'production' : 'sandbox').'.cer');
        $publicKey = $certificate === false ? false : openssl_pkey_get_public($certificate);

        if ($publicKey === false || ! openssl_public_encrypt($initiatorPassword, $encrypted, $publicKey, OPENSSL_PKCS1_PADDING)) {
            throw new MpesaApiException('Could not encrypt the M-Pesa security credential with the '.$this->environment.' certificate.');
        }

        return base64_encode($encrypted);
    }
}
