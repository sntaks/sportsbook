<?php

namespace App\Betting\Jackpot;

use App\Enums\JackpotEntryStatus;
use App\Enums\JackpotStatus;
use App\Enums\WalletTransactionType;
use App\Models\Jackpot;
use App\Wallet\Ledger;
use Illuminate\Support\Facades\DB;

/**
 * Undoes a jackpot's settlement so it can settle again after a corrected result: every prize it paid (net of tax)
 * is taken back, and the jackpot and its entries are open again under the next settlement version.
 *
 * Winners may already have spent or withdrawn their prize, so the reversal may take a balance below zero; a
 * negative balance then blocks withdrawals and bets until it is covered.
 */
class ReopenJackpot
{
    public function __construct(private Ledger $ledger) {}

    public function handle(Jackpot $jackpot): Jackpot
    {
        return DB::transaction(function () use ($jackpot): Jackpot {
            $jackpot = Jackpot::query()->whereKey($jackpot->id)->lockForUpdate()->firstOrFail();

            if ($jackpot->status !== JackpotStatus::Settled) {
                return $jackpot;
            }

            $entries = $jackpot->entries()->whereIn('status', [JackpotEntryStatus::Won, JackpotEntryStatus::Lost])->lockForUpdate()->get();

            foreach ($entries->where('status', JackpotEntryStatus::Won) as $entry) {
                if (($entry->prize ?? 0) > 0) {
                    $this->ledger->post(
                        $entry->user,
                        WalletTransactionType::SettlementReversal,
                        -$entry->prize,
                        "jackpot-reversal:{$entry->id}:{$jackpot->settlement_version}",
                        source: $entry,
                        description: __(':jackpot: result corrected', ['jackpot' => $jackpot->name]),
                        allowNegative: true,
                    );
                }
            }

            $jackpot->entries()->whereKey($entries->modelKeys())->update([
                'status' => JackpotEntryStatus::Open,
                'correct_count' => null,
                'prize' => null,
                'prize_transaction_id' => null,
                'settled_at' => null,
            ]);

            $jackpot->update([
                'status' => JackpotStatus::Open,
                'settled_at' => null,
                'settlement_version' => $jackpot->settlement_version + 1,
            ]);

            return $jackpot;
        });
    }
}
