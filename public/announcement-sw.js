self.addEventListener('push', (event) => {
    let payload = {};
    try { payload = event.data ? event.data.json() : {}; } catch (error) { payload = {}; }
    const title = payload.title || 'New HotelDesk announcement';
    const options = {
        body: payload.body || 'You have a new announcement.',
        icon: '/assets/branding/lodgix-mark.png',
        data: { url: payload.url || '/announcements/dashboard' },
    };
    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/announcements/dashboard';
    event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
        const existing = windows.find((client) => 'focus' in client);
        return existing ? existing.focus().then(() => existing.navigate(url)) : clients.openWindow(url);
    }));
});
