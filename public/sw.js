const CACHE_NAME = 'hrms-v1';
const OFFLINE_URL = '/offline.html';

// Static assets to pre-cache
const PRECACHE_ASSETS = [
    OFFLINE_URL,
    '/manifest.json'
];

// Install: pre-cache offline page
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => cache.addAll(PRECACHE_ASSETS))
    );
    self.skipWaiting();
});

// Activate: clean old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
        )
    );
    self.clients.claim();
});

// Fetch strategy:
// - Same-origin HTML → network-first, fall back to offline page
// - CDN / fonts / scripts → cache-first (stale-while-revalidate)
// - API / AJAX → network-only (never cache)
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET and cross-origin API/AJAX calls
    if (request.method !== 'GET') return;
    if (url.pathname.startsWith('/ajax/') || url.pathname.startsWith('/api/')) return;

    // HTML navigation → network-first with offline fallback
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() =>
                caches.match(OFFLINE_URL)
            )
        );
        return;
    }

    // Static CDN assets → cache-first
    if (
        url.hostname !== location.hostname &&
        (url.pathname.endsWith('.css') || url.pathname.endsWith('.js') || url.pathname.includes('fonts'))
    ) {
        event.respondWith(
            caches.match(request).then(cached => {
                if (cached) return cached;
                return fetch(request).then(response => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then(cache => cache.put(request, clone));
                    }
                    return response;
                });
            })
        );
        return;
    }

    // Everything else → network-only
});
