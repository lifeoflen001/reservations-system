const navbar = document.querySelector('[data-public-navbar]');
const menu = document.querySelector('[data-public-mobile-menu]');
const openButton = document.querySelector('[data-public-menu-open]');
const closeButtons = [...document.querySelectorAll('[data-public-menu-close]')];
const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
let lastFocusedElement = null;

const setScrolledState = () => navbar?.classList.toggle('is-scrolled', window.scrollY > 8);
setScrolledState();
window.addEventListener('scroll', setScrolledState, { passive: true });

const focusable = () => menu?.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])') ?? [];

const closeMenu = () => {
    if (!menu || menu.hidden) return;
    menu.hidden = true;
    document.body.classList.remove('public-menu-open');
    openButton?.setAttribute('aria-expanded', 'false');
    lastFocusedElement?.focus();
};

const openMenu = () => {
    if (!menu) return;
    lastFocusedElement = document.activeElement;
    menu.hidden = false;
    document.body.classList.add('public-menu-open');
    openButton?.setAttribute('aria-expanded', 'true');
    menu.querySelector('button, a')?.focus();
};

openButton?.addEventListener('click', openMenu);
closeButtons.forEach((button) => button.addEventListener('click', closeMenu));
menu?.addEventListener('click', (event) => {
    if (event.target.closest('a[href^="#"]')) closeMenu();
});

document.addEventListener('keydown', (event) => {
    if (!menu || menu.hidden) return;
    if (event.key === 'Escape') {
        event.preventDefault();
        closeMenu();
        return;
    }
    if (event.key !== 'Tab') return;
    const items = [...focusable()];
    if (!items.length) return;
    const first = items[0];
    const last = items[items.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
});

window.addEventListener('resize', () => {
    if (window.innerWidth > 800) closeMenu();
});

if (!prefersReducedMotion.matches) {
    document.querySelectorAll('a[href^="#"]').forEach((link) => link.addEventListener('click', (event) => {
        const target = document.querySelector(link.getAttribute('href'));
        if (!target) return;
        event.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        history.replaceState(null, '', link.getAttribute('href'));
    }));
}
