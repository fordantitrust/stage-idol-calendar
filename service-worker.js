// Idol Track — Live Idol TH Timetable — Service Worker
// Web Push notifications + PWA lifecycle + offline cache (v15.7.0)

'use strict';

// ── Versioning ────────────────────────────────────────────────────────────────
// CACHE_VERSION is kept in sync with APP_VERSION by sync-sw-version.php
const CACHE_VERSION = '16.5.3';
const STATIC_CACHE  = 'app-static-v' + CACHE_VERSION;
const PAGES_CACHE   = 'app-pages-v'  + CACHE_VERSION;
const API_CACHE     = 'app-api-v'    + CACHE_VERSION;
const VALID_CACHES  = [STATIC_CACHE, PAGES_CACHE, API_CACHE];

// Precached on install — paths relative to SW scope (works under subdir installs)
const PRECACHE_ASSETS = [
    'offline.html',
    'manifest.json',
    'icon/icon-72.png',
    'icon/icon-192.png',
    'icon/icon-512.png',
    'styles/common.css?v=' + CACHE_VERSION,
    'styles/index.css?v=' + CACHE_VERSION,
    'styles/artist.css?v=' + CACHE_VERSION,
    'styles/portal.css?v=' + CACHE_VERSION,
    'js/common.js?v=' + CACHE_VERSION,
    'js/translations.js?v=' + CACHE_VERSION
];

const MAX_API_CACHE_AGE_MS = 7 * 24 * 60 * 60 * 1000;   // 7 days
const NETWORK_TIMEOUT_MS   = 3000;                       // HTML strategy
const STATIC_ASSET_RE      = /\.(css|js|png|jpe?g|gif|svg|ico|webp|woff2?|ttf|otf)(\?.*)?$/i;

// Network-only path fragments (substring match on URL pathname).
// Includes both .php paths and clean URL routes.
const NETWORK_ONLY_PATHS = [
    '/api/favorites',
    '/api/push',
    '/api/request',
    '/api/event-request',
    '/api/telegram',
    'api/favorites.php',
    'api/push.php',
    'api/request.php',
    'api/event-request.php',
    'api/telegram.php',
    'feed.php',
    'my-feed.php',
    '/admin/',
    '/setup.php',
    '/tools/',
    'service-worker.js',
    'sync-sw-version.php'
];

// ── Helpers ───────────────────────────────────────────────────────────────────

function getOfflineUrl() {
    return new URL('offline.html', self.registration.scope).toString();
}

function isStaticAsset(url) {
    return STATIC_ASSET_RE.test(url.pathname);
}

function isNetworkOnly(url) {
    const p = url.pathname;
    for (let i = 0; i < NETWORK_ONLY_PATHS.length; i++) {
        if (p.indexOf(NETWORK_ONLY_PATHS[i]) !== -1) return true;
    }
    return false;
}

function isPublicApi(url) {
    // Root api.php (any querystring); excludes anything under /api/*
    return /(^|\/)api\.php$/.test(url.pathname);
}

function isHtmlRequest(request) {
    if (request.mode === 'navigate') return true;
    const accept = request.headers.get('accept') || '';
    return accept.indexOf('text/html') !== -1;
}

// Wrap a Response so we can read it more than once and inject our timestamp.
function cachePutWithTimestamp(cache, request, response) {
    return response.clone().blob().then(function(body) {
        const headers = new Headers(response.headers);
        headers.set('X-SW-Cached-At', String(Date.now()));
        const stamped = new Response(body, {
            status:     response.status,
            statusText: response.statusText,
            headers:    headers
        });
        return cache.put(request, stamped);
    });
}

function isCacheFresh(response, maxAgeMs) {
    if (!response) return false;
    const stamp = response.headers.get('X-SW-Cached-At');
    if (!stamp) return true;   // legacy entries with no stamp — treat as fresh
    const cachedAt = parseInt(stamp, 10);
    if (!cachedAt) return true;
    return (Date.now() - cachedAt) <= maxAgeMs;
}

