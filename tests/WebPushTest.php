<?php
/**
 * WebPushTest — Automated tests for Web Push Notifications / PWA (v15.0.0)
 *
 * Coverage:
 *  1.  Config & Constants         — WEBPUSH_* constants defined + webpush_is_enabled()
 *  2.  Crypto Functions           — key generation, encoding helpers, JWT, encrypt, send
 *  3.  Static Files               — service-worker.js, manifest.json, icons
 *  4.  Admin API                  — webpush_config_get/save/vapid_generate actions present
 *  5.  Public API                 — api/push.php subscribe/unsubscribe/status 400 without slug
 *  6.  Cron                       — send-web-push-notifications.php CLI-only + guard
 *  7.  i18n                       — admin-i18n.js and translations.js keys
 *  8.  Config loading             — config/webpush.php required in config.php
 */

require_once __DIR__ . '/../config.php';

// ── 1. Config & Constants ─────────────────────────────────────────────────────

function testWebPushEnabledConstantDefined($test) {
    $test->assertTrue(
        defined('WEBPUSH_ENABLED'),
        'WEBPUSH_ENABLED constant should be defined after config.php loads'
    );
}

function testWebPushVapidPublicKeyConstantDefined($test) {
    $test->assertTrue(
        defined('WEBPUSH_VAPID_PUBLIC_KEY'),
        'WEBPUSH_VAPID_PUBLIC_KEY constant should be defined after config.php loads'
    );
}

function testWebPushNotifyBeforeMinutesConstantDefined($test) {
    $test->assertTrue(
        defined('WEBPUSH_NOTIFY_BEFORE_MINUTES'),
        'WEBPUSH_NOTIFY_BEFORE_MINUTES constant should be defined'
    );
}

function testWebPushMaxSubsPerTokenConstantDefined($test) {
    $test->assertTrue(
        defined('WEBPUSH_MAX_SUBS_PER_TOKEN'),
        'WEBPUSH_MAX_SUBS_PER_TOKEN constant should be defined'
    );
}

function testWebPushIsEnabledFunctionExists($test) {
    $test->assertTrue(
        function_exists('webpush_is_enabled'),
        'webpush_is_enabled() should be defined in functions/webpush.php'
    );
}

function testWebPushIsEnabledReturnsFalseWhenNoVapidKey($test) {
    // Default config has empty VAPID key — should return false
    if (WEBPUSH_ENABLED && !empty(WEBPUSH_VAPID_PUBLIC_KEY)) {
        // VAPID already configured — skip this assertion
        $test->assertTrue(true, 'VAPID already configured, skipping empty-key guard test');
        return;
    }
    $test->assertFalse(
        webpush_is_enabled(),
        'webpush_is_enabled() should return false when VAPID_PUBLIC_KEY is empty'
    );
}

// ── 2. Crypto Helper Functions ────────────────────────────────────────────────

function testWebPushUrlsafeB64EncodeFunctionExists($test) {
    $test->assertTrue(function_exists('webpush_urlsafe_b64encode'), 'webpush_urlsafe_b64encode() should exist');
}

function testWebPushUrlsafeB64DecodeFunctionExists($test) {
    $test->assertTrue(function_exists('webpush_urlsafe_b64decode'), 'webpush_urlsafe_b64decode() should exist');
}

function testWebPushB64EncodeDecodeRoundtrip($test) {
    $raw     = random_bytes(32);
    $encoded = webpush_urlsafe_b64encode($raw);
    $decoded = webpush_urlsafe_b64decode($encoded);
    $test->assertEquals($raw, $decoded, 'base64url encode/decode roundtrip should return original bytes');
}

function testWebPushB64EncodeProducesNoPlus($test) {
    // Run many random samples — ensure none contain + or /
    for ($i = 0; $i < 20; $i++) {
        $encoded = webpush_urlsafe_b64encode(random_bytes(16));
        $test->assertFalse(
            strpos($encoded, '+') !== false || strpos($encoded, '/') !== false,
            'webpush_urlsafe_b64encode() must not produce + or / characters'
        );
    }
}

function testWebPushGenerateVapidKeysFunctionExists($test) {
    $test->assertTrue(function_exists('webpush_generate_vapid_keys'), 'webpush_generate_vapid_keys() should exist');
}

