<?php

namespace App\Models;

use App\Enums\ExclusionType;
use App\Enums\OddsChangePolicy;
use App\Enums\UserStatus;
use App\Support\PhoneNumber;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string|null $phone
 * @property Carbon|null $phone_verified_at
 * @property Carbon|null $adult_confirmed_at
 * @property UserStatus $status
 * @property string|null $suspension_reason
 * @property ExclusionType|null $exclusion_type
 * @property Carbon|null $excluded_until
 * @property OddsChangePolicy $odds_change_policy
 * @property string|null $name
 * @property string|null $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['phone', 'name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Matches the column default, so a player created in this request already has a status.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'odds_change_policy' => 'higher',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'adult_confirmed_at' => 'datetime',
            'status' => UserStatus::class,
            'exclusion_type' => ExclusionType::class,
            'excluded_until' => 'datetime',
            'odds_change_policy' => OddsChangePolicy::class,
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasOne<Wallet, $this>
     */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    /**
     * The player's ledger entries.
     *
     * @return HasManyThrough<WalletTransaction, Wallet, $this>
     */
    public function walletTransactions(): HasManyThrough
    {
        return $this->hasManyThrough(WalletTransaction::class, Wallet::class);
    }

    /**
     * @return HasMany<MpesaDeposit, $this>
     */
    public function mpesaDeposits(): HasMany
    {
        return $this->hasMany(MpesaDeposit::class);
    }

    /**
     * @return HasMany<Withdrawal, $this>
     */
    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    /**
     * @return HasMany<DepositLimit, $this>
     */
    public function depositLimits(): HasMany
    {
        return $this->hasMany(DepositLimit::class);
    }

    public function isSuspended(): bool
    {
        return $this->status === UserStatus::Suspended;
    }

    /**
     * Whether a cool-off or self-exclusion is running now.
     */
    public function isExcluded(): bool
    {
        return $this->excluded_until !== null && $this->excluded_until->isFuture();
    }

    /**
     * Excluded and suspended players cannot put money in (or bet, from phase 4).
     */
    public function canDeposit(): bool
    {
        return ! $this->isSuspended() && ! $this->isExcluded();
    }

    /**
     * Excluded and suspended players cannot bet.
     */
    public function canBet(): bool
    {
        return ! $this->isSuspended() && ! $this->isExcluded();
    }

    /**
     * @return HasMany<Bet, $this>
     */
    public function bets(): HasMany
    {
        return $this->hasMany(Bet::class);
    }

    /**
     * @return HasMany<JackpotEntry, $this>
     */
    public function jackpotEntries(): HasMany
    {
        return $this->hasMany(JackpotEntry::class);
    }

    /**
     * Excluded players can still take their money out; suspended players cannot.
     */
    public function canWithdraw(): bool
    {
        return ! $this->isSuspended();
    }

    /**
     * The name to show the player: their name if they gave one, otherwise their masked number.
     */
    public function displayName(): string
    {
        return filled($this->name) ? (string) $this->name : PhoneNumber::mask($this->phone);
    }

    /**
     * Get the user's initials, or the last two digits of their number when they have no name.
     */
    public function initials(): string
    {
        if (blank($this->name)) {
            return substr((string) $this->phone, -2);
        }

        $initials = Str::initials((string) $this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
