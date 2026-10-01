const navbar = document.querySelector('[data-public-navbar]');
const menu = document.querySelector('[data-public-mobile-menu]');
const openButton = document.querySelector('[data-public-menu-open]');
const closeButtons = [...document.querySelectorAll('[data-public-menu-close]')];
const solutions = document.querySelector('[data-public-solutions]');
const solutionsToggle = document.querySelector('[data-solutions-toggle]');
const solutionsMenu = document.querySelector('[data-solutions-menu]');
const mobileSolutionsToggle = document.querySelector('[data-mobile-solutions-toggle]');
const mobileSolutionsList = document.querySelector('[data-mobile-solutions-list]');
const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
let lastFocusedElement = null;

const setScrolledState = () => navbar?.classList.toggle('is-scrolled', window.scrollY > 8);
setScrolledState();
window.addEventListener('scroll', setScrolledState, { passive: true });

const focusable = () => [...(menu?.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])') ?? [])]
    .filter((element) => !element.closest('[hidden]') && element.getClientRects().length > 0);

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
    if (event.target.closest('a[href]')) closeMenu();
});

const setSolutionsOpen = (isOpen, { focusFirst = false } = {}) => {
    if (!solutionsToggle || !solutionsMenu) return;
    solutionsMenu.hidden = !isOpen;
    solutionsToggle.setAttribute('aria-expanded', String(isOpen));
    if (isOpen && focusFirst) solutionsMenu.querySelector('a[href]')?.focus();
};

solutionsToggle?.addEventListener('click', () => {
    setSolutionsOpen(solutionsMenu?.hidden ?? true);
});
solutionsToggle?.addEventListener('keydown', (event) => {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        setSolutionsOpen(true);
        const links = [...(solutionsMenu?.querySelectorAll('a[href]') ?? [])];
        (event.key === 'ArrowUp' ? links.at(-1) : links[0])?.focus();
    }
});
solutionsMenu?.addEventListener('keydown', (event) => {
    const links = [...solutionsMenu.querySelectorAll('a[href]')];
    const index = links.indexOf(document.activeElement);
    if (event.key === 'Escape') {
        event.preventDefault();
        setSolutionsOpen(false);
        solutionsToggle?.focus();
    } else if (event.key === 'ArrowDown' && index < links.length - 1) {
        event.preventDefault();
        links[index + 1]?.focus();
    } else if (event.key === 'ArrowUp' && index > 0) {
        event.preventDefault();
        links[index - 1]?.focus();
    }
});
document.addEventListener('click', (event) => {
    if (solutions && !solutions.contains(event.target)) setSolutionsOpen(false);
});
mobileSolutionsToggle?.addEventListener('click', () => {
    if (!mobileSolutionsList) return;
    const isOpen = mobileSolutionsList.hidden;
    mobileSolutionsList.hidden = !isOpen;
    mobileSolutionsToggle.setAttribute('aria-expanded', String(isOpen));
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        if (solutionsMenu && !solutionsMenu.hidden) {
            event.preventDefault();
            setSolutionsOpen(false);
            solutionsToggle?.focus();
            return;
        }
        if (!menu || menu.hidden) return;
        event.preventDefault();
        closeMenu();
        return;
    }
    if (!menu || menu.hidden) return;
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
    if (window.innerWidth > 900) closeMenu();
    if (window.innerWidth <= 900) setSolutionsOpen(false);
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