function testWebPushGenerateVapidKeysReturnsArray($test) {
    $keys = webpush_generate_vapid_keys();
    $test->assertTrue(is_array($keys) && !empty($keys), 'webpush_generate_vapid_keys() should return a non-empty array');
}

function testWebPushGenerateVapidKeysPublicKey87Chars($test) {
    $keys = webpush_generate_vapid_keys();
    if (!is_array($keys)) { $test->assertTrue(false, 'keys not returned'); return; }
    $pub = $keys['public_key'] ?? '';
    // Uncompressed P-256 EC point (65 bytes) base64url-encoded → 87 chars (no padding)
    $test->assertTrue(
        strlen($pub) === 87,
        'VAPID public key should be 87 characters (65-byte EC point in base64url without padding), got: ' . strlen($pub)
    );
}

function testWebPushGenerateVapidKeysPublicKeyStartsWithB($test) {
    $keys = webpush_generate_vapid_keys();
    if (!is_array($keys)) { $test->assertTrue(false, 'keys not returned'); return; }
    $pub = $keys['public_key'] ?? '';
    // P-256 uncompressed point starts with 0x04; base64url of "\x04..." starts with 'B'
    $test->assertEquals('B', substr($pub, 0, 1), 'VAPID public key should start with "B" (uncompressed EC point 0x04)');
}

function testWebPushGenerateVapidKeysHasPrivateKeyPem($test) {
    $keys = webpush_generate_vapid_keys();
    if (!is_array($keys)) { $test->assertTrue(false, 'keys not returned'); return; }
    $pem = $keys['private_key_pem'] ?? '';
    // openssl_pkey_export() may produce SEC1 (BEGIN EC PRIVATE KEY) or PKCS#8 (BEGIN PRIVATE KEY)
    // depending on the OpenSSL version and platform; both are valid EC private key formats
    $isEc  = strpos($pem, '-----BEGIN EC PRIVATE KEY-----') !== false;
    $isPkcs8 = strpos($pem, '-----BEGIN PRIVATE KEY-----') !== false;
    $test->assertTrue(
        $isEc || $isPkcs8,
        'private_key_pem should contain a PEM private key header (EC or PKCS#8)'
    );
}

function testWebPushDerToRawSigFunctionExists($test) {
    $test->assertTrue(function_exists('webpush_der_to_raw_sig'), 'webpush_der_to_raw_sig() should exist');
}

function testWebPushRawPubkeyToPemFunctionExists($test) {
    $test->assertTrue(function_exists('webpush_raw_pubkey_to_pem'), 'webpush_raw_pubkey_to_pem() should exist');
}

function testWebPushVapidJwtFunctionExists($test) {
    $test->assertTrue(function_exists('webpush_vapid_jwt'), 'webpush_vapid_jwt() should exist');
}

function testWebPushEncryptFunctionExists($test) {
    $test->assertTrue(function_exists('webpush_encrypt'), 'webpush_encrypt() should exist');
}

function testWebPushSendFunctionExists($test) {
    $test->assertTrue(function_exists('webpush_send'), 'webpush_send() should exist');
}

// ── 3. Static Files ───────────────────────────────────────────────────────────

function testServiceWorkerFileExists($test) {
    $test->assertTrue(
        file_exists(__DIR__ . '/../service-worker.js'),
        'service-worker.js should exist at project root'
    );
}

function testServiceWorkerHasPushEventListener($test) {
    $sw = @file_get_contents(__DIR__ . '/../service-worker.js');
    $test->assertTrue(
        $sw !== false && strpos($sw, "'push'") !== false || ($sw !== false && strpos($sw, '"push"') !== false),
        'service-worker.js should contain a push event listener'
    );
}

function testServiceWorkerHasNotificationClickListener($test) {
    $sw = @file_get_contents(__DIR__ . '/../service-worker.js');
    $test->assertTrue(
        $sw !== false && strpos($sw, 'notificationclick') !== false,
        'service-worker.js should contain a notificationclick event listener'
    );
}

function testManifestJsonFileExists($test) {
    $test->assertTrue(
        file_exists(__DIR__ . '/../manifest.json'),
        'manifest.json should exist at project root'
    );
}

