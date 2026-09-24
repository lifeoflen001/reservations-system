import './ui';
import './announcements';

// Keep POS interaction code out of the initial payload for every PMS page.
if (document.querySelector('[data-pos-terminal]')) {
    import('./pos');
}
