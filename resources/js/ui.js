const root = document.documentElement;
const body = document.body;

const initConnectionMonitor = () => {
    const status = document.querySelector('[data-connection-status]');
    const message = status?.querySelector('[data-connection-message]');
    const healthUrl = body.dataset.networkHealthUrl;
    if (!status || !message || !healthUrl) return;

    let probeController = null;
    let hideTimer = null;
    let lastState = navigator.onLine === false ? 'offline' : 'unknown';
    let initialProbeComplete = false;

    const hideStatus = () => {
        window.clearTimeout(hideTimer);
        status.hidden = true;
    };

    const showStatus = (state, text, autoHide = false) => {
        window.clearTimeout(hideTimer);
        status.className = `connection-status connection-status--${state}`;
        message.textContent = text;
        status.hidden = false;
        lastState = state;
        if (autoHide) hideTimer = window.setTimeout(hideStatus, 6000);
    };

    const connectionInfo = () => navigator.connection || navigator.mozConnection || navigator.webkitConnection;
    const qualityFor = (latency) => {
        const info = connectionInfo();
        const type = info?.effectiveType;
        const rtt = Number(info?.rtt) || 0;
        if (latency >= 1800 || rtt >= 1200 || type === 'slow-2g' || type === '2g') return 'slow';
        if (latency >= 700 || rtt >= 600 || type === '3g') return 'fair';
        return 'good';
    };

    const showQuality = (quality, announceOnline = false) => {
        const hadProblem = ['offline', 'unstable', 'fair', 'slow'].includes(lastState);
        if (quality === 'good') {
            if (announceOnline || (initialProbeComplete && hadProblem)) showStatus('online', announceOnline ? 'Back online. Connection is good.' : 'Connection restored. You are back online.', true);
            else { lastState = 'good'; hideStatus(); }
            return;
        }
        if (quality === 'slow') {
            showStatus('slow', announceOnline ? 'Back online, but your connection is slow. Pages and saves may take longer.' : 'Your connection is slow. Pages and saves may take longer.');
            return;
        }
        showStatus('fair', announceOnline ? 'Back online. Connection quality is fair.' : 'Connection quality is fair. Pages may take longer to load.', true);
    };

    const probe = async ({ announceOnline = false } = {}) => {
        if (navigator.onLine === false) {
            showStatus('offline', 'You are offline. Changes cannot be saved until your connection returns.');
            initialProbeComplete = true;
            return;
        }

        probeController?.abort();
        const controller = new AbortController();
        probeController = controller;
        const timeout = window.setTimeout(() => controller.abort(), 8000);
        const startedAt = performance.now();
        const url = new URL(healthUrl, window.location.href);
        url.searchParams.set('connection_probe', String(Date.now()));

        try {
            const response = await fetch(url, {
                cache: 'no-store',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal,
            });
            const payload = await response.json();
            if (!response.ok || payload?.status !== 'ok') throw new Error('HotelDesk health check reported a degraded service.');
            showQuality(qualityFor(performance.now() - startedAt), announceOnline);
        } catch (error) {
            if (error.name === 'AbortError') return;
            if (navigator.onLine === false) showStatus('offline', 'You are offline. Changes cannot be saved until your connection returns.');
            else showStatus('unstable', 'Network connected, but HotelDesk is having trouble reaching the server. We will keep retrying.');
        } finally {
            window.clearTimeout(timeout);
            initialProbeComplete = true;
        }
    };

    window.hotelDeskConnection = {
        check: probe,
        notifyFailure: (text = 'Network request failed. Check your connection and try again.') => showStatus('unstable', text),
    };

    window.addEventListener('offline', () => showStatus('offline', 'You are offline. Changes cannot be saved until your connection returns.'));
    window.addEventListener('online', () => {
        showStatus('online', 'Back online. Checking your connection…');
        window.setTimeout(() => probe({ announceOnline: true }), 250);
    });

    const network = connectionInfo();
    network?.addEventListener('change', () => probe());
    window.setInterval(() => {
        if (document.visibilityState === 'visible') probe();
    }, 60000);

    probe();
};

const persistThemePreference = (theme) => {
    const url = body.dataset.themePreferenceUrl;
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!url || !token || !['light', 'dark'].includes(theme)) return;
    fetch(url, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ theme }),
        credentials: 'same-origin',
    }).catch(() => {});
};

const setTheme = (theme, { persist = true } = {}) => {
    if (!['light', 'dark'].includes(theme)) theme = 'light';
    root.dataset.theme = theme;
    if (persist) {
        root.dataset.themePreference = theme;
        persistThemePreference(theme);
    }
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        const label = theme === 'dark' ? 'Use light mode' : 'Use dark mode';
        button.setAttribute('aria-label', label);
        button.dataset.tooltip = label;
        button.innerHTML = theme === 'dark' ? '<svg class="ui-icon" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3.5"></circle><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.65 17.65l1.42 1.42M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.65 6.35l1.42-1.42"></path></svg>' : '<svg class="ui-icon" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 15.2A8.5 8.5 0 0 1 8.8 4 8.5 8.5 0 1 0 20 15.2Z"></path></svg>';
    });
};

const closeDropdowns = () => document.querySelectorAll('[data-dropdown]').forEach((dropdown) => {
    dropdown.classList.remove('is-open');
    const toggle = dropdown.querySelector('[data-dropdown-toggle]');
    const menu = dropdown.querySelector('[data-dropdown-menu]');
    if (toggle) toggle.setAttribute('aria-expanded', 'false');
    if (menu) menu.hidden = true;
});

const closeSidebar = () => {
    document.querySelector('[data-sidebar]')?.classList.remove('is-open');
    document.querySelector('[data-sidebar-backdrop]')?.classList.remove('is-visible');
    body.classList.remove('drawer-open');
};

