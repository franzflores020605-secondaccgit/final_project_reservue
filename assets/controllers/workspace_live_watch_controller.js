import { Controller } from '@hotwired/stimulus';

const FRAME_ID = 'reservue-dashboard-outlet';
/** Check interval — only reloads when data actually changed (not a blind refresh). */
const CHECK_MS = 20000;

/**
 * Watches a sync-check URL; reloads the outlet (or fires an event) when fingerprint changes.
 * Used on dashboard, bookings, audit logs, and entity list pages.
 */
export default class extends Controller {
    static values = {
        checkUrl: String,
        refreshEvent: String,
    };

    connect() {
        this._snapshot = null;
        this._onVisibility = () => {
            if (document.visibilityState === 'visible') {
                this.checkForUpdates();
            }
        };
        this._timer = window.setInterval(() => this.checkForUpdates(), CHECK_MS);
        document.addEventListener('visibilitychange', this._onVisibility);
        this.checkForUpdates();
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
            const fingerprint = String(data.fingerprint ?? '');

            if (this._snapshot === null) {
                this._snapshot = fingerprint;
                return;
            }

            if (fingerprint === this._snapshot) {
                return;
            }

            this._snapshot = fingerprint;

            if (this.hasRefreshEventValue && this.refreshEventValue) {
                document.dispatchEvent(
                    new CustomEvent(this.refreshEventValue, { detail: { fingerprint } }),
                );
                return;
            }

            if (typeof frame.reload === 'function') {
                frame.reload();
            }
        } catch {
            // Network errors are ignored; admin can still refresh manually.
        }
    }
}