function testManifestJsonHasName($test) {
    $manifest = json_decode(@file_get_contents(__DIR__ . '/../manifest.json'), true);
    $test->assertTrue(isset($manifest['name']) && !empty($manifest['name']), 'manifest.json should have a non-empty "name" field');
}

function testManifestJsonHas192Icon($test) {
    $manifest = json_decode(@file_get_contents(__DIR__ . '/../manifest.json'), true);
    $icons = $manifest['icons'] ?? [];
    $has192 = false;
    foreach ($icons as $icon) {
        if (($icon['sizes'] ?? '') === '192x192') { $has192 = true; break; }
    }
    $test->assertTrue($has192, 'manifest.json icons array should contain a 192x192 icon');
}

function testManifestJsonHasStartUrl($test) {
    $manifest = json_decode(@file_get_contents(__DIR__ . '/../manifest.json'), true);
    $test->assertTrue(isset($manifest['start_url']), 'manifest.json should have start_url field');
}

function testManifestJsonDisplayStandalone($test) {
    $manifest = json_decode(@file_get_contents(__DIR__ . '/../manifest.json'), true);
    $test->assertEquals('standalone', $manifest['display'] ?? '', 'manifest.json display should be "standalone"');
}

function testPwaIcon192Exists($test) {
    $test->assertTrue(
        file_exists(__DIR__ . '/../icon/icon-192.png'),
        'icon/icon-192.png should exist (run php tools/generate-pwa-icons.php to create)'
    );
}

// ── 4. Admin API ──────────────────────────────────────────────────────────────

function testAdminApiHasWebPushConfigGetAction($test) {
    $src = @file_get_contents(__DIR__ . '/../admin/api.php');
    $test->assertTrue(
        $src !== false && strpos($src, 'webpush_config_get') !== false,
        'admin/api.php should contain webpush_config_get action'
    );
}

function testAdminApiHasWebPushConfigSaveAction($test) {
    $src = @file_get_contents(__DIR__ . '/../admin/api.php');
    $test->assertTrue(
        $src !== false && strpos($src, 'webpush_config_save') !== false,
        'admin/api.php should contain webpush_config_save action'
    );
}

function testAdminApiHasWebPushVapidGenerateAction($test) {
    $src = @file_get_contents(__DIR__ . '/../admin/api.php');
    $test->assertTrue(
        $src !== false && strpos($src, 'webpush_vapid_generate') !== false,
        'admin/api.php should contain webpush_vapid_generate action'
    );
}

// ── 5. Public API ─────────────────────────────────────────────────────────────

function testPushApiFileExists($test) {
    $test->assertTrue(file_exists(__DIR__ . '/../api/push.php'), 'api/push.php should exist');
}

function testPushApiHasSubscribeAction($test) {
    $src = @file_get_contents(__DIR__ . '/../api/push.php');
    $test->assertTrue($src !== false && strpos($src, 'subscribe') !== false, 'api/push.php should handle subscribe action');
}

function testPushApiHasUnsubscribeAction($test) {
    $src = @file_get_contents(__DIR__ . '/../api/push.php');
    $test->assertTrue($src !== false && strpos($src, 'unsubscribe') !== false, 'api/push.php should handle unsubscribe action');
}

function testPushApiHasStatusAction($test) {
    $src = @file_get_contents(__DIR__ . '/../api/push.php');
    $test->assertTrue($src !== false && strpos($src, 'status') !== false, 'api/push.php should handle status action');
}

// ── 6. Cron ───────────────────────────────────────────────────────────────────

function testWebPushCronFileExists($test) {
    $test->assertTrue(
        file_exists(__DIR__ . '/../cron/send-web-push-notifications.php'),
        'cron/send-web-push-notifications.php should exist'
    );
}

function testWebPushCronIsCliOnly($test) {
    $src = @file_get_contents(__DIR__ . '/../cron/send-web-push-notifications.php');
    $test->assertTrue(
        $src !== false && strpos($src, "php_sapi_name()") !== false,
        'cron/send-web-push-notifications.php should guard against HTTP execution with php_sapi_name()'
    );
}

function testWebPushCronChecksWebPushIsEnabled($test) {
    $src = @file_get_contents(__DIR__ . '/../cron/send-web-push-notifications.php');
    $test->assertTrue(
        $src !== false && strpos($src, 'webpush_is_enabled') !== false,
        'cron/send-web-push-notifications.php should call webpush_is_enabled() before running'
    );
}

