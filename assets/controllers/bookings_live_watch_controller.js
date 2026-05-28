import { Controller } from '@hotwired/stimulus';

const FRAME_ID = 'reservue-dashboard-outlet';
/** How often to check for new customer bookings (tiny JSON request — no full reload unless something changed). */
const CHECK_MS = 20000;

/**
 * On the Bookings list only: detect new customer bookings and reload the table once.
 */
export default class extends Controller {
    static values = {
        checkUrl: String,
        total: { type: Number, default: 0 },
        latestId: { type: Number, default: 0 },
    };

    connect() {
        this._snapshot = {
            total: this.totalValue,
            latestId: this.latestIdValue,
        };
        this._onVisibility = () => {
            if (document.visibilityState === 'visible') {
                this.checkForUpdates();
            }
        };
        this._timer = window.setInterval(() => this.checkForUpdates(), CHECK_MS);
        document.addEventListener('visibilitychange', this._onVisibility);
    }

    disconnect() {
        window.clearInterval(this._timer);
        document.removeEventListener('visibilitychange', this._onVisibility);
    }

    async checkForUpdates() {
        if (document.visibilityState !== 'visible') {
            return;
        }

        const frame = document.getElementById(FRAME_ID);
        if (!frame) {
            return;
        }
        if (frame.querySelector('input:focus, select:focus, textarea:focus')) {
            return;
        }

        try {
            const response = await fetch(this.checkUrlValue, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                return;
            }
            const data = await response.json();
            const total = Number(data.total) || 0;
            const latestId = Number(data.latestId) || 0;

            if (
                total === this._snapshot.total &&
                latestId === this._snapshot.latestId
            ) {
                return;
            }

            this._snapshot = { total, latestId };

            if (typeof frame.reload === 'function') {
                frame.reload();
            }
        } catch {
            // Ignore network errors; admin can still pull to refresh.
        }
    }
}
