// Version this deliberately whenever a site-wide layout changes. Some users
// installed TavsScore as a PWA, so a new version clears the old HTML/CSS cache
// instead of letting an old page survive after deployment.
const CACHE = 'tavsscore-v2';

const PRECACHE = ['/', '/picks', '/predictions', '/live', '/favicon.svg', '/manifest.json'];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE).then(cache => cache.addAll(PRECACHE)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET') return;
    if (event.request.url.includes('/api/')) return;
    event.respondWith(
        fetch(event.request)
            .then(response => {
                caches.open(CACHE).then(cache => cache.put(event.request, response.clone()));
                return response;
            })
            .catch(() => caches.match(event.request).then(r => r || caches.match('/')))
    );
});
