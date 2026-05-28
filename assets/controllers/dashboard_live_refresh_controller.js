import { Controller } from '@hotwired/stimulus';

const FRAME_ID = 'reservue-dashboard-outlet';
const CHANNEL_NAME = 'reservue-workspace-v1';

/** Index/list screens only — never edit or detail pages. */
const LIST_PATHS = new Set([
    '/bookings',
    '/dashboard',
    '/staff/dashboard',
    '/product',
    '/category',
    '/travel-package',
    '/traveler',
    '/admin/users',
    '/admin/logs',
]);

/**
 * - After admin/staff save: Turbo redirect + optional cross-tab refresh.
 * - On list pages only: light polling so new customer bookings appear without F5.
 * - Never polls on edit/new (avoids interrupting file uploads).
 */
export default class extends Controller {
    static values = {
        listPollInterval: { type: Number, default: 12000 },
    };

    connect() {
        this._refreshing = false;
        this._channel =
            typeof BroadcastChannel !== 'undefined' ? new BroadcastChannel(CHANNEL_NAME) : null;

        this._onChannelMessage = () => this.refreshOutlet('broadcast');
        if (this._channel) {
            this._channel.addEventListener('message', this._onChannelMessage);
        }

        this._onSubmitEnd = (event) => this.handleSubmitEnd(event);
        document.addEventListener('turbo:submit-end', this._onSubmitEnd);

        this._pollList = () => this.refreshOutlet('list-poll');
        if (this.listPollIntervalValue > 0) {
            this._pollTimer = window.setInterval(this._pollList, this.listPollIntervalValue);
        }
    }

    disconnect() {
        if (this._pollTimer) {
            window.clearInterval(this._pollTimer);
        }
        document.removeEventListener('turbo:submit-end', this._onSubmitEnd);
        if (this._channel) {
            this._channel.removeEventListener('message', this._onChannelMessage);
            this._channel.close();
        }
    }

    handleSubmitEnd(event) {
        const { success, formSubmission } = event.detail;
        if (!success || !formSubmission) {
            return;
        }

        const form = formSubmission.formElement;
        if (!form || !this.isWorkspaceMutation(form)) {
            return;
        }

        this.notifyMutation();
    }

    isWorkspaceMutation(form) {
        if (form.getAttribute('data-turbo') === 'false') {
            return false;
        }
        if (!this.element.contains(form)) {
            return false;
        }
        const method = (form.getAttribute('method') || 'get').toLowerCase();
        return ['post', 'put', 'patch', 'delete'].includes(method);
    }

    notifyMutation() {
        if (!this._channel) {
            return;
        }
        this._channel.postMessage({
            at: Date.now(),
            path: window.location.pathname,
        });
    }

    normalizedPath() {
        const path = window.location.pathname.replace(/\/+$/, '');
        return path === '' ? '/' : path;
    }

    isListScreen() {
        return LIST_PATHS.has(this.normalizedPath());
    }

    isEditingScreen() {
        return /\/(new|edit)$/.test(this.normalizedPath());
    }

    hasActiveFormWork() {
        const frame = document.getElementById(FRAME_ID);
        if (!frame) {
            return false;
        }
        if (frame.querySelector('input:focus, select:focus, textarea:focus')) {
            return true;
        }
        const fileInputs = frame.querySelectorAll('input[type="file"]');
        for (const input of fileInputs) {
            if (input.files && input.files.length > 0) {
                return true;
            }
        }
        return false;
    }

    refreshOutlet(reason) {
        if (this._refreshing) {
            return;
        }

        if (reason === 'list-poll') {
            if (!this.isListScreen() || this.isEditingScreen() || this.hasActiveFormWork()) {
                return;
            }
        } else if (reason === 'broadcast') {
            if (this.isEditingScreen() || this.hasActiveFormWork()) {
                return;
            }
        } else {
            return;
        }

        const frame = document.getElementById(FRAME_ID);
        if (!frame || frame.hasAttribute('busy')) {
            return;
        }

        if (typeof frame.reload !== 'function') {
            return;
        }

        this._refreshing = true;
        const done = () => {
            this._refreshing = false;
        };
        frame.addEventListener('turbo:frame-load', done, { once: true });
        frame.addEventListener('turbo:frame-missing', done, { once: true });
        try {
            frame.reload();
        } catch (e) {
            done();
        }
    }
}
