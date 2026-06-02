<?php
/**
 * PwaOfflineTest — Automated tests for PWA Offline Cache (v15.7.0)
 *
 * Coverage:
 *  1. service-worker.js — CACHE_VERSION matches APP_VERSION, cache constants,
 *     PRECACHE_ASSETS, install/activate/fetch handlers, network-only routes,
 *     SWR + ETag/304 logic, X-SW-Cached-At, network timeout, offline fallback
 *  2. offline.html — exists, static (no PHP), 3 languages, inline styles
 *  3. sync-sw-version.php — exists at root, CLI guard, idempotent, regex
 *  4. .htaccess — denies HTTP access to sync-sw-version.php (Apache 2.4 + 2.2 fallback)
 *  5. tools/update-version.php — no reference to service-worker.js (regression guard)
 *  6. Regression — push/notificationclick/pushsubscriptionchange handlers preserved
 */

require_once __DIR__ . '/../config.php';

// Helper: load file content once per file path
function _pwaLoad($relPath) {
    static $cache = [];
    $abs = realpath(__DIR__ . '/../' . $relPath);
    if ($abs === false) return null;
    if (!isset($cache[$abs])) {
        $cache[$abs] = file_get_contents($abs);
    }
    return $cache[$abs];
}

// ── 1. service-worker.js source checks ────────────────────────────────────────

function testPwaServiceWorkerFileExists($test) {
    $test->assertTrue(
        file_exists(__DIR__ . '/../service-worker.js'),
        'service-worker.js should exist at project root'
    );
}

function testPwaServiceWorkerHasCacheVersion($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertNotNull($sw, 'service-worker.js should be readable');
    $ok = preg_match("/const\s+CACHE_VERSION\s*=\s*'(\d+\.\d+\.\d+)'\s*;/", $sw, $m);
    $test->assertEquals(1, $ok, 'CACHE_VERSION constant should be defined');
}

function testPwaCacheVersionMatchesAppVersion($test) {
    $sw = _pwaLoad('service-worker.js');
    preg_match("/const\s+CACHE_VERSION\s*=\s*'(\d+\.\d+\.\d+)'\s*;/", $sw, $m);
    $swVersion = $m[1] ?? null;
    $test->assertEquals(
        APP_VERSION,
        $swVersion,
        'CACHE_VERSION in service-worker.js must match APP_VERSION (run: php sync-sw-version.php)'
    );
}

function testPwaServiceWorkerHasStaticCacheConstant($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("STATIC_CACHE", $sw, 'STATIC_CACHE constant should be defined');
    $test->assertContains("'app-static-v'", $sw, 'STATIC_CACHE should use app-static-v prefix');
}

function testPwaServiceWorkerHasPagesCacheConstant($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("PAGES_CACHE", $sw, 'PAGES_CACHE constant should be defined');
    $test->assertContains("'app-pages-v'", $sw, 'PAGES_CACHE should use app-pages-v prefix');
}

function testPwaServiceWorkerHasApiCacheConstant($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("API_CACHE", $sw, 'API_CACHE constant should be defined');
    $test->assertContains("'app-api-v'", $sw, 'API_CACHE should use app-api-v prefix');
}

function testPwaServiceWorkerHasValidCachesArray($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("VALID_CACHES", $sw, 'VALID_CACHES whitelist should be defined for activate cleanup');
}

function testPwaServiceWorkerHasPrecacheAssetsArray($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("PRECACHE_ASSETS", $sw, 'PRECACHE_ASSETS array should be defined');
}

function testPwaPrecacheIncludesOfflineHtml($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("'offline.html'", $sw, 'PRECACHE_ASSETS should include offline.html');
}

function testPwaPrecacheIncludesManifest($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("'manifest.json'", $sw, 'PRECACHE_ASSETS should include manifest.json');
}

function testPwaPrecacheIncludesIcons($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("icon/icon-72.png", $sw, 'PRECACHE_ASSETS should include icon-72');
    $test->assertContains("icon/icon-192.png", $sw, 'PRECACHE_ASSETS should include icon-192');
    $test->assertContains("icon/icon-512.png", $sw, 'PRECACHE_ASSETS should include icon-512');
}

function testPwaPrecacheIncludesCommonCss($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("styles/common.css", $sw, 'PRECACHE_ASSETS should include common.css');
}

