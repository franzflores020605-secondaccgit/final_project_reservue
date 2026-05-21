import '@hotwired/turbo';
import { config } from '@hotwired/turbo';

/* Avoid the top “page loading” bar on in-app visits (dashboard SPA feels in-place). */
config.drive.progressBarDelay = 999999;

import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.css';
