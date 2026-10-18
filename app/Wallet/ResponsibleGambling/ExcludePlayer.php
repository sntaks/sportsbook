<?php

namespace App\Wallet\ResponsibleGambling;

use App\Enums\ExclusionType;
use App\Models\User;
use DateInterval;
use Illuminate\Validation\ValidationException;

/**
 * A player takes a cool-off or self-excludes. While it runs they can log in and withdraw but not deposit or bet.
 * It can be extended but never shortened or lifted early, by the player or anyone else.
 */
class ExcludePlayer
{
    /**
     * @throws ValidationException
     */
    public function handle(User $user, ExclusionType $type, string $duration): User
    {
        $choice = $type->durations()[$duration] ?? null;

        if ($choice === null) {
            throw ValidationException::withMessages(['duration' => __('Choose how long the break should last.')]);
        }

        $until = now()->add(new DateInterval($choice[1]));

        if ($user->isExcluded() && $user->excluded_until !== null && $user->excluded_until->gte($until)) {
            throw ValidationException::withMessages(['duration' => __('Your current break already lasts until :date. It can be made longer, but not shorter.', [
                'date' => $user->excluded_until->timezone(config('sportsbook.timezone'))->format('j M Y, H:i'),
            ])]);
        }

        // A longer cool-off never downgrades a self-exclusion still running.
        $keepsSelfExclusion = $user->isExcluded() && $user->exclusion_type === ExclusionType::SelfExclusion;

        $user->forceFill([
            'exclusion_type' => $keepsSelfExclusion ? ExclusionType::SelfExclusion : $type,
            'excluded_until' => $until,
        ])->save();

        activity('responsible-gambling')
            ->causedBy($user)
            ->performedOn($user)
            ->event('player-excluded')
            ->withProperties(['type' => $type->value, 'duration' => $duration, 'until' => $until->toIso8601String()])
            ->log('Player started a break from betting');

        return $user;
    }
}
