/* /public/igbo-calendar/service-worker.js */
'use strict';

/**
 * Strong PWA strategy (recommended):
 * - Navigations (HTML): Network-first, cache fallback (ignoreSearch true)
 * - Same-origin assets: Stale-while-revalidate
 * - Cross-origin: pass-through
 */

const CACHE_NAME = 'igbo-calendar-v11'; // bump on every deploy that changes HTML/CSS/JS

const CORE_ASSETS = [
  '/igbo-calendar/',
  '/igbo-calendar/index.php',
  '/igbo-calendar/manifest.json',
  '/igbo-calendar/igbo-calendar.css',
  '/lib/css/ui.css',
  '/lib/css/subjects.css',
  '/igbo-calendar/icons/icon-48.png',
  '/igbo-calendar/icons/icon-72.png',
  '/igbo-calendar/icons/icon-192.png',
  '/igbo-calendar/icons/icon-512.png',
];

const isSameOrigin = (url) => url.origin === self.location.origin;
const isNavigation = (req) =>
  req.mode === 'navigate' || (req.headers.get('accept') || '').includes('text/html');

self.addEventListener('install', (event) => {
  event.waitUntil((async () => {
    const cache = await caches.open(CACHE_NAME);
    await cache.addAll(CORE_ASSETS);
    self.skipWaiting();
  })());
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.map((k) => (k !== CACHE_NAME ? caches.delete(k) : Promise.resolve())));
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);

  // Cross-origin: do not cache
  if (!isSameOrigin(url)) return;

  // HTML navigations: network-first, fallback to cache (ignoreSearch)
  if (isNavigation(req)) {
    event.respondWith((async () => {
      const cache = await caches.open(CACHE_NAME);

      try {
        const fresh = await fetch(req, { cache: 'no-store' });
        if (fresh && fresh.ok) {
          // Cache a stable key (path only) to prevent query-string cache spam
          const stableKey = url.pathname === '/igbo-calendar/' ? '/igbo-calendar/' : url.pathname;
          cache.put(stableKey, fresh.clone());
        }
        return fresh;
      } catch (e) {
        // Offline: try cached navigation ignoring query
        const cachedExact = await cache.match(url.pathname);
        if (cachedExact) return cachedExact;

        const cachedShell = await cache.match('/igbo-calendar/');
        if (cachedShell) return cachedShell;

        return new Response('Offline', {
          status: 503,
          headers: { 'Content-Type': 'text/plain; charset=utf-8' }
        });
      }
    })());
    return;
  }

  // Assets: stale-while-revalidate
  event.respondWith((async () => {
    const cache = await caches.open(CACHE_NAME);
    const cached = await cache.match(req);

    const fetchPromise = (async () => {
      try {
        const fresh = await fetch(req);
        if (fresh && fresh.ok) cache.put(req, fresh.clone());
        return fresh;
      } catch (e) {
        return null;
      }
    })();

    if (cached) {
      fetchPromise.catch(() => {});
      return cached;
    }

    const fresh = await fetchPromise;
    if (fresh) return fresh;

    return new Response('Offline', {
      status: 503,
      headers: { 'Content-Type': 'text/plain; charset=utf-8' }
    });
  })());
});

self.addEventListener('message', (event) => {
  if (!event.data) return;

  if (event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
    return;
  }

  if (event.data.type === 'REFRESH_CORE') {
    event.waitUntil((async () => {
      const cache = await caches.open(CACHE_NAME);
      await cache.addAll(CORE_ASSETS);
    })());
  }
});
