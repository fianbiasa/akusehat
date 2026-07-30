// Minimal "offline shell" service worker (Phase 13 checklist) - this is
// an Inertia SPA with server-rendered page data, not a fully offline-
// capable client app, so this deliberately does NOT try to cache API
// responses or page data (health data is sensitive; serving it stale
// from a cache while "offline" would be actively misleading). It only
// caches the static, content-hashed Vite build assets (safe forever,
// since a new build gets a new filename) and a small offline fallback
// page shown when navigation fails with no network.

const CACHE_VERSION = 'akusehat-shell-v1';
const OFFLINE_URL = '/offline.html';
const PRECACHE_URLS = [OFFLINE_URL, '/manifest.json', '/icon.svg', '/favicon.ico'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_VERSION).then((cache) => cache.addAll(PRECACHE_URLS)).then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((key) => key !== CACHE_VERSION).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // Content-hashed build assets never change for a given filename -
    // safe to serve cache-first and cache indefinitely.
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then((response) => {
                const clone = response.clone();
                caches.open(CACHE_VERSION).then((cache) => cache.put(request, clone));
                return response;
            })),
        );
        return;
    }

    // Full-page navigations: try the network first (always want fresh
    // server-rendered/Inertia data when online), fall back to the
    // offline shell only when the network is genuinely unreachable.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL)),
        );
        return;
    }

    // Everything else (API calls, Inertia XHR requests) passes through
    // untouched - never served from cache.
});
