import './ui';

// Announcement editing and browser-push controls are only needed on
// announcement screens. Keep that optional behavior out of the initial
// authenticated and guest application bundle.
if (document.querySelector('[data-company-wide-toggle], [data-rich-command], [data-browser-alerts]')) {
    import('./announcements');
}

// Keep POS interaction code out of the initial payload for every PMS page.
if (document.querySelector('[data-pos-terminal]')) {
    import('./pos');
}
