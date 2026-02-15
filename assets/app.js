/*
 * Welcome to your app's main JavaScript file!
 */

// Import Stimulus controllers
import './stimulus_bootstrap.js';

// Import Turbo for SPA-like navigation
import * as Turbo from '@hotwired/turbo';

// Import HTMX for partial page updates
import htmx from 'htmx.org';

// Import Alpine.js
import Alpine from 'alpinejs';

// Import Tailwind CSS
import './styles/app.css';

// Import admin dark theme
import './styles/admin-dark-theme.css';

// Import video preview functionality
import './video-preview.js';

// Import lazy loading functionality
import './js/lazy-loading.js';

// Import theme switcher
import './js/theme-switcher.js';

// Import toast notifications
import './js/toast-notifications.js';

// Import watch later functionality
import './watch-later.js';

// Import notifications system
import './js/notifications.js';

// Import interactions (bookmarks, playlists, subscriptions, ratings)
import './js/interactions.js';

// Make htmx globally available
window.htmx = htmx;

// Make Alpine globally available and defer start
window.Alpine = Alpine;
window.Alpine.plugin = Alpine.plugin;

// Defer Alpine start until DOM is fully loaded
document.addEventListener('alpine:init', () => {
    console.log('Alpine:init event fired');
});

// Start Alpine
Alpine.start();
console.log('Alpine.js started');

// Initialize Turbo
console.log('Turbo initialized');

// Turbo configuration
Turbo.start();

// HTMX configuration
document.addEventListener('DOMContentLoaded', function() {
    console.log('HTMX version:', htmx.version);
    
    // Configure HTMX to include CSRF token
    document.body.addEventListener('htmx:configRequest', function(evt) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (csrfToken) {
            evt.detail.headers['X-CSRF-Token'] = csrfToken.getAttribute('content');
        }
    });
    
    // Debug HTMX events
    document.body.addEventListener('htmx:beforeRequest', function(evt) {
        console.log('HTMX Request:', evt.detail.verb, evt.detail.path);
    });
    
    document.body.addEventListener('htmx:afterRequest', function(evt) {
        console.log('HTMX Response:', evt.detail.xhr.status);
    });
    
    document.body.addEventListener('htmx:responseError', function(evt) {
        console.error('HTMX Error:', evt.detail.xhr.status, evt.detail.xhr.responseText);
    });
});

// Re-process HTMX after Turbo navigation
document.addEventListener('turbo:load', function() {
    htmx.process(document.body);
});

document.addEventListener('turbo:render', function() {
    htmx.process(document.body);
});

console.log('HTMX initialized');
