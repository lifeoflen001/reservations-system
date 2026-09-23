document.addEventListener('DOMContentLoaded', () => {
    const companyWide = document.querySelector('[data-company-wide-toggle]');
    const picker = document.querySelector('[data-department-picker]');
    const syncAudience = () => {
        if (!companyWide || !picker) return;
        picker.hidden = companyWide.checked;
        picker.querySelectorAll('input').forEach((input) => { input.disabled = companyWide.checked; });
    };
    companyWide?.addEventListener('change', syncAudience);
    syncAudience();

    document.querySelectorAll('[data-rich-command]').forEach((button) => {
        button.addEventListener('click', () => {
            const editor = button.closest('.form-field')?.querySelector('.announcement-content-editor');
            if (!editor) return;
            const command = button.dataset.richCommand;
            const start = editor.selectionStart;
            const end = editor.selectionEnd;
            const selection = editor.value.slice(start, end);
            if (command === 'createLink') {
                const url = window.prompt('Link URL');
                if (!url) return;
                editor.setRangeText('<a href="' + url.replace(/"/g, '&quot;') + '">' + (selection || url) + '</a>', start, end, 'end');
            } else if (command === 'bold' || command === 'italic') {
                const tag = command === 'bold' ? 'strong' : 'em';
                editor.setRangeText('<' + tag + '>' + selection + '</' + tag + '>', start, end, 'end');
            } else if (command === 'insertUnorderedList') {
                editor.setRangeText('<ul><li>' + selection.replace(/\n/g, '</li><li>') + '</li></ul>', start, end, 'end');
            }
            editor.dispatchEvent(new Event('input', { bubbles: true }));
            editor.focus();
        });
    });

    document.querySelectorAll('[data-browser-alerts]').forEach((button) => {
        button.addEventListener('click', async () => {
            if (!('Notification' in window)) {
                button.textContent = 'Browser alerts unavailable';
                return;
            }
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                button.textContent = permission === 'denied' ? 'Browser alerts blocked' : 'Enable browser alerts';
                return;
            }
            button.textContent = 'Browser alerts enabled';
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
            try {
                const registration = await navigator.serviceWorker.register('/announcement-sw.js');
                const subscription = await registration.pushManager.getSubscription();
                if (!subscription) return;
                const key = subscription.getKey('p256dh');
                const auth = subscription.getKey('auth');
                const encode = (value) => value ? btoa(String.fromCharCode(...new Uint8Array(value))) : null;
                await fetch('/announcements/push-subscriptions', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', Accept: 'application/json' },
                    body: JSON.stringify({ endpoint: subscription.endpoint, public_key: encode(key), auth_token: encode(auth), content_encoding: subscription.options?.applicationServerKey ? 'aes128gcm' : null }),
                });
            } catch (error) {
                console.warn('Browser alert subscription is unavailable.', error);
            }
        });
    });
});