function testPwaPrecacheIncludesCommonJs($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("js/common.js", $sw, 'PRECACHE_ASSETS should include common.js');
}

function testPwaServiceWorkerHasMaxApiCacheAgeConstant($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("MAX_API_CACHE_AGE_MS", $sw, 'MAX_API_CACHE_AGE_MS constant should be defined');
    $test->assertContains("7 * 24 * 60 * 60 * 1000", $sw, 'MAX_API_CACHE_AGE_MS should be 7 days');
}

function testPwaServiceWorkerHasNetworkTimeoutConstant($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("NETWORK_TIMEOUT_MS", $sw, 'NETWORK_TIMEOUT_MS constant should be defined');
    $test->assertContains("3000", $sw, 'NETWORK_TIMEOUT_MS should be 3000 (3s)');
}

function testPwaServiceWorkerHasInstallHandler($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("addEventListener('install'", $sw, 'install handler should exist');
    $test->assertContains("cache.addAll", $sw, 'install should call cache.addAll for precache');
}

function testPwaServiceWorkerHasActivateHandler($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("addEventListener('activate'", $sw, 'activate handler should exist');
    $test->assertContains("caches.keys()", $sw, 'activate should iterate caches.keys() for cleanup');
    $test->assertContains("caches.delete", $sw, 'activate should delete stale caches');
    $test->assertContains("clients.claim()", $sw, 'activate should call clients.claim()');
}

function testPwaServiceWorkerHasFetchHandler($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("addEventListener('fetch'", $sw, 'fetch handler should exist');
    $test->assertContains("event.respondWith", $sw, 'fetch should use event.respondWith');
}

function testPwaFetchBypassesNonGet($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("request.method !== 'GET'", $sw, 'fetch should bypass non-GET requests');
}

function testPwaFetchBypassesCrossOrigin($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("url.origin !== self.location.origin", $sw, 'fetch should bypass cross-origin requests');
}

function testPwaNetworkOnlyIncludesFavoritesApi($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("/api/favorites", $sw, 'Network-only list should include /api/favorites');
    $test->assertContains("api/favorites.php", $sw, 'Network-only list should include api/favorites.php');
}

function testPwaNetworkOnlyIncludesPushApi($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("/api/push", $sw, 'Network-only list should include /api/push');
    $test->assertContains("api/push.php", $sw, 'Network-only list should include api/push.php');
}

function testPwaNetworkOnlyIncludesRequestApi($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("/api/request", $sw, 'Network-only list should include /api/request');
    $test->assertContains("api/request.php", $sw, 'Network-only list should include api/request.php');
}

function testPwaNetworkOnlyIncludesEventRequestApi($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("/api/event-request", $sw, 'Network-only list should include /api/event-request');
    $test->assertContains("api/event-request.php", $sw, 'Network-only list should include api/event-request.php');
}

function testPwaNetworkOnlyIncludesTelegramApi($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("/api/telegram", $sw, 'Network-only list should include /api/telegram');
    $test->assertContains("api/telegram.php", $sw, 'Network-only list should include api/telegram.php');
}

function testPwaNetworkOnlyIncludesFeeds($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("feed.php", $sw, 'Network-only list should include feed.php');
    $test->assertContains("my-feed.php", $sw, 'Network-only list should include my-feed.php');
}

function testPwaNetworkOnlyIncludesAdminAndSetup($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("/admin/", $sw, 'Network-only list should include /admin/');
    $test->assertContains("/setup.php", $sw, 'Network-only list should include /setup.php');
    $test->assertContains("/tools/", $sw, 'Network-only list should include /tools/');
}

function testPwaNetworkOnlyIncludesSwSelfAndSync($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("service-worker.js", $sw, 'Network-only list should include service-worker.js itself');
    $test->assertContains("sync-sw-version.php", $sw, 'Network-only list should include sync-sw-version.php');
}

function testPwaSwrLogicForApi($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("staleWhileRevalidate", $sw, 'SWR helper should exist for api.php');
    $test->assertContains("isPublicApi", $sw, 'SWR routing should detect public api.php');
}

function testPwaCachePutWithTimestamp($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("X-SW-Cached-At", $sw, 'Responses cached must include X-SW-Cached-At header');
    $test->assertContains("cachePutWithTimestamp", $sw, 'cachePutWithTimestamp helper should exist');
}

