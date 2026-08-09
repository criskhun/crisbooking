const CACHE_VERSION = 'davao-rent-zone-v8';
const OFFLINE_URL = new URL('./offline.html', self.registration.scope).href;
const PRECACHE_URLS = [
    './offline.html',
    './manifest.webmanifest',
    './css/app.css',
    './css/mobile-shell-v5.css',
    './css/mobile-menu-v6.css',
    './css/mobile-form-v7.css',
    './css/profile-controls-v8.css',
    './css/address-combobox-v9.css',
    './js/app.js',
    './js/address-combobox-v9.js',
    './js/maps.js',
    './js/mobile-shell-v5.js',
    './js/pwa.js',
    './images/davao-rent-zone-logo-mark-flat.png',
    './icons/icon-192.png',
    './icons/icon-512.png',
    './icons/icon-maskable-192.png',
    './icons/icon-maskable-512.png',
    './apple-touch-icon.png',
    './favicon.ico',
    './favicon.svg',
].map((path) => new URL(path, self.registration.scope).href);

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(CACHE_VERSION);
        await Promise.allSettled(PRECACHE_URLS.map((url) => cache.add(new Request(url, { cache: 'reload' }))));
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const cacheNames = await caches.keys();
        await Promise.all(cacheNames
            .filter((cacheName) => cacheName.startsWith('davao-rent-zone-') && cacheName !== CACHE_VERSION)
            .map((cacheName) => caches.delete(cacheName)));
        await self.clients.claim();
    })());
});

const isSafeStaticAsset = (url) => {
    const scopePath = new URL(self.registration.scope).pathname;
    if (!url.pathname.startsWith(scopePath)) return false;

    const relativePath = url.pathname.slice(scopePath.length);
    return ['css/', 'js/', 'images/', 'icons/'].some((directory) => relativePath.startsWith(directory))
        || ['manifest.webmanifest', 'apple-touch-icon.png', 'favicon.ico', 'favicon.svg'].includes(relativePath);
};

const updateStaticCache = async (request, response) => {
    if (response.ok && response.type === 'basic') {
        const cache = await caches.open(CACHE_VERSION);
        await cache.put(request, response.clone());
    }
    return response;
};

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(async () => (await caches.match(OFFLINE_URL)) || Response.error()));
        return;
    }

    if (url.origin !== self.location.origin || !isSafeStaticAsset(url)) return;

    const prefersFreshAsset = ['script', 'style', 'worker'].includes(request.destination)
        || url.pathname.endsWith('/manifest.webmanifest');

    if (prefersFreshAsset) {
        event.respondWith(fetch(request)
            .then((response) => updateStaticCache(request, response))
            .catch(async () => (await caches.match(request, { ignoreSearch: true })) || Response.error()));
        return;
    }

    event.respondWith(caches.match(request, { ignoreSearch: true }).then(async (cachedResponse) => {
        if (cachedResponse) return cachedResponse;
        return updateStaticCache(request, await fetch(request));
    }));
});