// Refresh the timestamp on a cached response without changing its body.
function refreshCacheTimestamp(cache, request, cachedResponse) {
    return cachedResponse.clone().blob().then(function(body) {
        const headers = new Headers(cachedResponse.headers);
        headers.set('X-SW-Cached-At', String(Date.now()));
        const stamped = new Response(body, {
            status:     cachedResponse.status,
            statusText: cachedResponse.statusText,
            headers:    headers
        });
        return cache.put(request, stamped);
    });
}

// Conditional revalidation for API SWR. If cached has ETag, send If-None-Match.
// 200 → store new body + new timestamp. 304 → keep body, refresh timestamp.
function revalidateApi(request, cachedResponse) {
    return caches.open(API_CACHE).then(function(cache) {
        const headers = new Headers();
        if (cachedResponse) {
            const etag = cachedResponse.headers.get('ETag');
            if (etag) headers.set('If-None-Match', etag);
        }
        const condReq = new Request(request.url, {
            method:      'GET',
            headers:     headers,
            credentials: request.credentials,
            mode:        'same-origin',
            redirect:    'follow'
        });
        return fetch(condReq).then(function(networkResponse) {
            if (!networkResponse) return;
            if (networkResponse.status === 304 && cachedResponse) {
                // Body unchanged — refresh timestamp only
                return refreshCacheTimestamp(cache, request, cachedResponse);
            }
            if (networkResponse.status === 200) {
                return cachePutWithTimestamp(cache, request, networkResponse);
            }
            // Other statuses (4xx/5xx): leave cache alone
        }).catch(function() {
            // Network error during revalidation — silent, caller already served cache
        });
    });
}

// Network-first with timeout. If network too slow, fall back to cache.
function networkFirstWithTimeout(request, cacheName, timeoutMs) {
    return caches.open(cacheName).then(function(cache) {
        let timeoutId = null;
        const timeoutPromise = new Promise(function(resolve) {
            timeoutId = setTimeout(function() { resolve('__timeout__'); }, timeoutMs);
        });
        const networkPromise = fetch(request).then(function(response) {
            if (timeoutId) clearTimeout(timeoutId);
            // Cache successful HTML responses
            if (response && response.status === 200 && response.type !== 'opaque') {
                cachePutWithTimestamp(cache, request, response).catch(function() {});
            }
            return response;
        });
        return Promise.race([networkPromise, timeoutPromise]).then(function(result) {
            if (result === '__timeout__') {
                return cache.match(request).then(function(cached) {
                    if (cached) return cached;
                    // No cache — keep waiting for network
                    return networkPromise;
                });
            }
            return result;
        }).catch(function() {
            // Network failed entirely — try cache, then offline page
            return cache.match(request).then(function(cached) {
                if (cached) return cached;
                return caches.match(getOfflineUrl()).then(function(off) {
                    return off || new Response('Offline', { status: 503, statusText: 'Offline' });
                });
            });
        });
    });
}

// Cache-first for static assets, with network fallback that updates cache.
function cacheFirstStatic(request) {
    return caches.open(STATIC_CACHE).then(function(cache) {
        return cache.match(request).then(function(cached) {
            if (cached) return cached;
            return fetch(request).then(function(response) {
                if (response && response.status === 200 && response.type !== 'opaque') {
                    cache.put(request, response.clone()).catch(function() {});
                }
                return response;
            }).catch(function() {
                // Static asset unavailable offline — return a generic 503
                return new Response('Offline', { status: 503, statusText: 'Offline' });
            });
        });
    });
}

// Stale-while-revalidate for api.php. Serve cache (when fresh) + revalidate in background.
function staleWhileRevalidate(request) {
    return caches.open(API_CACHE).then(function(cache) {
        return cache.match(request).then(function(cached) {
            if (cached && isCacheFresh(cached, MAX_API_CACHE_AGE_MS)) {
                // Serve cache immediately, kick off background revalidation
                revalidateApi(request, cached);
                return cached;
            }
            // No cache or stale — go to network
            return fetch(request).then(function(networkResponse) {
                if (networkResponse && networkResponse.status === 200) {
                    cachePutWithTimestamp(cache, request, networkResponse).catch(function() {});
                }
                return networkResponse;
            }).catch(function() {
                // Network failed — if we have a stale cache, serve it as last resort
                if (cached) return cached;
                return caches.match(getOfflineUrl()).then(function(off) {
                    return off || new Response('Offline', { status: 503, statusText: 'Offline' });
                });
            });
        });
    });
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────

self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(function(cache) {
                // addAll is atomic — any single failure aborts install
                return cache.addAll(PRECACHE_ASSETS.map(function(p) {
                    return new URL(p, self.registration.scope).toString();
                }));
            })
            .catch(function() {
                // Don't block install entirely if a precache asset 404s — log silently
            })
    );
    self.skipWaiting();
});