function testPwaIsCacheFreshChecksAge($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("isCacheFresh", $sw, 'isCacheFresh helper should exist');
    $test->assertContains("MAX_API_CACHE_AGE_MS", $sw, 'Freshness check should use MAX_API_CACHE_AGE_MS');
}

function testPwaEtagRevalidation($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("ETag", $sw, 'Revalidation should read ETag from cached response');
    $test->assertContains("If-None-Match", $sw, 'Revalidation should send If-None-Match header');
    $test->assertContains("304", $sw, 'Revalidation should handle 304 Not Modified');
}

function testPwa304DoesNotOverwriteBody($test) {
    $sw = _pwaLoad('service-worker.js');
    // The 304 branch should call refreshCacheTimestamp, NOT cachePutWithTimestamp(networkResponse)
    $test->assertContains("refreshCacheTimestamp", $sw,
        'On 304, helper must refresh timestamp without overwriting cached body');
}

function testPwaNetworkTimeoutUsesRace($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("Promise.race", $sw, 'Network timeout should use Promise.race');
    $test->assertContains("setTimeout", $sw, 'Network timeout should use setTimeout');
}

function testPwaOfflineFallback($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("getOfflineUrl", $sw, 'getOfflineUrl helper should exist');
    $test->assertContains("self.registration.scope", $sw,
        'getOfflineUrl should derive URL from self.registration.scope (subdir safe)');
    $test->assertContains("offline.html", $sw, 'Fallback should reference offline.html');
}

function testPwaStaticAssetRegex($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("STATIC_ASSET_RE", $sw, 'Static asset regex constant should exist');
    $test->assertContains("css|js|png", $sw, 'Static asset regex should cover css/js/png');
}

// ── 2. Regression: push/notificationclick/pushsubscriptionchange preserved ────

function testPwaPushHandlerPreserved($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("addEventListener('push'", $sw, 'push handler must be preserved');
    $test->assertContains("showNotification", $sw, 'push handler must call showNotification');
}

function testPwaNotificationClickPreserved($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("addEventListener('notificationclick'", $sw,
        'notificationclick handler must be preserved');
    $test->assertContains("clients.matchAll", $sw, 'notificationclick must call clients.matchAll');
}

function testPwaPushSubscriptionChangePreserved($test) {
    $sw = _pwaLoad('service-worker.js');
    $test->assertContains("addEventListener('pushsubscriptionchange'", $sw,
        'pushsubscriptionchange handler must be preserved');
    $test->assertContains("PUSH_SUBSCRIPTION_CHANGED", $sw,
        'pushsubscriptionchange must postMessage PUSH_SUBSCRIPTION_CHANGED');
}

// ── 3. offline.html checks ────────────────────────────────────────────────────

function testPwaOfflineHtmlExists($test) {
    $test->assertTrue(
        file_exists(__DIR__ . '/../offline.html'),
        'offline.html should exist at project root'
    );
}

function testPwaOfflineHtmlHasDoctype($test) {
    $html = _pwaLoad('offline.html');
    $test->assertContains("<!DOCTYPE html>", $html, 'offline.html should have HTML5 doctype');
}

function testPwaOfflineHtmlHasTitle($test) {
    $html = _pwaLoad('offline.html');
    $test->assertContains("<title>", $html, 'offline.html should have <title> tag');
}

function testPwaOfflineHtmlIsStatic($test) {
    $html = _pwaLoad('offline.html');
    $test->assertEquals(false, strpos($html, '<?php'),
        'offline.html must be static — no <?php tags allowed');
    $test->assertEquals(false, strpos($html, '<?='),
        'offline.html must be static — no <?= tags allowed');
}

function testPwaOfflineHtmlHasThaiText($test) {
    $html = _pwaLoad('offline.html');
    $test->assertContains("ลองอีกครั้ง", $html, 'offline.html should contain Thai retry label');
}

function testPwaOfflineHtmlHasEnglishText($test) {
    $html = _pwaLoad('offline.html');
    $test->assertContains("Try again", $html, 'offline.html should contain English retry label');
}

function testPwaOfflineHtmlHasJapaneseText($test) {
    $html = _pwaLoad('offline.html');
    $test->assertContains("再試行", $html, 'offline.html should contain Japanese retry label');
}

