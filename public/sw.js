self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    let data = { title: 'MiniMon', body: 'Status-Aenderung' };
    try {
        if (event.data) {
            data = event.data.json();
        }
    } catch (e) {
        console.error('[SW] Push data parse error:', e);
    }

    const options = {
        body: data.body || '',
        icon: data.icon || '/assets/images/logo.svg',
        badge: '/assets/images/logo.svg',
        tag: data.tag || 'minimon-alert',
        data: { url: data.url || '/backend' },
        vibrate: [100, 50, 100],
        requireInteraction: true
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'MiniMon', options)
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    let url = '/backend';
    if (event.notification.data && event.notification.data.url) {
        url = event.notification.data.url;
    }
    if (url.startsWith('/')) {
        url = self.location.origin + url;
    }

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if (client.url.includes('/backend') && 'focus' in client) {
                    return client.focus();
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(url);
            }
        })
    );
});