self.addEventListener('activate', function(event) {
    event.waitUntil(
        caches.keys().then(function(keys) {
            return Promise.all(keys.map(function(key) {
                // Delete any app-* cache whose version doesn't match current
                if (key.indexOf('app-') === 0 && VALID_CACHES.indexOf(key) === -1) {
                    return caches.delete(key);
                }
                return Promise.resolve();
            }));
        }).then(function() {
            return clients.claim();
        })
    );
});

// ── Fetch Router ──────────────────────────────────────────────────────────────

self.addEventListener('fetch', function(event) {
    const request = event.request;

    // 1. Bypass: non-GET
    if (request.method !== 'GET') return;

    let url;
    try {
        url = new URL(request.url);
    } catch (e) {
        return;
    }

    // 2. Bypass: cross-origin (AdSense, GA, CDNs)
    if (url.origin !== self.location.origin) return;

    // 3. Bypass: network-only routes (private/write APIs, feeds, admin, setup, tools, SW itself)
    if (isNetworkOnly(url)) return;

    // 4. SWR: public api.php only
    if (isPublicApi(url)) {
        event.respondWith(staleWhileRevalidate(request));
        return;
    }

    // 5. Cache-first: static assets
    if (isStaticAsset(url)) {
        event.respondWith(cacheFirstStatic(request));
        return;
    }

    // 6. Network-first + timeout: HTML / document requests
    if (isHtmlRequest(request)) {
        event.respondWith(networkFirstWithTimeout(request, PAGES_CACHE, NETWORK_TIMEOUT_MS));
        return;
    }

    // Default: passthrough
});

// ── Push Notifications ────────────────────────────────────────────────────────

self.addEventListener('push', function(event) {
    var data = {};
    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            data = { title: 'Idol Track — Live Idol TH', body: event.data.text() };
        }
    }

    var scope   = self.registration.scope; // e.g. "https://host/stage-idol-calendar/"
    var title   = data.title  || 'Idol Track — Live Idol TH';
    var options = {
        body:               data.body  || '',
        icon:               data.icon  || scope + 'icon/icon-192.png',
        badge:              data.badge || scope + 'icon/icon-72.png',
        data:               { url: data.url || scope },
        tag:                data.tag   || 'idol-stage-push',
        requireInteraction: false,
        renotify:           false,
        silent:             false
    };

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

// ── Notification Click ────────────────────────────────────────────────────────

self.addEventListener('notificationclick', function(event) {
    event.notification.close();

    var url = (event.notification.data && event.notification.data.url)
        ? event.notification.data.url
        : self.registration.scope;

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(function(clientList) {
                // Focus existing window if already open
                for (var i = 0; i < clientList.length; i++) {
                    var client = clientList[i];
                    if (client.url === url && 'focus' in client) {
                        return client.focus();
                    }
                }
                // Otherwise open a new window
                if (clients.openWindow) {
                    return clients.openWindow(url);
                }
            })
    );
});

// ── Push Subscription Change ──────────────────────────────────────────────────

self.addEventListener('pushsubscriptionchange', function(event) {
    // Browser rotated push keys — re-subscribe and notify the server
    event.waitUntil(
        self.registration.pushManager.subscribe(event.oldSubscription.options)
            .then(function(newSub) {
                // Post message to all clients so they can call api/push.php?action=resubscribe
                return clients.matchAll({ type: 'window' })
                    .then(function(cs) {
                        cs.forEach(function(c) {
                            c.postMessage({
                                type: 'PUSH_SUBSCRIPTION_CHANGED',
                                subscription: newSub.toJSON()
                            });
                        });
                    });
            })
            .catch(function() {
                // Subscription expired — client will need to re-subscribe manually
            })
    );
});
