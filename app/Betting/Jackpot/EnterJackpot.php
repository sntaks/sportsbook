<?php

namespace App\Betting\Jackpot;

use App\Enums\JackpotEntryStatus;
use App\Enums\WalletTransactionType;
use App\Models\Jackpot;
use App\Models\JackpotEntry;
use App\Models\User;
use App\Support\Money;
use App\Wallet\InsufficientFunds;
use App\Wallet\Ledger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Enters the player into a jackpot with one pick (1, X or 2) for every match, for the jackpot's entry price.
 *
 * Entries close when the first match kicks off. The jackpot row is share-locked while the entry is written, so an
 * entry cannot slip in while the jackpot is being cancelled or settled. The price leaves the wallet in the same
 * transaction. A player may enter the same jackpot more than once.
 *
 * An entry key makes it safe to submit twice: the second submission returns the entry the first one made.
 */
class EnterJackpot
{
    /** No 0/O, 1/I/L: references are read out to support. */
    private const REFERENCE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /** "J" then seven characters, so support can tell an entry from a bet at a glance. */
    private const REFERENCE_LENGTH = 7;

    public function __construct(private Ledger $ledger) {}

    /**
     * @param  array<int|string, mixed>  $picks  Jackpot match id => "1", "X" or "2".
     *
     * @throws JackpotEntryRefused
     */
    public function handle(User $user, Jackpot $jackpot, array $picks, ?string $clientKey = null): JackpotEntry
    {
        if (! $user->canBet()) {
            throw new JackpotEntryRefused(JackpotEntryRefused::NOT_ALLOWED, $user->isSuspended()
                ? __('Your account is suspended. Please contact support.')
                : __('Betting is paused while your break from betting runs.'));
        }

        if ($clientKey !== null && ($entered = $user->jackpotEntries()->where('client_key', $clientKey)->first()) !== null) {
            return $entered;
        }

        try {
            return DB::transaction(fn (): JackpotEntry => $this->enter($user, $jackpot, $picks, $clientKey));
        } catch (UniqueConstraintViolationException $e) {
            // The same entry submitted twice at once: the other request made it.
            $entered = $clientKey === null ? null : $user->jackpotEntries()->where('client_key', $clientKey)->first();

            return $entered ?? throw $e;
        }
    }

    /**
     * @param  array<int|string, mixed>  $picks
     *
     * @throws JackpotEntryRefused
     */
    private function enter(User $user, Jackpot $jackpot, array $picks, ?string $clientKey): JackpotEntry
    {
        $jackpot = Jackpot::query()->whereKey($jackpot->id)->sharedLock()->firstOrFail();

        if (! $jackpot->isTakingEntries()) {
            throw new JackpotEntryRefused(JackpotEntryRefused::CLOSED, __('Entries for this jackpot have closed.'));
        }

        $matches = $jackpot->jackpotFixtures()->get();
        $chosen = [];

        foreach ($matches as $match) {
            $pick = $picks[$match->id] ?? null;
            $pick = is_string($pick) ? strtoupper(trim($pick)) : null;

            if (! in_array($pick, ['1', 'X', '2'], true)) {
                throw new JackpotEntryRefused(JackpotEntryRefused::PICKS, __('Pick 1, X or 2 for every match.'));
            }

            $chosen[$match->id] = $pick;
        }

        if (count($picks) !== count($chosen)) {
            throw new JackpotEntryRefused(JackpotEntryRefused::PICKS, __('Pick 1, X or 2 for every match.'));
        }

        $entry = JackpotEntry::create([
            'jackpot_id' => $jackpot->id,
            'user_id' => $user->id,
            'reference' => $this->reference(),
            'status' => JackpotEntryStatus::Open,
            'stake' => $jackpot->entry_price,
            'client_key' => $clientKey,
            'placed_at' => now(),
        ]);

        $entry->picks()->createMany(collect($chosen)->map(fn (string $pick, int $matchId): array => [
            'jackpot_fixture_id' => $matchId,
            'pick' => $pick,
        ])->values()->all());

        try {
            $debit = $this->ledger->post($user, WalletTransactionType::JackpotEntry, -$entry->stake, "jackpot-entry:{$entry->id}", source: $entry, description: __(':jackpot entry :reference', ['jackpot' => $jackpot->name, 'reference' => $entry->reference]));
        } catch (InsufficientFunds $e) {
            throw new JackpotEntryRefused(JackpotEntryRefused::INSUFFICIENT_FUNDS, __('Your balance is :balance. Deposit to enter this jackpot.', ['balance' => Money::format($e->balance)]));
        }

        $entry->update(['stake_transaction_id' => $debit->id]);

        return $entry;
    }

    private function reference(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $reference = 'J';

            for ($i = 0; $i < self::REFERENCE_LENGTH; $i++) {
                $reference .= self::REFERENCE_ALPHABET[random_int(0, strlen(self::REFERENCE_ALPHABET) - 1)];
            }

            if (! JackpotEntry::query()->where('reference', $reference)->exists()) {
                return $reference;
            }
        }

        throw new RuntimeException('Could not generate a unique jackpot entry reference.');
    }
}
