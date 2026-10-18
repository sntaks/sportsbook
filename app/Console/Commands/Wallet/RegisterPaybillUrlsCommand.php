<?php

namespace App\Console\Commands\Wallet;

use App\Wallet\Mpesa\MpesaApiException;
use App\Wallet\Mpesa\MpesaService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('mpesa:register-c2b-urls')]
#[Description('Register the Paybill validation and confirmation URLs with Safaricom (once per shortcode and environment)')]
class RegisterPaybillUrlsCommand extends Command
{
    public function handle(MpesaService $mpesa): int
    {
        $this->line('Confirmation URL: '.preg_replace('#/api/mpesa/[^/]+/#', '/api/mpesa/[secret]/', MpesaService::callbackUrl('c2b/confirmation')));

        try {
            $response = $mpesa->c2bRegister();
        } catch (MpesaApiException|InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info((string) ($response['ResponseDescription'] ?? 'Registered.'));

        return self::SUCCESS;
    }
}
