import '@hotwired/turbo';
import { config } from '@hotwired/turbo';

/* Avoid the top “page loading” bar on in-app visits (dashboard SPA feels in-place). */
config.drive.progressBarDelay = 999999;

const DASHBOARD_FRAME_ID = 'reservue-dashboard-outlet';

function applyDashboardFormFrames(root) {
    const outlet = document.getElementById(DASHBOARD_FRAME_ID);
    if (!outlet) {
        return;
    }
    const scope = root && outlet.contains(root) ? root : outlet;
    scope.querySelectorAll('form[method="post"], form[method="POST"]').forEach((form) => {
        if (form.getAttribute('data-turbo') === 'false') {
            return;
        }
        if (!form.hasAttribute('data-turbo-frame')) {
            form.setAttribute('data-turbo-frame', DASHBOARD_FRAME_ID);
        }
    });
}

document.addEventListener('turbo:load', () => applyDashboardFormFrames());
document.addEventListener('turbo:frame-load', (event) => {
    if (event.target?.id === DASHBOARD_FRAME_ID) {
        applyDashboardFormFrames(event.target);
    }
});

import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.css';
