/**
 * Bet slip picks, kept in the browser so the slip survives reloads and page changes.
 *
 * The store only remembers what the player picked, the price they saw and the labels to show for it. The slip shows a
 * new pick from these at once; the bet slip Livewire component then re-checks every pick against current prices on the
 * server whenever the picks change, and only its result counts.
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
         * The combined odds of the prices the player saw, shown until the server's check comes back.
         */
        seenOdds() {
            const odds = this.picks.reduce((product, pick) => product * Number(pick.price), 1);

            // Rounded down to 2 decimals, as the server does.
            return (Math.floor(Number((odds * 100).toFixed(6))) / 100).toFixed(2);
        },

        /**
         * Add a pick, replace the pick from the same match, or remove it when it is already on the slip.
         */
        toggle(fixtureId, selectionId, price, labels = {}) {
            if (this.has(selectionId)) {
                this.remove(selectionId);

                return;
            }

            const others = this.picks.filter((pick) => pick.fixture_id !== fixtureId);

            if (others.length >= this.maxPicks) {
                window.dispatchEvent(new CustomEvent('betslip-full', { detail: { max: this.maxPicks } }));

                return;
            }

            this.replace([...others, { fixture_id: fixtureId, selection_id: selectionId, price: String(price), ...labels }]);
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

/**
 * The match panel: a match's markets sliding over the current page instead of loading the match page.
 *
 * Opening it adds a history entry for the match URL, so the address bar shows the match and the back button closes
 * the panel. That popstate is handled here before Livewire's own handler, which would otherwise re-render the page.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('matchPanel', () => ({
        isOpen: false,
        requested: null,
        loaded: null,
        title: '',
        url: '',
        pushed: false,
        pageTitle: null,
        returnFocus: null,

        init() {
            this.onPopState = (event) => {
                if (this.pushed) {
                    event.stopImmediatePropagation();
                    this.pushed = false;
                    this.hide();
                } else if (event.state?.matchPanel) {
                    // Forward again onto a match that was opened in the panel.
                    event.stopImmediatePropagation();
                    this.open(event.state.matchPanel, { fromHistory: true });
                }
            };

            // Capturing listeners on the window run before Livewire's non-capturing one.
            window.addEventListener('popstate', this.onPopState, true);
        },

        destroy() {
            window.removeEventListener('popstate', this.onPopState, true);
            document.body.classList.remove('overflow-hidden');
        },

        open({ id, title, url }, { fromHistory = false } = {}) {
            if (!this.isOpen) {
                this.pageTitle = document.title;
                this.returnFocus = document.activeElement;
            }

            this.requested = id;
            this.title = title;
            this.url = url;
            document.title = title;

            const state = { matchPanel: { id, title, url } };

            if (fromHistory || this.pushed) {
                window.history.replaceState(state, '', url);
            } else {
                window.history.pushState(state, '', url);
            }

            this.pushed = true;
            this.isOpen = true;
            document.body.classList.add('overflow-hidden');
            this.$nextTick(() => this.$refs.close?.focus());

            if (this.loaded !== id) {
                this.$wire.show(id).then(() => {
                    if (this.requested === id) {
                        this.loaded = id;
                    }
                });
            }
        },

        close() {
            if (this.pushed) {
                window.history.back();
            } else {
                this.hide();
            }
        },

        hide() {
            this.isOpen = false;
            document.body.classList.remove('overflow-hidden');

            if (this.pageTitle !== null) {
                document.title = this.pageTitle;
            }

            this.returnFocus?.focus?.();
        },
    }));

    /**
     * The labels the slip shows for a pick before the server answers, read from the odds button and the market and
     * match around it.
     */
    window.Alpine.magic('pickLabels', (el) => ({
        name: el.dataset.pickName ?? '',
        market: el.closest('[data-market]')?.dataset.market ?? '',
        match: el.closest('[data-match]')?.dataset.match ?? '',
    }));

    /**
     * Click handler for match links: opens the match panel, but leaves new-tab and modified clicks to the browser.
     */
    window.Alpine.magic('openMatch', (el) => (event, id, title) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        event.preventDefault();

        if (document.querySelector('[data-match-panel]')) {
            window.dispatchEvent(new CustomEvent('open-match', { detail: { id, title, url: el.href } }));
        } else {
            window.Livewire.navigate(el.href);
        }
    });
});