// ── 7. i18n ───────────────────────────────────────────────────────────────────

function testAdminI18nHasWebPushTitleKeyTh($test) {
    $src = @file_get_contents(__DIR__ . '/../admin/js/admin-i18n.js');
    $test->assertTrue(
        $src !== false && strpos($src, 'settings.webpush') !== false,
        'admin-i18n.js (TH section) should contain settings.webpush key'
    );
}

function testAdminI18nHasWebPushTitleKeyEn($test) {
    $src = @file_get_contents(__DIR__ . '/../admin/js/admin-i18n.js');
    // EN section appears after TH section; key should appear at least twice
    $test->assertTrue(
        $src !== false && substr_count($src, 'settings.webpush') >= 2,
        'admin-i18n.js should have settings.webpush key in both TH and EN sections'
    );
}

function testTranslationsJsHasWebPushSubscribeKey($test) {
    $src = @file_get_contents(__DIR__ . '/../js/translations.js');
    $test->assertTrue(
        $src !== false && strpos($src, 'webpush.subscribe') !== false,
        'translations.js should contain webpush.subscribe key'
    );
}

function testTranslationsJsHasWebPushUnsubscribeKey($test) {
    $src = @file_get_contents(__DIR__ . '/../js/translations.js');
    $test->assertTrue(
        $src !== false && strpos($src, 'webpush.unsubscribe') !== false,
        'translations.js should contain webpush.unsubscribe key'
    );
}

// ── 8. Config Loading ─────────────────────────────────────────────────────────

function testWebPushConfigPhpLoadedInConfigPhp($test) {
    $src = @file_get_contents(__DIR__ . '/../config.php');
    $test->assertTrue(
        $src !== false && strpos($src, 'config/webpush.php') !== false,
        'config.php should require config/webpush.php'
    );
}

function testWebPushFunctionsPhpLoadedInConfigPhp($test) {
    $src = @file_get_contents(__DIR__ . '/../config.php');
    $test->assertTrue(
        $src !== false && strpos($src, 'functions/webpush.php') !== false,
        'config.php should require functions/webpush.php'
    );
}

// ── 9. Endpoint Allow-List (SSRF defense) ─────────────────────────────────────

function testWebPushAllowedHostsFunctionExists($test) {
    $test->assertTrue(function_exists('webpush_allowed_hosts'), 'webpush_allowed_hosts() should be defined');
}

function testWebPushValidateEndpointFunctionExists($test) {
    $test->assertTrue(function_exists('webpush_validate_endpoint'), 'webpush_validate_endpoint() should be defined');
}

function testWebPushAllowedHostsContainsFcm($test) {
    $hosts = webpush_allowed_hosts();
    $test->assertTrue(
        isset($hosts['exact']) && in_array('fcm.googleapis.com', $hosts['exact'], true),
        'allow-list should include fcm.googleapis.com'
    );
}

function testWebPushAllowedHostsContainsMozilla($test) {
    $hosts = webpush_allowed_hosts();
    $test->assertTrue(
        in_array('updates.push.services.mozilla.com', $hosts['exact'], true),
        'allow-list should include updates.push.services.mozilla.com'
    );
}

function testWebPushAllowedHostsContainsApple($test) {
    $hosts = webpush_allowed_hosts();
    $test->assertTrue(
        in_array('web.push.apple.com', $hosts['exact'], true),
        'allow-list should include web.push.apple.com'
    );
}

function testWebPushAllowedHostsContainsWnsSuffix($test) {
    $hosts = webpush_allowed_hosts();
    $test->assertTrue(
        isset($hosts['suffix']) && in_array('.notify.windows.com', $hosts['suffix'], true),
        'allow-list should include .notify.windows.com suffix for WNS'
    );
}

function testValidateEndpointAcceptsFcm($test) {
    $test->assertNotNull(
        webpush_validate_endpoint('https://fcm.googleapis.com/fcm/send/abc123'),
        'fcm.googleapis.com endpoint should be accepted'
    );
}

