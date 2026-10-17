<?php

namespace App\Sportsbook\Trading;

use App\Models\Admin;
use App\Models\Market;
use App\Models\OddsSnapshot;
use App\Models\Selection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Sets or clears admin price overrides on a market's selections. An override replaces the feed price for players
 * until it is cleared; odds syncs keep updating the feed price underneath it.
 */
class OverrideSelectionPrices
{
    public const MINIMUM_PRICE = 1.01;

    public const MAXIMUM_PRICE = 1000.0;

    /**
     * @param  array<int, string|null>  $prices  Selection id => override price, or null to clear that override.
     */
    public function handle(Market $market, Admin $admin, array $prices, string $reason): void
    {
        DB::transaction(function () use ($market, $admin, $prices, $reason): void {
            $changes = [];

            foreach ($market->selections as $selection) {
                if (! array_key_exists($selection->id, $prices)) {
                    continue;
                }

                $newOverride = $this->normalise($prices[$selection->id]);

                if ($newOverride === $selection->override_price) {
                    continue;
                }

                $offeredBefore = $selection->price();

                $selection->update([
                    'override_price' => $newOverride,
                    'override_reason' => $newOverride === null ? null : $reason,
                    'override_by_admin_id' => $newOverride === null ? null : $admin->id,
                ]);

                $this->snapshotIfChanged($selection->refresh(), $offeredBefore);
                $changes[$selection->name] = ['from' => $offeredBefore, 'to' => $selection->price()];
            }

            if ($changes === []) {
                return;
            }

            activity('odds')
                ->causedBy($admin)
                ->performedOn($market)
                ->event('prices-overridden')
                ->withProperties(['fixture' => $market->fixture->name(), 'market' => $market->name(), 'reason' => $reason, 'prices' => $changes])
                ->log('Prices overridden');
        });
    }

    private function normalise(?string $price): ?string
    {
        if ($price === null || trim($price) === '') {
            return null;
        }

        if (! is_numeric($price) || (float) $price < self::MINIMUM_PRICE || (float) $price > self::MAXIMUM_PRICE) {
            throw new InvalidArgumentException('Override prices must be between '.self::MINIMUM_PRICE.' and '.self::MAXIMUM_PRICE.'.');
        }

        return number_format((float) $price, 2, '.', '');
    }

    private function snapshotIfChanged(Selection $selection, ?string $offeredBefore): void
    {
        $offeredNow = $selection->price();

        if ($offeredNow === null || $offeredNow === $offeredBefore) {
            return;
        }

        OddsSnapshot::create([
            'selection_id' => $selection->id,
            'price' => $offeredNow,
            'fair_probability' => $selection->fair_probability,
            'source' => $selection->hasOverride() ? 'override' : 'feed',
            'captured_at' => now(),
        ]);
    }
}
