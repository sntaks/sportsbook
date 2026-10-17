<?php

namespace App\Enums;

/**
 * What an SMS code proves. A code sent for one purpose can never be used for another.
 */
enum PhoneCodePurpose: string
{
    case Registration = 'registration';
    case PasswordReset = 'password-reset';
    case PhoneChange = 'phone-change';
}