function testValidateEndpointAcceptsMozilla($test) {
    $test->assertNotNull(
        webpush_validate_endpoint('https://updates.push.services.mozilla.com/wpush/v2/xxx'),
        'Mozilla autopush endpoint should be accepted'
    );
}

function testValidateEndpointAcceptsApple($test) {
    $test->assertNotNull(
        webpush_validate_endpoint('https://web.push.apple.com/QABcDeF'),
        'Apple Web Push endpoint should be accepted'
    );
}

function testValidateEndpointAcceptsWnsSubdomain($test) {
    $test->assertNotNull(
        webpush_validate_endpoint('https://db5p.notify.windows.com/w/?token=xxx'),
        'WNS *.notify.windows.com subdomain should be accepted via suffix match'
    );
}

function testValidateEndpointRejectsHttpScheme($test) {
    // http (not https) to a known host must still fail in production path
    if (defined('WEBPUSH_ALLOW_LOCALHOST') && WEBPUSH_ALLOW_LOCALHOST
        && (!defined('PRODUCTION_MODE') || !PRODUCTION_MODE)) {
        // localhost exception is active — http is allowed only for localhost hosts,
        // so http to fcm.googleapis.com must still be rejected.
    }
    $test->assertNull(
        webpush_validate_endpoint('http://fcm.googleapis.com/fcm/send/abc'),
        'http (not https) to FCM should be rejected'
    );
}

function testValidateEndpointRejectsAttackerHost($test) {
    $test->assertNull(
        webpush_validate_endpoint('https://attacker.example.com/exfil'),
        'attacker.example.com should be rejected'
    );
}

function testValidateEndpointRejectsIpV4Literal($test) {
    $test->assertNull(
        webpush_validate_endpoint('https://10.0.0.5/internal'),
        'IPv4 literal must be rejected to prevent SSRF'
    );
}

function testValidateEndpointRejectsIpV6Literal($test) {
    // [::1] in URL host position — parse_url returns "::1" or "[::1]" depending on PHP build;
    // both must be rejected unless localhost dev flag is on (covered separately).
    $r1 = webpush_validate_endpoint('https://[2001:db8::1]/x');
    $test->assertNull($r1, 'IPv6 literal must be rejected in production path');
}

function testValidateEndpointRejectsEmptyHost($test) {
    $test->assertNull(webpush_validate_endpoint('https:///path'), 'URL with empty host must be rejected');
}

function testValidateEndpointRejectsGarbage($test) {
    $test->assertNull(webpush_validate_endpoint('not a url'), 'non-URL string must be rejected');
    $test->assertNull(webpush_validate_endpoint(''), 'empty string must be rejected');
}

function testValidateEndpointRejectsLookalikeSuffix($test) {
    // host = "evil.notify.windows.com.attacker.example" — must NOT match .notify.windows.com
    $test->assertNull(
        webpush_validate_endpoint('https://evil.notify.windows.com.attacker.example/x'),
        'lookalike suffix (suffix not at end of host) must be rejected'
    );
}

function testValidateEndpointRejectsBareSuffixAsHost($test) {
    // host == bare suffix without leading dot — must be rejected
    $test->assertNull(
        webpush_validate_endpoint('https://notify.windows.com/x'),
        'bare WNS suffix as full hostname must be rejected (only subdomains accepted)'
    );
}

function testValidateEndpointAllowLocalhostConstantDefined($test) {
    $test->assertTrue(
        defined('WEBPUSH_ALLOW_LOCALHOST'),
        'WEBPUSH_ALLOW_LOCALHOST constant should be defined'
    );
}

function testValidateEndpointLocalhostFlagDefaultsOff($test) {
    // The shipped default in config/webpush.php should be false.
    // The live config may override; this test reads the source of truth.
    $defaultsSrc = @file_get_contents(__DIR__ . '/../config/webpush.php');
    $test->assertTrue(
        $defaultsSrc !== false && strpos($defaultsSrc, "'allow_localhost'      => false") !== false,
        'config/webpush.php default for allow_localhost must be false'
    );
}