const tooltipState = { element: null, target: null };
const hideContextTooltip = () => {
    tooltipState.element?.remove();
    tooltipState.element = null;
    tooltipState.target = null;
};
const positionContextTooltip = () => {
    const tooltip = tooltipState.element;
    const target = tooltipState.target;
    if (!tooltip || !target || !document.documentElement.contains(target)) return;
    const rect = target.getBoundingClientRect();
    const isCollapsedNav = target.matches('.sidebar-collapsed .nav-item') || target.closest('.sidebar-collapsed .nav-item');
    const gap = 9;
    const tooltipRect = tooltip.getBoundingClientRect();
    let left = rect.left + (rect.width - tooltipRect.width) / 2;
    let top = rect.top - tooltipRect.height - gap;
    let placement = 'top';
    if (isCollapsedNav) {
        left = rect.right + gap;
        top = rect.top + (rect.height - tooltipRect.height) / 2;
        placement = 'right';
    } else if (top < 8) {
        top = rect.bottom + gap;
        placement = 'bottom';
    }
    left = Math.max(8, Math.min(left, window.innerWidth - tooltipRect.width - 8));
    top = Math.max(8, Math.min(top, window.innerHeight - tooltipRect.height - 8));
    tooltip.dataset.placement = placement;
    tooltip.style.left = `${left}px`;
    tooltip.style.top = `${top}px`;
};
const showContextTooltip = (target) => {
    const isSidebarNav = target?.matches('.nav-item');
    const isCollapsedNav = target?.closest('.sidebar-collapsed .nav-item');
    if (isSidebarNav && !isCollapsedNav) return;
    const label = target?.dataset.tooltip || target?.getAttribute('aria-label');
    if (!label) return;
    hideContextTooltip();
    const tooltip = document.createElement('div');
    tooltip.className = `context-tooltip${isCollapsedNav ? ' context-tooltip--navigation' : ''}`;
    tooltip.setAttribute('role', 'tooltip');
    tooltip.textContent = label;
    document.body.append(tooltip);
    tooltipState.element = tooltip;
    tooltipState.target = target;
    requestAnimationFrame(positionContextTooltip);
};
const bindContextTooltips = () => {
    document.querySelectorAll('[aria-label]').forEach((element) => {
        if (element.matches('.app-logo')) return;
        const isIconOnly = element.matches('.icon-button, .topbar__icon, .topbar__menu, .global-search__submit, .password-toggle, .sidebar__close, .planning-group-toggle') || !element.textContent.trim();
        if (isIconOnly && !element.dataset.tooltip) element.dataset.tooltip = element.getAttribute('aria-label');
    });
    document.querySelectorAll('[data-tooltip]').forEach((element) => {
        if (element.matches('.app-logo')) return;
        element.addEventListener('pointerenter', () => showContextTooltip(element));
        element.addEventListener('pointerleave', hideContextTooltip);
        element.addEventListener('focus', () => showContextTooltip(element));
        element.addEventListener('blur', hideContextTooltip);
    });
};
const bindPageLoading = () => {
    const pageSkeleton = document.querySelector('[data-app-page-skeleton]');
    if (!pageSkeleton) return;
    const showPageSkeleton = () => {
        pageSkeleton.hidden = false;
        pageSkeleton.setAttribute('aria-hidden', 'false');
        body.classList.add('is-page-loading');
    };
    const hidePageSkeleton = () => {
        pageSkeleton.hidden = true;
        pageSkeleton.setAttribute('aria-hidden', 'true');
        body.classList.remove('is-page-loading');
    };
    const isActionLink = (link) => link.closest([
        'form',
        '.filter-toolbar',
        '.planning-filter-bar',
        '.planning-toolbar',
        '.page-header__actions',
        '.modal-form-footer',
        '.row-actions',
    ].join(', ')) || link.matches('.ui-button, [role="button"], [data-no-page-loading]');

    // Form submissions and action controls should never display the full-page
    // navigation skeleton. The response will render its own result or error.
    document.addEventListener('submit', hidePageSkeleton, true);
    document.addEventListener('click', (event) => {
        if (event.target.closest('button, input, select, textarea, form')) {
            hidePageSkeleton();
            return;
        }
        const link = event.target.closest('a[href]');
        if (!link || isActionLink(link) || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target === '_blank' || link.hasAttribute('download')) return;
        const url = new URL(link.href, window.location.href);
        if ((url.origin === window.location.origin && url.pathname !== window.location.pathname) || (url.origin === window.location.origin && url.pathname === window.location.pathname && url.search !== window.location.search)) showPageSkeleton();
    });
};

