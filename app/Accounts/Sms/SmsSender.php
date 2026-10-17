<?php

namespace App\Accounts\Sms;

interface SmsSender
{
    /**
     * Send one SMS. Returns whether the provider accepted it; never throws and never retries.
     */
    public function send(string $phone, string $message): bool;
}
