/**
 * Alpine.js bootstrap shim for the Etechflow Part Finder widget.
 *
 * The widget is built on Alpine.js. Hyva-based stores load Alpine globally on
 * every page, so on Hyva this script is a no-op. On Luma / Blank / any
 * non-Hyva theme, Alpine isn't loaded — so we lazy-load it from a CDN on the
 * pages where the Part Finder appears.
 *
 * Stores that don't want a third-party CDN dependency should either:
 *  1. Add Alpine.js to their own theme (recommended for production), or
 *  2. Self-host this file and change CDN_URL below to their own URL.
 */
(function () {
    'use strict';

    var CDN_URL = 'https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js';

    if (typeof window.Alpine !== 'undefined') {
        // Alpine already loaded by the host theme (Hyva). Nothing to do.
        return;
    }

    // Avoid double-injection on multi-form pages (header modal + hero + sidebar)
    if (window.__etechflowAlpineLoading) {
        return;
    }
    window.__etechflowAlpineLoading = true;

    var s = document.createElement('script');
    s.src = CDN_URL;
    s.defer = true;
    s.crossOrigin = 'anonymous';
    s.referrerPolicy = 'no-referrer';
    document.head.appendChild(s);
})();
