<?php

namespace App\Enums;

enum MpesaDepositChannel: string
{
    /** We sent an STK push (Lipa Na M-Pesa Online) to the player's phone. */
    case Stk = 'stk';
    /** The player paid our Paybill from their own M-Pesa menu (C2B). */
    case Paybill = 'paybill';
}
