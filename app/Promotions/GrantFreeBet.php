<?php

namespace App\Promotions;

use App\Enums\FreeBetSource;
use App\Enums\FreeBetStatus;
use App\Models\Admin;
use App\Models\Bet;
use App\Models\FreeBet;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Gives a player a free bet: from staff, with their name and note in the activity log, or as a replacement when the
 * bet a free bet was used on ended void or was cancelled (the stake of a free bet never comes back as cash).
 */
class GrantFreeBet
{
    /**
     * @param  int  $amount  Cents, whole shillings within the stake limits.
     *
     * @throws ValidationException
     */
    public function byAdmin(User $player, Admin $admin, int $amount, string $minOdds, CarbonInterface $expiresAt, string $note): FreeBet
    {
        $minimum = Money::fromShillings((int) config('sportsbook.limits.min_stake'));
        $maximum = Money::fromShillings((int) config('sportsbook.limits.max_stake'));

        if ($amount < $minimum || $amount > $maximum || $amount % 100 !== 0) {
            throw ValidationException::withMessages(['amount' => __('A free bet must be whole shillings from :min to :max.', ['min' => Money::format($minimum), 'max' => Money::format($maximum)])]);
        }

        if ((float) $minOdds < 1.01) {
            throw ValidationException::withMessages(['min_odds' => __('Minimum odds must be at least 1.01.')]);
        }

        if (! $expiresAt->isFuture()) {
            throw ValidationException::withMessages(['expires_at' => __('The expiry must be in the future.')]);
        }

        $freeBet = FreeBet::create([
            'user_id' => $player->id,
            'amount' => $amount,
            'min_odds' => number_format((float) $minOdds, 2, '.', ''),
            'expires_at' => $expiresAt,
            'status' => FreeBetStatus::Available,
            'source' => FreeBetSource::Admin,
            'note' => mb_substr($note, 0, 255),
            'admin_id' => $admin->id,
        ]);

        activity('promotions')
            ->causedBy($admin)
            ->performedOn($player)
            ->event('free-bet-given')
            ->withProperties(['free_bet_id' => $freeBet->id, 'amount' => Money::format($amount), 'min_odds' => $freeBet->min_odds, 'expires_at' => $freeBet->expires_at->toIso8601String(), 'note' => $note])
            ->log('Free bet given');

        return $freeBet;
    }

    /**
     * A new free bet of the same amount and minimum odds for a free bet whose bet ended void or was cancelled.
     */
    public function replacing(Bet $bet): FreeBet
    {
        $original = $bet->freeBet()->firstOrFail();

        return FreeBet::create([
            'user_id' => $bet->user_id,
            'amount' => $bet->stake,
            'min_odds' => $original->min_odds,
            'expires_at' => now()->addDays((int) config('sportsbook.promotions.free_bets.replacement_days')),
            'status' => FreeBetStatus::Available,
            'source' => FreeBetSource::Replacement,
            'note' => __('Replaces the free bet on bet :reference', ['reference' => $bet->reference]),
            'replaces_bet_id' => $bet->id,
        ]);
    }
}
