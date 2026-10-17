<?php

namespace App\Enums;

enum PhoneCodeResult: string
{
    case Sent = 'sent';
    case Cooldown = 'cooldown';
    case Limited = 'limited';
    case Failed = 'failed';
    case Verified = 'verified';
    case Invalid = 'invalid';
    case Expired = 'expired';
    case TooMany = 'too-many';

    /**
     * What to tell the player, as a translation key for __(). Null for the outcomes that need no message.
     */
    public function message(): ?string
    {
        return match ($this) {
            self::Sent, self::Verified => null,
            self::Cooldown => 'Please wait a moment before asking for another code.',
            self::Limited => 'Too many codes have been requested. Please try again later.',
            self::Failed => 'We could not send the SMS. Please check the number and try again.',
            self::Invalid => 'That code is not correct.',
            self::Expired => 'That code has expired. Please request a new one.',
            self::TooMany => 'Too many wrong codes. Please request a new one.',
        };
    }
}
