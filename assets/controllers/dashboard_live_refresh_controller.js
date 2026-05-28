import { Controller } from '@hotwired/stimulus';

const FRAME_ID = 'reservue-dashboard-outlet';
const CHANNEL_NAME = 'reservue-workspace-v1';

/**
 * Keeps the admin/staff workspace outlet in sync: polls for new data (e.g. customer
 * bookings) and refreshes after POST mutations without a full browser reload.
 */
export default class extends Controller {
    static values = {
        pollInterval: { type: Number, default: 8000 },
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

        this._pollTimer = window.setInterval(
            () => this.refreshOutlet('poll'),
            this.pollIntervalValue
        );
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
        window.setTimeout(() => this.refreshOutlet('submit'), 80);
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

    refreshOutlet(reason) {
        if (this._refreshing) {
            return;
        }

        const frame = document.getElementById(FRAME_ID);
        if (!frame) {
            return;
        }

        if (frame.hasAttribute('busy')) {
            return;
        }

        if (frame.querySelector('input:focus, select:focus, textarea:focus')) {
            return;
        }

        if (typeof frame.reload === 'function') {
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
            return;
        }

        if (reason === 'poll') {
            return;
        }

        const url = new URL(window.location.href);
        url.searchParams.set('_rv', String(Date.now()));
        frame.src = url.pathname + url.search;
    }
}