document.addEventListener('DOMContentLoaded', () => {
    setTheme(root.dataset.theme || 'light', { persist: false });
    const systemTheme = window.matchMedia('(prefers-color-scheme: dark)');
    const applySystemTheme = () => {
        if (root.dataset.themePreference === 'system') setTheme(systemTheme.matches ? 'dark' : 'light', { persist: false });
    };
    if (typeof systemTheme.addEventListener === 'function') systemTheme.addEventListener('change', applySystemTheme);
    else if (typeof systemTheme.addListener === 'function') systemTheme.addListener(applySystemTheme);
    bindContextTooltips();
    bindPageLoading();
    initConnectionMonitor();
    window.addEventListener('scroll', positionContextTooltip, true);
    window.addEventListener('resize', positionContextTooltip);

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => button.addEventListener('click', () => {
        setTheme(root.dataset.theme === 'dark' ? 'light' : 'dark');
    }));

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const input = button.closest('.password-input')?.querySelector('input');
        if (!input) return;
        button.addEventListener('click', () => {
            const visible = input.type === 'text';
            input.type = visible ? 'password' : 'text';
            button.setAttribute('aria-pressed', visible ? 'false' : 'true');
            button.setAttribute('aria-label', visible ? 'Show password' : 'Hide password');
            button.dataset.tooltip = visible ? 'Show password' : 'Hide password';
            button.querySelector('[data-password-eye]')?.toggleAttribute('hidden', !visible);
            button.querySelector('[data-password-eye-off]')?.toggleAttribute('hidden', visible);
        });
    });

    // Keep date/time values in their server-friendly formats while presenting
    // one compact, PMS-styled control across reservations, tasks, payments,
    // housekeeping, maintenance, reports, and settings.
    const closeDatePickers = (except = null) => document.querySelectorAll('[data-pms-datetime]').forEach((picker) => {
        if (picker !== except) {
            picker.classList.remove('is-open');
            picker.querySelector('[data-pms-datetime-trigger]')?.setAttribute('aria-expanded', 'false');
            picker.querySelector('[data-pms-datetime-popover]')?.setAttribute('hidden', '');
        }
    });
    const dateParts = (value, type) => {
        if (!value) return { date: '', time: '' };
        if (type === 'datetime-local') {
            const [date = '', time = ''] = value.split('T');
            return { date, time: time.slice(0, 5) };
        }
        return type === 'date' ? { date: value, time: '' } : { date: '', time: value.slice(0, 5) };
    };
    const formatDatePart = (value) => {
        if (!value) return '';
        const [year, month, day] = value.split('-').map(Number);
        if (!year || !month || !day) return value;
        return new Intl.DateTimeFormat(undefined, { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(Date.UTC(year, month - 1, day)));
    };
    const enhanceDateTimeInputs = () => document.querySelectorAll('input[type="datetime-local"], input[type="date"], input[type="time"]').forEach((input) => {
        if (input.closest('[data-pms-datetime]')) return;
        const type = input.type;
        const wrapper = document.createElement('div');
        wrapper.className = 'pms-datetime';
        wrapper.dataset.pmsDatetime = type;
        input.parentNode.insertBefore(wrapper, input);
        wrapper.append(input);
        input.classList.add('pms-datetime__native');
        input.tabIndex = -1;
        input.setAttribute('aria-hidden', 'true');

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'pms-datetime__trigger';
        trigger.dataset.pmsDatetimeTrigger = '';
        trigger.setAttribute('aria-haspopup', 'dialog');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-label', input.getAttribute('aria-label') || input.name.replaceAll('_', ' '));
        trigger.innerHTML = '<svg class="ui-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15" rx="1.5"></rect><path d="M7 3v4M17 3v4M3.5 9h17"></path></svg><span data-pms-datetime-label></span><svg class="pms-datetime__chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>';
        wrapper.append(trigger);

        const popover = document.createElement('div');
        popover.className = 'pms-datetime__popover';
        popover.dataset.pmsDatetimePopover = '';
        popover.setAttribute('role', 'dialog');
        popover.setAttribute('aria-label', `Choose ${input.getAttribute('aria-label') || input.name.replaceAll('_', ' ')}`);
        popover.hidden = true;
        const parts = dateParts(input.value, type);
        const [initialHour = '00', initialMinute = '00'] = (parts.time || '00:00').split(':');
        const timePicker = type !== 'date' ? `<label>Time<div class="pms-time-picker"><select data-pms-picker-hour aria-label="Hour">${Array.from({ length: 24 }, (_, hour) => `<option value="${String(hour).padStart(2, '0')}" ${String(hour).padStart(2, '0') === initialHour ? 'selected' : ''}>${String(hour).padStart(2, '0')}</option>`).join('')} </select><span>:</span><select data-pms-picker-minute aria-label="Minute">${Array.from({ length: 12 }, (_, minute) => { const value = String(minute * 5).padStart(2, '0'); return `<option value="${value}" ${value === initialMinute ? 'selected' : ''}>${value}</option>`; }).join('')}</select></div></label>` : '';
        popover.innerHTML = `<div class="pms-datetime__popover-heading"><strong>Choose ${type === 'time' ? 'time' : type === 'date' ? 'date' : 'date and time'}</strong><button type="button" class="icon-button" data-pms-datetime-close aria-label="Close picker"><span aria-hidden="true">×</span></button></div>${type !== 'time' ? `<div class="pms-calendar"><div class="pms-calendar__header"><button type="button" class="pms-calendar__nav" data-pms-calendar-prev aria-label="Previous month">‹</button><strong data-pms-calendar-month></strong><button type="button" class="pms-calendar__nav" data-pms-calendar-next aria-label="Next month">›</button></div><div class="pms-calendar__weekdays" aria-hidden="true"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div><div class="pms-calendar__grid" data-pms-calendar-grid></div><button type="button" class="pms-calendar__today" data-pms-calendar-today>Today</button></div>` : ''}<div class="pms-datetime__fields">${timePicker}</div><div class="pms-datetime__actions"><button type="button" class="ui-button ui-button--ghost ui-button--small" data-pms-datetime-clear>Clear</button><button type="button" class="ui-button ui-button--primary ui-button--small" data-pms-datetime-apply>Done</button></div>`;
        wrapper.append(popover);

        const label = trigger.querySelector('[data-pms-datetime-label]');
        let selectedDate = parts.date;
        let monthCursor = selectedDate ? new Date(`${selectedDate}T00:00:00Z`) : new Date();
        monthCursor = new Date(Date.UTC(monthCursor.getUTCFullYear(), monthCursor.getUTCMonth(), 1));
        const todayIso = () => {
            const now = new Date();
            const parts = new Intl.DateTimeFormat('en-CA', { timeZone: document.body.dataset.propertyTimezone || Intl.DateTimeFormat().resolvedOptions().timeZone, year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(now).reduce((result, part) => { result[part.type] = part.value; return result; }, {});
            return `${parts.year}-${parts.month}-${parts.day}`;
        };
        const renderCalendar = () => {
            const grid = popover.querySelector('[data-pms-calendar-grid]');
            const monthLabel = popover.querySelector('[data-pms-calendar-month]');
            if (!grid || !monthLabel) return;
            monthLabel.textContent = new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(monthCursor);
            grid.replaceChildren();
            const year = monthCursor.getUTCFullYear();
            const month = monthCursor.getUTCMonth();
            const firstDay = new Date(Date.UTC(year, month, 1)).getUTCDay();
            const daysInMonth = new Date(Date.UTC(year, month + 1, 0)).getUTCDate();
            const previousDays = new Date(Date.UTC(year, month, 0)).getUTCDate();
            for (let index = 0; index < 42; index += 1) {
                const dayNumber = index - firstDay + 1;
                const date = new Date(Date.UTC(year, month, dayNumber));
                const iso = date.toISOString().slice(0, 10);
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'pms-calendar__day';
                button.textContent = String(date.getUTCDate());
                button.dataset.date = iso;
                button.setAttribute('aria-label', formatDatePart(iso));
                if (dayNumber < 1 || dayNumber > daysInMonth) button.classList.add('is-outside-month');
                if (iso === selectedDate) button.classList.add('is-selected');
                if (iso === todayIso()) button.classList.add('is-today');
                if ((input.min && iso < input.min) || (input.max && iso > input.max)) button.disabled = true;
                button.addEventListener('click', () => {
                    selectedDate = iso;
                    monthCursor = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), 1));
                    renderCalendar();
                });
                grid.append(button);
            }
        };
        const syncLabel = () => {
            const current = dateParts(input.value, type);
            const formatted = type === 'time' ? current.time : `${formatDatePart(current.date)}${type === 'datetime-local' && current.time ? ` · ${current.time}` : ''}`;
            label.textContent = formatted || (type === 'time' ? 'Select time' : type === 'date' ? 'Select date' : 'Select date and time');
            trigger.classList.toggle('is-empty', !formatted);
        };
        const close = () => { closeDatePickers(); };
        const open = () => {
            closeDatePickers(wrapper);
            const current = dateParts(input.value, type);
            const hourField = popover.querySelector('[data-pms-picker-hour]');
            const minuteField = popover.querySelector('[data-pms-picker-minute]');
            selectedDate = current.date || todayIso();
            monthCursor = new Date(`${selectedDate}T00:00:00Z`);
            monthCursor = new Date(Date.UTC(monthCursor.getUTCFullYear(), monthCursor.getUTCMonth(), 1));
            renderCalendar();
            if (hourField) hourField.value = (current.time || '00:00').split(':')[0];
            if (minuteField) minuteField.value = (current.time || '00:00').split(':')[1] || '00';
            wrapper.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            popover.hidden = false;
            (popover.querySelector('.pms-calendar__day.is-selected') || hourField || minuteField)?.focus();
        };
        trigger.addEventListener('click', () => wrapper.classList.contains('is-open') ? close() : open());
        popover.querySelector('[data-pms-datetime-close]')?.addEventListener('click', close);
        popover.querySelector('[data-pms-datetime-clear]')?.addEventListener('click', () => {
            input.value = '';
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            syncLabel();
            close();
        });
        popover.querySelector('[data-pms-datetime-apply]')?.addEventListener('click', () => {
            const hour = popover.querySelector('[data-pms-picker-hour]')?.value || '';
            const minute = popover.querySelector('[data-pms-picker-minute]')?.value || '';
            const time = hour ? `${hour}:${minute || '00'}` : '';
            input.value = type === 'datetime-local' ? (selectedDate ? `${selectedDate}T${time || '00:00'}` : '') : type === 'date' ? selectedDate : time;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            syncLabel();
            close();
        });
        popover.querySelector('[data-pms-calendar-prev]')?.addEventListener('click', () => { monthCursor.setUTCMonth(monthCursor.getUTCMonth() - 1); renderCalendar(); });
        popover.querySelector('[data-pms-calendar-next]')?.addEventListener('click', () => { monthCursor.setUTCMonth(monthCursor.getUTCMonth() + 1); renderCalendar(); });
        popover.querySelector('[data-pms-calendar-today]')?.addEventListener('click', () => { selectedDate = todayIso(); monthCursor = new Date(`${selectedDate}T00:00:00Z`); monthCursor = new Date(Date.UTC(monthCursor.getUTCFullYear(), monthCursor.getUTCMonth(), 1)); renderCalendar(); });
        input.addEventListener('change', syncLabel);
        syncLabel();
    });
    enhanceDateTimeInputs();
    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-pms-datetime]')) closeDatePickers();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && event.target.closest('[data-pms-datetime]')) closeDatePickers();
    });

    const closePmsSelects = (except = null) => document.querySelectorAll('[data-pms-select-wrapper]').forEach((wrapper) => {
        if (wrapper !== except) {
            wrapper.classList.remove('is-open');
            wrapper.querySelector('[data-pms-select-trigger]')?.setAttribute('aria-expanded', 'false');
            wrapper.querySelector('[data-pms-select-menu]')?.setAttribute('hidden', '');
        }
    });
    const enhancePmsSelects = () => document.querySelectorAll('[data-pms-select]').forEach((select) => {
        const wrapper = select.closest('[data-pms-select-wrapper]');
        if (!wrapper || wrapper.dataset.pmsSelectReady === '1') return;
        wrapper.dataset.pmsSelectReady = '1';
        select.classList.add('pms-select__native');
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'pms-select__trigger';
        trigger.dataset.pmsSelectTrigger = '';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-controls', `${select.id}-menu`);
        trigger.setAttribute('aria-label', select.getAttribute('aria-label') || select.name.replaceAll('_', ' '));
        trigger.innerHTML = '<span data-pms-select-label></span><svg class="pms-select__chevron" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>';
        wrapper.append(trigger);
        wrapper.querySelector('.form-select__chevron')?.setAttribute('hidden', '');

        const menu = document.createElement('div');
        menu.className = 'pms-select__menu';
        menu.dataset.pmsSelectMenu = '';
        menu.id = `${select.id}-menu`;
        menu.setAttribute('role', 'listbox');
        menu.hidden = true;
        wrapper.append(menu);
        const label = trigger.querySelector('[data-pms-select-label]');
        const isSearchable = wrapper.dataset.pmsSearchable === 'true' || select.options.length > 8;

        const render = (filter = '') => {
            const options = [...select.options].filter((option) => !option.disabled && option.textContent.toLowerCase().includes(filter.toLowerCase()));
            menu.replaceChildren();
            if (isSearchable) {
                const search = document.createElement('input');
                search.type = 'search';
                search.className = 'pms-select__search';
                search.placeholder = 'Search options…';
                search.setAttribute('aria-label', 'Search options');
                search.dataset.pmsSelectSearch = '';
                search.value = filter;
                menu.append(search);
                search.addEventListener('input', () => render(search.value));
            }
            const list = document.createElement('div');
            list.className = 'pms-select__options';
            options.forEach((option) => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'pms-select__option';
                item.dataset.value = option.value;
                item.setAttribute('role', 'option');
                item.setAttribute('aria-selected', option.value === select.value ? 'true' : 'false');
                item.textContent = option.textContent;
                if (option.value === select.value) item.classList.add('is-selected');
                item.addEventListener('click', () => {
                    select.value = option.value;
                    select.dispatchEvent(new Event('input', { bubbles: true }));
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    syncLabel();
                    closePmsSelects();
                    trigger.focus();
                });
                list.append(item);
            });
            if (!options.length) {
                const empty = document.createElement('span');
                empty.className = 'pms-select__empty';
                empty.textContent = 'No options found';
                list.append(empty);
            }
            menu.append(list);
        };
        const syncLabel = () => {
            const option = select.options[select.selectedIndex];
            label.textContent = option?.textContent || 'Select an option';
            trigger.classList.toggle('is-empty', !select.value);
            trigger.disabled = select.disabled;
        };
        const open = () => {
            closePmsSelects(wrapper);
            render();
            wrapper.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            menu.hidden = false;
            (menu.querySelector('[data-pms-select-search]') || menu.querySelector('.pms-select__option.is-selected') || menu.querySelector('.pms-select__option'))?.focus();
        };
        trigger.addEventListener('click', (event) => { event.preventDefault(); wrapper.classList.contains('is-open') ? closePmsSelects() : open(); });
        trigger.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') { event.preventDefault(); open(); }
        });
        menu.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') { event.preventDefault(); closePmsSelects(); trigger.focus(); }
            if (event.key === 'Tab') closePmsSelects();
            if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && event.target.matches('[data-pms-select-search], .pms-select__option')) {
                event.preventDefault();
                const options = [...menu.querySelectorAll('.pms-select__option')];
                if (!options.length) return;
                const current = options.indexOf(event.target);
                const next = current < 0 ? (event.key === 'ArrowDown' ? 0 : options.length - 1) : (current + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
                options[next].focus();
            }
            if (event.key === 'Enter' && event.target.matches('.pms-select__option')) { event.preventDefault(); event.target.click(); }
        });
        select.addEventListener('change', () => { syncLabel(); render(); });
        select.addEventListener('invalid', () => { trigger.setAttribute('aria-invalid', 'true'); trigger.focus(); }, true);
        new MutationObserver(() => { syncLabel(); render(); }).observe(select, { childList: true, subtree: true, attributes: true });
        syncLabel();
        render();
    });
    enhancePmsSelects();
    document.body.classList.add('pms-controls-ready');
    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-pms-select-wrapper]')) closePmsSelects();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && event.target.closest('[data-pms-select-wrapper]')) closePmsSelects();
    });

    const avatarEditor = document.querySelector('[data-avatar-editor]');
    if (avatarEditor) {
        const avatarInput = avatarEditor.querySelector('[data-avatar-input]');
        const removeInput = avatarEditor.querySelector('[data-avatar-remove-input]');
        const preview = avatarEditor.querySelector('[data-avatar-select].profile-avatar-editor__preview');
        const status = avatarEditor.querySelector('[data-avatar-status]');
        const cropModal = document.getElementById('avatar-crop-modal');
        const cropStage = cropModal?.querySelector('.avatar-cropper__stage');
        const canvas = cropModal?.querySelector('[data-avatar-canvas]');
        const zoomInput = cropModal?.querySelector('[data-avatar-zoom]');
        const cropApply = cropModal?.querySelector('[data-avatar-crop-apply]');
        const avatarForm = avatarEditor.closest('form');
        const cropImage = canvas?.getContext('2d');
        const profileHeroAvatar = document.querySelector('.detail-hero > .avatar');
        let image = null;
        let zoom = 1;
        let offsetX = 0;
        let offsetY = 0;
        let dragStart = null;
        let objectUrl = null;

        const syncProfileHero = () => {
            if (!profileHeroAvatar || !preview) return;
            const imagePreview = preview.querySelector('img');
            profileHeroAvatar.classList.toggle('avatar--image', Boolean(imagePreview));
            profileHeroAvatar.replaceChildren(imagePreview ? imagePreview.cloneNode(true) : document.createTextNode(avatarEditor.dataset.initials || 'U'));
        };
        syncProfileHero();

        const drawCrop = () => {
            if (!image || !cropImage || !canvas) return;
            const scale = Math.max(canvas.width / image.naturalWidth, canvas.height / image.naturalHeight) * zoom;
            const width = image.naturalWidth * scale;
            const height = image.naturalHeight * scale;
            cropImage.clearRect(0, 0, canvas.width, canvas.height);
            cropImage.drawImage(image, (canvas.width - width) / 2 + offsetX, (canvas.height - height) / 2 + offsetY, width, height);
        };
        const releaseObjectUrl = () => {
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        };
        const showCropError = (message = 'That image could not be read. Choose another photo.') => {
            releaseObjectUrl();
            image = null;
            if (status) status.textContent = message;
        };
        const openCrop = (file) => {
            if (!file || !file.type.startsWith('image/') || file.size > 5 * 1024 * 1024 || !cropModal) {
                if (status) status.textContent = 'Choose a JPG, PNG or WebP image up to 5 MB.';
                return;
            }
            releaseObjectUrl();
            objectUrl = URL.createObjectURL(file);
            image = new Image();
            image.decoding = 'async';
            image.onload = () => {
                zoom = 1;
                offsetX = 0;
                offsetY = 0;
                if (zoomInput) zoomInput.value = '1';
                openModal(cropModal, avatarEditor.querySelector('[data-avatar-select]'));
                drawCrop();
                if (status) status.textContent = 'Drag to position the photo, then choose Use this photo.';
            };
            image.onerror = showCropError;
            image.src = objectUrl;
        };
        avatarEditor.querySelectorAll('[data-avatar-select]').forEach((button) => button.addEventListener('click', () => avatarInput?.click()));
        avatarInput?.addEventListener('change', () => openCrop(avatarInput.files?.[0]));
        zoomInput?.addEventListener('input', () => { zoom = parseFloat(zoomInput.value) || 1; drawCrop(); });
        cropStage?.addEventListener('pointerdown', (event) => {
            if (!image) return;
            dragStart = { x: event.clientX, y: event.clientY, offsetX, offsetY };
            cropStage.setPointerCapture?.(event.pointerId);
        });
        cropStage?.addEventListener('pointermove', (event) => {
            if (!dragStart) return;
            offsetX = dragStart.offsetX + event.clientX - dragStart.x;
            offsetY = dragStart.offsetY + event.clientY - dragStart.y;
            drawCrop();
        });
        cropStage?.addEventListener('pointerup', () => { dragStart = null; });
        cropStage?.addEventListener('pointercancel', () => { dragStart = null; });
        cropModal?.querySelector('[data-avatar-crop-cancel]')?.addEventListener('click', () => { closeModal(cropModal, { force: true }); image = null; releaseObjectUrl(); });
        cropApply?.addEventListener('click', async () => {
            if (!image || !canvas || !avatarInput || typeof canvas.toDataURL !== 'function') return;
            cropApply.disabled = true;
            if (status) status.textContent = 'Preparing cropped photo…';
            try {
                const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
                const encoded = dataUrl.split(',')[1];
                const bytes = Uint8Array.from(atob(encoded), (character) => character.charCodeAt(0));
                const blob = new Blob([bytes], { type: 'image/jpeg' });
                const croppedFile = new File([blob], 'profile-picture.jpg', { type: 'image/jpeg', lastModified: Date.now() });
                const transfer = new DataTransfer();
                transfer.items.add(croppedFile);
                avatarInput.files = transfer.files;
                if (!avatarInput.files.length) throw new Error('The browser rejected the cropped file.');
                if (removeInput) removeInput.value = '0';
                if (preview) {
                    preview.classList.add('avatar--image');
                    preview.replaceChildren();
                    const imagePreview = document.createElement('img');
                    imagePreview.src = URL.createObjectURL(blob);
                    imagePreview.alt = 'Profile picture preview';
                    preview.append(imagePreview);
                    const camera = document.createElement('span');
                    camera.className = 'profile-avatar-editor__camera';
                    camera.innerHTML = '<svg class="ui-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h3l1.5-2h7L17 7h3a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Z"></path><circle cx="12" cy="13" r="3.5"></circle></svg>';
                    preview.append(camera);
                    syncProfileHero();
                }
                if (status) status.textContent = 'Cropped photo ready. Save picture to apply it.';
                closeModal(cropModal, { force: true });
                image = null;
                releaseObjectUrl();
            } catch (error) {
                showCropError('The crop could not be prepared. Choose the photo again or upload the original image.');
                console.error('HotelDesk profile picture crop failed.', error);
            } finally {
                cropApply.disabled = false;
            }
        });
        avatarEditor.querySelector('[data-avatar-remove]')?.addEventListener('click', () => {
            if (avatarInput) avatarInput.value = '';
            if (removeInput) removeInput.value = '1';
            if (preview) {
                preview.classList.remove('avatar--image');
                preview.replaceChildren(document.createTextNode(avatarEditor.dataset.initials || 'U'));
                const camera = document.createElement('span');
                camera.className = 'profile-avatar-editor__camera';
                camera.innerHTML = '<svg class="ui-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h3l1.5-2h7L17 7h3a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Z"></path><circle cx="12" cy="13" r="3.5"></circle></svg>';
                preview.append(camera);
                syncProfileHero();
            }
            if (status) status.textContent = 'Picture will be removed when you save.';
        });
        avatarForm?.addEventListener('submit', () => {
            const saveButton = avatarForm.querySelector('button[type="submit"]');
            if (saveButton) {
                saveButton.disabled = true;
                saveButton.setAttribute('aria-busy', 'true');
                saveButton.querySelector('.ui-button__label')?.replaceChildren(document.createTextNode('Saving…'));
            }
            if (status) status.textContent = 'Uploading profile picture…';
        });
    }

    const fullscreenToggle = document.querySelector('[data-fullscreen-toggle]');
    const syncFullscreenToggle = () => {
        const active = Boolean(document.fullscreenElement);
        fullscreenToggle?.querySelector('[data-fullscreen-enter]')?.toggleAttribute('hidden', active);
        fullscreenToggle?.querySelector('[data-fullscreen-exit]')?.toggleAttribute('hidden', !active);
        const label = active ? 'Exit fullscreen' : 'Enter fullscreen';
        fullscreenToggle?.setAttribute('aria-label', label);
        if (fullscreenToggle) fullscreenToggle.dataset.tooltip = label;
    };
    fullscreenToggle?.addEventListener('click', async () => {
        try {
            if (document.fullscreenElement) await document.exitFullscreen?.();
            else await document.documentElement.requestFullscreen?.();
        } catch (error) { /* Fullscreen can be denied by the browser or embedding host. */ }
        syncFullscreenToggle();
    });
    document.addEventListener('fullscreenchange', syncFullscreenToggle);
    syncFullscreenToggle();

    const sidebar = document.querySelector('[data-sidebar]');
    const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
    const syncSidebarToggleLabel = () => {
        const label = window.matchMedia('(max-width: 900px)').matches
            ? 'Open navigation'
            : body.classList.contains('sidebar-collapsed') ? 'Expand navigation' : 'Collapse navigation';
        sidebarToggle?.setAttribute('aria-label', label);
        if (sidebarToggle) sidebarToggle.dataset.tooltip = label;
    };
    sidebarToggle?.addEventListener('click', () => {
        if (window.matchMedia('(max-width: 900px)').matches) {
            sidebar?.classList.add('is-open');
            document.querySelector('[data-sidebar-backdrop]')?.classList.add('is-visible');
            body.classList.add('drawer-open');
        } else {
            body.classList.toggle('sidebar-collapsed');
            localStorage.setItem('hotel-sidebar-collapsed', body.classList.contains('sidebar-collapsed') ? '1' : '0');
        }
        syncSidebarToggleLabel();
    });
    document.querySelector('[data-sidebar-close]')?.addEventListener('click', closeSidebar);
    document.querySelector('[data-sidebar-backdrop]')?.addEventListener('click', closeSidebar);
    sidebar?.querySelectorAll('.nav-item').forEach((link) => link.addEventListener('click', () => {
        if (window.matchMedia('(max-width: 900px)').matches) closeSidebar();
    }));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeSidebar();
            closeDropdowns();
        }
    });
    if (localStorage.getItem('hotel-sidebar-collapsed') === '1' && window.matchMedia('(min-width: 901px)').matches) body.classList.add('sidebar-collapsed');
    syncSidebarToggleLabel();

    document.querySelectorAll('[data-dropdown-toggle]').forEach((toggle) => toggle.addEventListener('click', (event) => {
        event.stopPropagation();
        const dropdown = toggle.closest('[data-dropdown]');
        const menu = dropdown?.querySelector('[data-dropdown-menu]');
        const wasOpen = dropdown?.classList.contains('is-open');
        closeDropdowns();
        if (!wasOpen && dropdown && menu) {
            dropdown.classList.add('is-open');
            toggle.setAttribute('aria-expanded', 'true');
            menu.hidden = false;
        }
    }));

    const searchInput = document.querySelector('[data-global-search-input]');
    const focusSearch = (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            searchInput?.focus();
        }
    };
    document.addEventListener('keydown', focusSearch);
    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-dropdown]')) closeDropdowns();
    });

    const focusableSelector = 'a[href], area[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    const visibleModals = () => [...document.querySelectorAll('[data-modal]:not([hidden])')];
    const activeModal = () => visibleModals().at(-1);
    const syncModalScrollLock = () => body.classList.toggle('modal-open', visibleModals().length > 0);
    const focusModal = (modal) => {
        const first = modal.querySelector(focusableSelector);
        (first || modal.querySelector('.modal__dialog'))?.focus();
    };
    const triggerModalAttention = (modal) => {
        const dialog = modal.querySelector('.modal__dialog');
        if (!dialog) return;
        dialog.classList.remove('modal-attention');
        void dialog.offsetWidth;
        dialog.classList.add('modal-attention');
        window.setTimeout(() => dialog.classList.remove('modal-attention'), 650);
    };
    const openModal = (modal, trigger = document.activeElement) => {
        if (!modal) return;
        modal.__previousFocus = trigger instanceof HTMLElement && trigger !== modal ? trigger : null;
        modal.hidden = false;
        modal.classList.remove('is-open');
        void modal.offsetWidth;
        modal.classList.add('is-open');
        syncModalScrollLock();
        focusModal(modal);
    };
    const closeModal = (modal, { force = false, restoreFocus = true } = {}) => {
        if (!modal || modal.hidden) return;
        if (!force && modal.dataset.modalDirtyGuard === 'true' && [...modal.querySelectorAll('form')].some((form) => form.dataset.modalDirty === '1')) {
            openConfirmPrompt({
                title: 'Discard unsaved changes?',
                message: 'Your changes have not been saved. Keep editing or discard them?',
                actionLabel: 'Discard',
                onConfirm: () => closeModal(modal, { force: true }),
            });
            return;
        }
        modal.hidden = true;
        modal.classList.remove('is-open');
        syncModalScrollLock();
        if (restoreFocus && modal.__previousFocus instanceof HTMLElement && document.contains(modal.__previousFocus)) modal.__previousFocus.focus();
    };
    const requestModalClose = (modal) => {
        if (!modal) return;
        if (modal.id === 'global-confirm-modal') closeConfirmModal();
        else closeModal(modal);
    };
    document.querySelectorAll('[data-modal-open]').forEach((button) => button.addEventListener('click', () => {
        openModal(document.getElementById(button.dataset.modalOpen), button);
    }));
    document.querySelectorAll('[data-modal-auto-open]').forEach((modal) => openModal(modal, null));
    document.querySelectorAll('[data-modal] form').forEach((form) => {
        form.dataset.modalDirty = '0';
        form.addEventListener('input', (event) => { if (!event.target.matches('[data-pms-select-search]')) form.dataset.modalDirty = '1'; });
        form.addEventListener('change', () => { form.dataset.modalDirty = '1'; });
    });
    document.querySelectorAll('[data-modal] .modal-form-footer a.ui-button--secondary').forEach((link) => link.dataset.modalClose = '');
    document.querySelectorAll('[data-modal] .modal__backdrop').forEach((backdrop) => backdrop.addEventListener('click', (event) => {
        event.preventDefault();
        const modal = backdrop.closest('[data-modal]');
        if (modal?.dataset.modalStaticBackdrop === 'true') triggerModalAttention(modal);
        else requestModalClose(modal);
    }));
    document.querySelectorAll('[data-planning-group-toggle]').forEach((toggle) => {
        const key = toggle.dataset.planningGroupToggle;
        const storageKey = `hotel-planning-group:${key}`;
        const setCollapsed = (collapsed) => {
            document.querySelectorAll(`[data-planning-group-row="${CSS.escape(key)}"]`).forEach((row) => { row.hidden = collapsed; });
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            toggle.setAttribute('aria-label', `${collapsed ? 'Expand' : 'Collapse'} room group`);
            toggle.dataset.tooltip = `${collapsed ? 'Expand' : 'Collapse'} ${key.replaceAll('-', ' ')}`;
            toggle.textContent = collapsed ? '▸' : '▾';
            sessionStorage.setItem(storageKey, collapsed ? '1' : '0');
        };
        setCollapsed(sessionStorage.getItem(storageKey) === '1');
        toggle.addEventListener('click', () => setCollapsed(toggle.getAttribute('aria-expanded') === 'true'));
    });
    const planningRefresh = document.querySelector('[data-planning-refresh]');
    const planningCard = document.querySelector('[data-planning-card]');
    const planningFeedback = document.querySelector('[data-planning-feedback]');
    planningRefresh?.addEventListener('click', async () => {
        if (planningRefresh.disabled) return;
        const endpoint = planningRefresh.dataset.planningRefresh;
        if (!endpoint) return;
        planningRefresh.disabled = true;
        planningCard?.classList.add('is-loading');
        const planningSkeleton = document.querySelector('[data-planning-skeleton]');
        if (planningSkeleton) planningSkeleton.hidden = false;
        if (planningFeedback) {
            planningFeedback.hidden = false;
            planningFeedback.className = 'planning-feedback';
            planningFeedback.textContent = 'Refreshing room availability…';
        }
        try {
            const response = await fetch(endpoint, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error(`Refresh failed (${response.status})`);
            await response.json();
            window.location.reload();
        } catch (error) {
            planningCard?.classList.remove('is-loading');
            if (planningSkeleton) planningSkeleton.hidden = true;
            planningRefresh.disabled = false;
            if (planningFeedback) {
                planningFeedback.hidden = false;
                planningFeedback.className = 'planning-feedback planning-feedback--error';
                planningFeedback.textContent = 'Room planning could not be refreshed. Your current data is still shown; try again or check the connection.';
            }
        }
    });
    let pendingConfirmForm = null;
    let pendingConfirmAction = null;
    const confirmModal = document.getElementById('global-confirm-modal');
    const closeConfirmModal = () => {
        if (confirmModal) closeModal(confirmModal, { force: true });
        pendingConfirmForm = null;
        pendingConfirmAction = null;
    };
    const openConfirmPrompt = ({ title, message, actionLabel = 'Continue', onConfirm = null }) => {
        if (!confirmModal) return;
        pendingConfirmAction = onConfirm;
        confirmModal.querySelector('.modal__header h2')?.replaceChildren(document.createTextNode(title));
        confirmModal.querySelector('[data-confirm-message]')?.replaceChildren(document.createTextNode(message));
        const confirmButton = confirmModal.querySelector('[data-confirm-submit]');
        if (confirmButton) confirmButton.replaceChildren(document.createTextNode(actionLabel));
        openModal(confirmModal);
        confirmButton?.focus();
    };
    const openConfirmModal = (form) => openConfirmPrompt({
        title: form.dataset.confirmTitle || 'Please confirm',
        message: form.dataset.confirm || 'Are you sure you want to continue?',
        actionLabel: form.dataset.confirmLabel || 'Continue',
        onConfirm: () => {
            form.dataset.draftSubmitted = '1';
            HTMLFormElement.prototype.submit.call(form);
        },
    });
    document.querySelectorAll('[data-modal-close]').forEach((button) => button.addEventListener('click', (event) => {
        const modal = button.closest('[data-modal]');
        const href = button.getAttribute('href');
        if (href && modal && modal.dataset.modalDirtyGuard === 'true' && [...modal.querySelectorAll('form')].some((form) => form.dataset.modalDirty === '1')) {
            event.preventDefault();
            openConfirmPrompt({
                title: 'Discard unsaved changes?',
                message: 'Your changes have not been saved. Keep editing or discard them?',
                actionLabel: 'Discard',
                onConfirm: () => { closeModal(modal, { force: true }); window.location.assign(href); },
            });
            return;
        }
        if (href) return;
        requestModalClose(modal);
    }));
    confirmModal?.querySelector('[data-confirm-submit]')?.addEventListener('click', () => {
        const action = pendingConfirmAction;
        const form = pendingConfirmForm;
        closeConfirmModal();
        if (action) action();
        else if (form) { form.dataset.draftSubmitted = '1'; HTMLFormElement.prototype.submit.call(form); }
    });

    const isDestructiveForm = (form) => form.hasAttribute('data-confirm')
        || form.querySelector('input[name="_method"][value="DELETE"]')
        || /\/(?:destroy|delete|void|cancel|no-show)(?:\/|$)/i.test(form.action);
    document.querySelectorAll('form').forEach((form) => {
        const method = (form.getAttribute('method') || 'GET').toUpperCase();
        const isModalForm = form.closest('[data-modal]') && !form.closest('#global-confirm-modal');
        const isOperationalForm = form.matches('[data-payment-form], [data-reservation-form]');
        if (method === 'POST' && (isModalForm || isOperationalForm) && !isDestructiveForm(form)) form.dataset.draftForm = '1';
        if (method === 'POST' && isDestructiveForm(form) && !form.dataset.confirm) {
            const action = /\/void(?:\/|$)/i.test(form.action) ? 'Void this payment? This will restore the reservation balance.'
                : /\/cancel(?:\/|$)/i.test(form.action) ? 'Cancel this reservation? The room will be released.'
                    : /\/no-show(?:\/|$)/i.test(form.action) ? 'Mark this reservation as a no-show?'
                        : 'Delete this record? This action cannot be undone.';
            form.dataset.confirm = action;
            form.dataset.confirmTitle = 'Please confirm';
            form.dataset.confirmLabel = /delete/i.test(action) ? 'Delete' : 'Continue';
        }
    });

    const draftStoragePrefix = 'hotel-form-draft:';
    const pendingDraftSubmissionStorageKey = 'hotel-form-draft-pending-submission';
    const draftExcludedTypes = new Set(['button', 'submit', 'reset', 'file', 'password', 'hidden']);
    const draftKeyFor = (form) => form.dataset.draftKey || `${draftStoragePrefix}${window.location.pathname}:${form.id || form.action}`;
    const clearCompletedDraftSubmission = () => {
        try {
            const pending = JSON.parse(sessionStorage.getItem(pendingDraftSubmissionStorageKey) || 'null');
            if (!pending?.key || pending.sourcePath === window.location.pathname) return;
            localStorage.removeItem(pending.key);
            sessionStorage.removeItem(pendingDraftSubmissionStorageKey);
        } catch (error) {
            sessionStorage.removeItem(pendingDraftSubmissionStorageKey);
        }
    };
    const markDraftSubmission = (form) => {
        if (form.dataset.draftLifecycle !== 'task') return;
        try {
            sessionStorage.setItem(pendingDraftSubmissionStorageKey, JSON.stringify({
                key: draftKeyFor(form),
                sourcePath: window.location.pathname,
            }));
        } catch (error) { /* Draft lifecycle is best effort. */ }
    };
    const draftStatusFor = (form) => form.querySelector('[data-draft-status]');
    const setDraftStatus = (form, text) => {
        const status = draftStatusFor(form);
        if (status) status.textContent = text;
    };
    const readDraftFields = (form) => {
        const values = {};
        form.querySelectorAll('input[name], select[name], textarea[name]').forEach((field) => {
            if (draftExcludedTypes.has((field.type || '').toLowerCase()) || field.name === '_token' || field.name === '_method') return;
            if ((field.type === 'radio' || field.type === 'checkbox') && !field.checked) return;
            if (field.type === 'select-multiple') values[field.name] = [...field.selectedOptions].map((option) => option.value);
            else values[field.name] = field.value;
        });
        return values;
    };
    const restoreDraftFields = (form, values) => {
        Object.entries(values || {}).forEach(([name, value]) => {
            form.querySelectorAll(`[name="${CSS.escape(name)}"]`).forEach((field) => {
                if (field.type === 'checkbox' || field.type === 'radio') field.checked = Array.isArray(value) ? value.includes(field.value) : String(value) === field.value;
                else if (field.type === 'select-multiple') [...field.options].forEach((option) => { option.selected = Array.isArray(value) && value.includes(option.value); });
                else field.value = value;
            });
        });
    };
    const clearDraft = (form) => {
        localStorage.removeItem(draftKeyFor(form));
        setDraftStatus(form, '');
    };
    clearCompletedDraftSubmission();
    document.querySelectorAll('[data-draft-form]').forEach((form) => {
        let saveTimer = null;
        const saveDraft = () => {
            window.clearTimeout(saveTimer);
            saveTimer = window.setTimeout(() => {
                try {
                    localStorage.setItem(draftKeyFor(form), JSON.stringify(readDraftFields(form)));
                    setDraftStatus(form, 'Saved as draft');
                } catch (error) { /* Draft persistence is best effort. */ }
        }, 350);
        };
        try {
            const stored = localStorage.getItem(draftKeyFor(form));
            if (stored) {
                restoreDraftFields(form, JSON.parse(stored));
                setDraftStatus(form, 'Draft restored');
            }
        } catch (error) { /* Ignore malformed or unavailable local drafts. */ }
        form.addEventListener('input', saveDraft);
        form.addEventListener('change', saveDraft);
        form.addEventListener('submit', () => {
            if (form.dataset.draftLifecycle === 'task') markDraftSubmission(form);
            else if (form.dataset.draftSubmitted !== '1') clearDraft(form);
        });
    });

    document.querySelectorAll('[data-toast]').forEach((toast) => {
        toast.querySelector('[data-toast-close]')?.addEventListener('click', () => toast.remove());
        window.setTimeout(() => toast.remove(), 5000);
    });

    document.querySelectorAll('[data-refresh]').forEach((button) => button.addEventListener('click', () => {
        const pageSkeleton = document.querySelector('[data-app-page-skeleton]');
        if (pageSkeleton) {
            pageSkeleton.hidden = false;
            pageSkeleton.setAttribute('aria-hidden', 'false');
            body.classList.add('is-page-loading');
        }
        window.location.reload();
    }));
    document.querySelectorAll('[data-print]').forEach((button) => button.addEventListener('click', () => window.print()));
    document.querySelectorAll('[data-confirm]').forEach((form) => form.addEventListener('submit', (event) => {
        event.preventDefault();
        openConfirmModal(form);
    }));
    document.querySelectorAll('[data-reservation-form]').forEach((form) => {
        const checkIn = form.querySelector('[data-reservation-check-in]');
        const checkOut = form.querySelector('[data-reservation-check-out]');
        const room = form.querySelector('[data-room-select]');
        const rate = form.querySelector('[data-nightly-rate]');
        const total = form.querySelector('[data-total-amount]');
        const availabilityError = form.querySelector('[data-availability-error]');
        let availabilityController = null;
        let availabilityTimer = null;
        const showAvailabilityError = (message = '') => {
            if (!availabilityError) return;
            availabilityError.textContent = message;
            availabilityError.hidden = message === '';
        };
        const refreshRooms = async () => {
            if (!checkIn?.value || !checkOut?.value || !room) return;
            if (availabilityTimer) window.clearTimeout(availabilityTimer);
            availabilityController?.abort();
            availabilityController = new AbortController();
            const params = new URLSearchParams({ check_in: checkIn.value, check_out: checkOut.value });
            if (form.dataset.ignoreReservationId) params.set('ignore_reservation_id', form.dataset.ignoreReservationId);
            showAvailabilityError();
            availabilityTimer = window.setTimeout(async () => {
              try {
                const response = await fetch(`${form.dataset.availabilityUrl}?${params.toString()}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: availabilityController.signal });
                if (!response.ok) throw new Error(`Availability request failed with status ${response.status}.`);
                const payload = await response.json();
                const rooms = Array.isArray(payload) ? payload : payload?.data;
                if (!Array.isArray(rooms)) throw new Error('Availability response was not a room list.');
                const selected = room.value;
                room.innerHTML = '<option value="">Select a room</option>';
                rooms.forEach((item) => {
                    const option = new Option(item.label, item.id, false, String(item.id) === String(selected));
                    option.dataset.rate = item.rate; option.dataset.capacity = item.capacity; room.add(option);
                });
                if (selected && !room.value) room.insertAdjacentHTML('beforeend', `<option value="${selected}" selected>Current room</option>`);
                const selectedOption = room.options[room.selectedIndex];
                if (selectedOption?.dataset.rate && (!rate.value || rate.dataset.autoRate === '1')) { rate.value = selectedOption.dataset.rate; rate.dataset.autoRate = '1'; }
                updateTotal();
              } catch (error) {
                if (error.name === 'AbortError') return;
                showAvailabilityError('Room availability could not be loaded. Check the connection and try again.');
                console.error('HotelDesk room availability request failed.', error);
              }
            }, 150);
        };
        const updateTotal = () => {
            if (!checkIn?.value || !checkOut?.value || !rate || !total) return;
            const nights = Math.max(0, Math.round((new Date(checkOut.value.split('T')[0]) - new Date(checkIn.value.split('T')[0])) / 86400000));
            total.value = (nights * (parseFloat(rate.value) || 0)).toFixed(2);
        };
        [checkIn, checkOut].forEach((input) => input?.addEventListener('change', () => { updateTotal(); refreshRooms(); }));
        rate?.addEventListener('input', () => { rate.dataset.autoRate = '0'; updateTotal(); });
        room?.addEventListener('change', () => {
            const option = room.options[room.selectedIndex];
            if (option?.dataset.rate && (!rate.value || rate.dataset.autoRate === '1')) { rate.value = option.dataset.rate; rate.dataset.autoRate = '1'; }
            updateTotal();
        });
        updateTotal(); refreshRooms();
    });
    document.querySelectorAll('[data-payment-form]').forEach((form) => {
        const reservation = form.querySelector('[data-payment-reservation]');
        const amount = form.querySelector('[data-payment-amount]');
        const summary = form.querySelector('[data-payment-summary]');
        const money = (value) => new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(parseFloat(value) || 0);
        const updatePaymentSummary = () => {
            const option = reservation?.options[reservation.selectedIndex];
            const total = parseFloat(option?.dataset.total) || 0;
            const paid = parseFloat(option?.dataset.paid) || 0;
            const entered = parseFloat(amount?.value) || 0;
            const balance = Math.max(0, total - paid - entered);
            summary?.querySelector('[data-payment-total]')?.replaceChildren(document.createTextNode(money(total)));
            summary?.querySelector('[data-payment-paid]')?.replaceChildren(document.createTextNode(money(paid)));
            summary?.querySelector('[data-payment-balance]')?.replaceChildren(document.createTextNode(money(balance)));
            if (amount && option && !amount.dataset.userEdited) amount.value = (parseFloat(option.dataset.balance) || 0).toFixed(2);
        };
        reservation?.addEventListener('change', () => { if (amount) amount.dataset.userEdited = ''; updatePaymentSummary(); });
        amount?.addEventListener('input', () => { amount.dataset.userEdited = '1'; updatePaymentSummary(); });
        updatePaymentSummary();
    });
    document.querySelectorAll('[data-submit-lock]').forEach((form) => form.addEventListener('submit', () => {
        const button = form.querySelector('button[type="submit"]');
        if (button) { button.disabled = true; button.dataset.originalText = button.textContent; button.textContent = 'Processing…'; }
    }));
    document.querySelectorAll('[data-live-clock]').forEach((clock) => {
        const timezone = clock.dataset.timezone || Intl.DateTimeFormat().resolvedOptions().timeZone;
        const tick = () => { clock.textContent = new Intl.DateTimeFormat(undefined, { month: 'short', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', timeZone: timezone }).format(new Date()); };
        tick();
        window.setInterval(tick, 30000);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeDropdowns();
            // Create/edit forms are intentionally X-only. Escape should never
            // discard an in-progress form or bypass its dirty-state guard.
            if (activeModal()) event.preventDefault();
            closeSidebar();
        }
        if (event.key === 'Tab') {
            const modal = activeModal();
            if (!modal) return;
            const focusable = [...modal.querySelectorAll(focusableSelector)].filter((element) => element.offsetParent !== null);
            if (!focusable.length) {
                event.preventDefault();
                modal.querySelector('.modal__dialog')?.focus();
                return;
            }
            const first = focusable[0];
            const last = focusable.at(-1);
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });
});