function testValidateEndpointLocalhostBehavior($test) {
    // Behavior depends on the live constants. Verify the two-flag gate works correctly.
    $allowLocal = defined('WEBPUSH_ALLOW_LOCALHOST') && WEBPUSH_ALLOW_LOCALHOST;
    $isProd     = defined('PRODUCTION_MODE') && PRODUCTION_MODE;
    $shouldAllow = $allowLocal && !$isProd;

    $r = webpush_validate_endpoint('http://localhost:8090/subscribe/x');
    if ($shouldAllow) {
        $test->assertNotNull($r, 'localhost should be accepted when WEBPUSH_ALLOW_LOCALHOST=true and PRODUCTION_MODE=false');
    } else {
        $test->assertNull($r, 'localhost must be rejected when flag is off or PRODUCTION_MODE=true');
    }

    $r2 = webpush_validate_endpoint('https://127.0.0.1/x');
    if ($shouldAllow) {
        $test->assertNotNull($r2, '127.0.0.1 should be accepted under the dev gate');
    } else {
        $test->assertNull($r2, '127.0.0.1 must be rejected when dev gate is closed');
    }
}

function testWebPushSendRejectsBadEndpoint($test) {
    // webpush_send() must short-circuit with expired=true for non-allow-listed endpoints,
    // without ever issuing a curl request.
    $sub = [
        'endpoint' => 'https://attacker.example.com/exfil',
        'keys'     => [
            'p256dh' => webpush_urlsafe_b64encode(str_repeat("\x04", 65)),
            'auth'   => webpush_urlsafe_b64encode(random_bytes(16)),
        ],
    ];
    $result = webpush_send($sub, '{"title":"x"}', [
        'vapid_public_key'      => 'X',
        'vapid_private_key_pem' => '',
        'vapid_subject'         => 'mailto:test@example.com',
    ]);
    $test->assertFalse($result['success'], 'send must fail for non-allow-listed endpoint');
    $test->assertTrue($result['expired'], 'expired=true so cron drops the subscription');
    $test->assertEquals('endpoint_not_allowed', $result['error'], 'error code should be endpoint_not_allowed');
}

function testApiPushUsesValidateEndpoint($test) {
    // Ensure api/push.php no longer uses the old preg_match check and now calls the validator.
    $src = @file_get_contents(__DIR__ . '/../api/push.php');
    $test->assertTrue(
        $src !== false && strpos($src, 'webpush_validate_endpoint') !== false,
        'api/push.php should call webpush_validate_endpoint() for subscribe'
    );
    $test->assertTrue(
        $src !== false && strpos($src, 'FILTER_SANITIZE_URL') === false,
        'api/push.php should not use deprecated FILTER_SANITIZE_URL'
    );
}

// ── Viewer-Timezone Notifications (v16.1.1) ──────────────────────────────────

function testPushSubscribeAcceptsTz($test) {
    $src = file_get_contents(dirname(__DIR__) . '/api/push.php');
    $test->assertContains("\$body['tz']", $src, 'subscribe should read tz from the body');
    $test->assertContains('is_valid_timezone', $src, 'subscribe should validate the timezone');
    $test->assertContains("'tz'             => \$tz", $src, 'clean subscription should store tz');
}

function testPushSubscribeRefreshesTzOnDuplicate($test) {
    $src = file_get_contents(dirname(__DIR__) . '/api/push.php');
    // On a duplicate endpoint the tz + lang are refreshed rather than early-returning
    $test->assertContains("\$subs[\$i]['tz']   = \$tz", $src,
        'duplicate endpoint should refresh stored tz');
}

function testWebPushCronResolvesViewerTimezone($test) {
    $src = file_get_contents(dirname(__DIR__) . '/cron/send-web-push-notifications.php');
    $test->assertContains('fav_resolve_user_timezone($favData, $sub[\'tz\'] ?? null)', $src,
        'web push cron should resolve viewer timezone with per-device fallback');
}

function testWebPushCronAnnotatesViewerTime($test) {
    $src = file_get_contents(dirname(__DIR__) . '/cron/send-web-push-notifications.php');
    $test->assertContains('$annotTzName', $src, 'web push cron should compute an annotation timezone');
    $test->assertContains('setTimezone($annotTz)', $src, 'web push cron should convert to the annotation timezone');
}

function testFavResolveUserTimezoneAvailableToWebPush($test) {
    // Loaded via config.php which both cron scripts require
    $test->assertTrue(function_exists('fav_resolve_user_timezone'),
        'fav_resolve_user_timezone() should be available to the web push cron');
}
