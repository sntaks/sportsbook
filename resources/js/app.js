/**
 * Bet slip picks, kept in the browser so the slip survives reloads and page changes.
 *
 * The store only remembers what the player picked and the price they saw. The bet slip Livewire component re-checks
 * every pick against current prices on the server whenever the picks change.
 */
const STORAGE_KEY = 'sportsbook.betslip.v1';

function loadPicks() {
    try {
        const picks = JSON.parse(window.localStorage.getItem(STORAGE_KEY) ?? '[]');

        return Array.isArray(picks) ? picks : [];
    } catch {
        return [];
    }
}

document.addEventListener('alpine:init', () => {
    window.Alpine.store('betslip', {
        picks: loadPicks(),
        maxPicks: window.sportsbook?.maxSelections ?? 30,

        has(selectionId) {
            return this.picks.some((pick) => pick.selection_id === selectionId);
        },

        count() {
            return this.picks.length;
        },

        /**
         * Add a pick, replace the pick from the same match, or remove it when it is already on the slip.
         */
        toggle(fixtureId, selectionId, price) {
            if (this.has(selectionId)) {
                this.remove(selectionId);

                return;
            }

            const others = this.picks.filter((pick) => pick.fixture_id !== fixtureId);

            if (others.length >= this.maxPicks) {
                window.dispatchEvent(new CustomEvent('betslip-full', { detail: { max: this.maxPicks } }));

                return;
            }

            this.replace([...others, { fixture_id: fixtureId, selection_id: selectionId, price: String(price) }]);
        },

        remove(selectionId) {
            this.replace(this.picks.filter((pick) => pick.selection_id !== selectionId));
        },

        clear() {
            this.replace([]);
        },

        /**
         * Take on the prices the server now offers, after the player accepts the changes.
         */
        acceptPrices(prices) {
            this.replace(this.picks.map((pick) => (prices[pick.selection_id] ? { ...pick, price: prices[pick.selection_id] } : pick)));
        },

        replace(picks) {
            this.picks = picks.slice(0, this.maxPicks);

            try {
                window.localStorage.setItem(STORAGE_KEY, JSON.stringify(this.picks));
            } catch {
                // Private browsing can block storage; the slip still works for this page view.
            }

            window.dispatchEvent(new CustomEvent('betslip-changed', { detail: { picks: this.picks } }));
        },
    });
});
