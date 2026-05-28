import { Controller } from '@hotwired/stimulus';

const FRAME_ID = 'reservue-dashboard-outlet';
const CHANNEL_NAME = 'reservue-workspace-v1';

/**
 * Refreshes workspace lists after admin/staff save/delete — not on a timer.
 * Avoids interrupting edit forms (e.g. choosing a package photo).
 */
export default class extends Controller {
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
    }

    disconnect() {
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
        // Turbo follows the redirect into the outlet; no extra reload on this tab.
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

    isEditingScreen() {
        const path = window.location.pathname;
        return /\/(new|edit)(\/|$)/.test(path);
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
        if (this._refreshing || reason !== 'broadcast') {
            return;
        }

        if (this.isEditingScreen() || this.hasActiveFormWork()) {
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
