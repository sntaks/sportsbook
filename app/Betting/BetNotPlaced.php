<?php

namespace App\Betting;

use RuntimeException;

/**
 * Why a bet was refused, as a reason code the slip (and later the API) can act on, plus a message for the player.
 */
class BetNotPlaced extends RuntimeException
{
    public const NOT_ALLOWED = 'not-allowed';

    public const EMPTY_SLIP = 'empty-slip';

    public const UNAVAILABLE = 'unavailable';

    public const PRICE_CHANGED = 'price-changed';

    public const DUPLICATE_MATCH = 'duplicate-match';

    public const STAKE = 'stake';

    public const INSUFFICIENT_FUNDS = 'insufficient-funds';

    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