function testPwaOfflineHtmlHasInlineStyles($test) {
    $html = _pwaLoad('offline.html');
    $test->assertContains("<style>", $html, 'offline.html should have inline <style> tag');
}

function testPwaOfflineHtmlHasNoExternalCss($test) {
    $html = _pwaLoad('offline.html');
    $test->assertEquals(false, strpos($html, '<link rel="stylesheet"'),
        'offline.html must not link external CSS (must work offline)');
}

function testPwaOfflineHtmlHasNoExternalScripts($test) {
    $html = _pwaLoad('offline.html');
    // Allow inline <script> but block external src
    $matches = [];
    preg_match_all('/<script\s+[^>]*src\s*=/i', $html, $matches);
    $test->assertCount(0, $matches[0],
        'offline.html must not load external scripts (must work offline)');
}

function testPwaOfflineHtmlHasReloadButton($test) {
    $html = _pwaLoad('offline.html');
    $test->assertContains("location.reload", $html, 'offline.html should reload on retry click');
}

// ── 4. sync-sw-version.php checks ─────────────────────────────────────────────

function testPwaSyncScriptExists($test) {
    $test->assertTrue(
        file_exists(__DIR__ . '/../sync-sw-version.php'),
        'sync-sw-version.php should exist at project root'
    );
}

function testPwaSyncScriptHasCliGuard($test) {
    $src = _pwaLoad('sync-sw-version.php');
    $test->assertContains("php_sapi_name() !== 'cli'", $src,
        'sync-sw-version.php must guard against web access via php_sapi_name()');
    $test->assertContains("http_response_code(403)", $src,
        'sync-sw-version.php should return 403 on web access');
}

function testPwaSyncScriptRequiresConfig($test) {
    $src = _pwaLoad('sync-sw-version.php');
    $test->assertContains("require_once", $src, 'sync-sw-version.php should require config/app.php');
    $test->assertContains("config/app.php", $src, 'sync-sw-version.php should require config/app.php');
}

function testPwaSyncScriptHasVersionRegex($test) {
    $src = _pwaLoad('sync-sw-version.php');
    $test->assertContains("CACHE_VERSION", $src, 'sync-sw-version.php should reference CACHE_VERSION');
    $test->assertContains("preg_match", $src, 'sync-sw-version.php should use preg_match');
}

function testPwaSyncScriptIsIdempotent($test) {
    $src = _pwaLoad('sync-sw-version.php');
    $test->assertContains("already at", $src,
        'sync-sw-version.php should early-return when version matches (idempotent)');
}

// ── 5. .htaccess HTTP block ───────────────────────────────────────────────────

function testPwaHtaccessBlocksSyncScript($test) {
    $ht = _pwaLoad('.htaccess');
    $test->assertContains('<Files "sync-sw-version.php">', $ht,
        '.htaccess should have <Files> block for sync-sw-version.php');
}

function testPwaHtaccessUsesApache24Syntax($test) {
    $ht = _pwaLoad('.htaccess');
    // Locate the sync-sw-version.php block specifically
    $pos = strpos($ht, '<Files "sync-sw-version.php">');
    $test->assertNotEquals(false, $pos, 'sync-sw-version.php Files block must exist');
    $block = substr($ht, $pos, 400);
    $test->assertContains("Require all denied", $block,
        'sync-sw-version.php block should use Apache 2.4 Require all denied');
}

function testPwaHtaccessHasApache22Fallback($test) {
    $ht = _pwaLoad('.htaccess');
    $pos = strpos($ht, '<Files "sync-sw-version.php">');
    $block = substr($ht, $pos, 400);
    $test->assertContains("Deny from all", $block,
        'sync-sw-version.php block should have Apache 2.2 Deny from all fallback');
}

// ── 6. tools/update-version.php regression guard ──────────────────────────────

function testPwaUpdateVersionDoesNotTouchSw($test) {
    $src = _pwaLoad('tools/update-version.php');
    $test->assertNotNull($src, 'tools/update-version.php should be readable');
    $test->assertEquals(false, strpos($src, 'service-worker.js'),
        'tools/update-version.php must NOT reference service-worker.js — use sync-sw-version.php instead');
}
