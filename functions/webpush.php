<?php
/**
 * Web Push Notifications — VAPID + RFC 8291 (aes128gcm) Implementation
 *
 * Implements Web Push without external dependencies using PHP 8.1+ built-ins:
 *   - openssl_pkey_* for EC P-256 key operations + ECDH
 *   - hash_hkdf() for RFC 5869 key derivation
 *   - openssl_encrypt() for AES-128-GCM
 *   - openssl_sign() for JWT ES256 (VAPID auth)
 */

// ── Base64url helpers ─────────────────────────────────────────────────────────

function webpush_urlsafe_b64encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function webpush_urlsafe_b64decode(string $data): string {
    $pad = 4 - (strlen($data) % 4);
    if ($pad !== 4) {
        $data .= str_repeat('=', $pad);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

// ── OpenSSL config helper ─────────────────────────────────────────────────────

/**
 * Build openssl_pkey_new() config array with EC P-256 params.
 * On Windows, OpenSSL cannot locate its config file via environment variables;
 * we pass it explicitly via the 'config' key to avoid "no such file" errors.
 */
function webpush_openssl_ec_config(): array {
    $cfg = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];
    if (PHP_OS_FAMILY === 'Windows') {
        // php_ini_loaded_file() is reliable in both CLI and Apache mod_php contexts
        $phpDir = php_ini_loaded_file() ? dirname(php_ini_loaded_file()) : '';
        $candidates = array_filter([
            getenv('OPENSSL_CONF'),                                  // env override (highest priority)
            $phpDir . '/extras/ssl/openssl.cnf',                    // XAMPP: same dir as php.ini
            dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf',       // CLI fallback
            dirname(PHP_BINARY) . '/../extras/ssl/openssl.cnf',
            dirname(PHP_BINARY) . '/ssl/openssl.cnf',
            dirname(PHP_BINARY) . '/../ssl/openssl.cnf',
            PHP_PREFIX  . '/extras/ssl/openssl.cnf',
            PHP_BINDIR  . '/extras/ssl/openssl.cnf',
        ]);
        foreach ($candidates as $c) {
            if (file_exists($c)) { $cfg['config'] = realpath($c); break; }
        }
    }
    return $cfg;
}

// ── EC public key helpers ─────────────────────────────────────────────────────

/**
 * Convert raw 65-byte uncompressed EC P-256 public key to PEM (SubjectPublicKeyInfo)
 */
function webpush_raw_pubkey_to_pem(string $raw): string {
    // DER prefix for EC P-256 SubjectPublicKeyInfo
    $der = "\x30\x59"           // SEQUENCE (89 bytes)
        . "\x30\x13"            // SEQUENCE (AlgorithmIdentifier)
        . "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"   // OID ecPublicKey
        . "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07" // OID prime256v1
        . "\x03\x42\x00"        // BIT STRING (66 bytes, 0 unused bits)
        . $raw;                  // 65 bytes: 0x04 || x || y
    return "-----BEGIN PUBLIC KEY-----\n"
        . chunk_split(base64_encode($der), 64, "\n")
        . "-----END PUBLIC KEY-----\n";
}

/**
 * Extract raw 65-byte uncompressed EC point from openssl_pkey_get_details()
 */
function webpush_get_raw_public_key(array $details): string {
    $x = str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT);
    $y = str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
    return "\x04" . $x . $y;
}

// ── VAPID JWT ─────────────────────────────────────────────────────────────────

/**
 * Convert DER-encoded ECDSA signature to raw R||S format (64 bytes) for JWT ES256
 */
function webpush_der_to_raw_sig(string $der): string {
    $pos = 2; // skip SEQUENCE tag + length
    // Handle long-form DER length
    if (strlen($der) > 2 && (ord($der[1]) & 0x80)) {
        $pos += ord($der[1]) & 0x7f;
    }
    // R integer
    $pos++;  // INTEGER tag
    $rLen = ord($der[$pos++]);
    $r = substr($der, $pos, $rLen);
    $pos += $rLen;
    // S integer
    $pos++;  // INTEGER tag
    $sLen = ord($der[$pos++]);
    $s = substr($der, $pos, $sLen);

    // Remove DER sign byte if present, pad to 32 bytes
    $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
    $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
    return $r . $s;
}

/**
 * Create VAPID JWT (ES256) for push authorization
 */
function webpush_vapid_jwt(string $endpoint, string $subject, array $cfg): string {
    $parsed = parse_url($endpoint);
    $audience = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
    if (!empty($parsed['port'])) {
        $audience .= ':' . $parsed['port'];
    }

    $header  = webpush_urlsafe_b64encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $payload = webpush_urlsafe_b64encode(json_encode([
        'aud' => $audience,
        'exp' => time() + 43200,
        'sub' => $subject,
    ]));
    $sigInput = $header . '.' . $payload;

    $privateKey = openssl_pkey_get_private($cfg['vapid_private_key_pem']);
    if (!$privateKey) {
        return '';
    }
    openssl_sign($sigInput, $der, $privateKey, OPENSSL_ALGO_SHA256);
    return $sigInput . '.' . webpush_urlsafe_b64encode(webpush_der_to_raw_sig($der));
}

// ── RFC 8291 Payload Encryption ───────────────────────────────────────────────

/**
 * Encrypt push payload using RFC 8291 (aes128gcm content encoding)
 *
 * @param string $payload  Raw JSON string to send
 * @param array  $sub      Subscription: {endpoint, keys: {p256dh, auth}}
 * @return string|null  Encrypted binary blob, or null on failure
 */
function webpush_encrypt(string $payload, array $sub): ?string {
    if (empty($sub['keys']['p256dh']) || empty($sub['keys']['auth'])) {
        return null;
    }

    // Decode subscriber keys
    $uaPublicKeyRaw = webpush_urlsafe_b64decode($sub['keys']['p256dh']); // 65 bytes
    $authSecret     = webpush_urlsafe_b64decode($sub['keys']['auth']);    // 16 bytes

    if (strlen($uaPublicKeyRaw) !== 65 || $uaPublicKeyRaw[0] !== "\x04") {
        return null;
    }

    // Generate ephemeral sender key pair
    $ephemKey = openssl_pkey_new(webpush_openssl_ec_config());
    if (!$ephemKey) {
        return null;
    }
    $ephemDetails = openssl_pkey_get_details($ephemKey);
    $asPublicKeyRaw = webpush_get_raw_public_key($ephemDetails); // 65 bytes

    // ECDH: derive shared secret
    $uaPem = webpush_raw_pubkey_to_pem($uaPublicKeyRaw);
    $uaKey = openssl_pkey_get_public($uaPem);
    if (!$uaKey) {
        return null;
    }
    $sharedSecret = openssl_pkey_derive($uaKey, $ephemKey, 32);
    if ($sharedSecret === false || strlen($sharedSecret) !== 32) {
        return null;
    }

    // Salt (16 random bytes)
    $salt = random_bytes(16);

    // HKDF key derivation (RFC 8291 Section 3.3)
    // PRK = HKDF-SHA256(IKM=sharedSecret, salt=authSecret, info="WebPush: info\x00"||ua_pub||as_pub)
    // hash_hkdf() always returns raw binary; no $raw_output param (unlike hash())
    $keyInfo = "WebPush: info\x00" . $uaPublicKeyRaw . $asPublicKeyRaw;
    $prk = hash_hkdf('sha256', $sharedSecret, 32, $keyInfo, $authSecret);

    // CEK (content encryption key, 16 bytes)
    $cek   = hash_hkdf('sha256', $prk, 16, "Content-Encoding: aes128gcm\x00", $salt);
    // Nonce (12 bytes)
    $nonce = hash_hkdf('sha256', $prk, 12, "Content-Encoding: nonce\x00", $salt);

    // Add padding delimiter (0x02 = last-record marker per RFC 8188)
    $padded = $payload . "\x02";

    // AES-128-GCM encryption
    $tag = '';
    $ciphertext = openssl_encrypt($padded, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    if ($ciphertext === false) {
        return null;
    }

    // Build RFC 8188 aes128gcm header + ciphertext
    // header: salt(16) + rs(4 BE uint32) + idlen(1) + keyid(65)
    $header = $salt
        . pack('N', 4096)   // record size
        . "\x41"            // idlen = 65 (0x41)
        . $asPublicKeyRaw;  // sender ephemeral public key as keyid

    return $header . $ciphertext . $tag;
}

// ── Endpoint allow-list (SSRF defense) ────────────────────────────────────────

/**
 * Known push-service hosts.
 *   - 'exact'  : full hostname must match
 *   - 'suffix' : hostname must end with the suffix and not be the bare suffix
 */
function webpush_allowed_hosts(): array {
    return [
        'exact'  => [
            'fcm.googleapis.com',                  // FCM (Chrome, Edge, Opera)
            'updates.push.services.mozilla.com',  // Mozilla autopush (Firefox)
            'web.push.apple.com',                  // Apple Web Push (Safari)
        ],
        'suffix' => [
            '.notify.windows.com',  // Microsoft WNS
            '.push.apple.com',       // alternate Apple push hosts
        ],
    ];
}

/**
 * Validate a push subscription endpoint URL against the known-push-service
 * allow-list. Returns the original URL string if accepted, or null if rejected.
 *
 * Production rule: must be HTTPS to a known push-service host.
 * Dev rule: when WEBPUSH_ALLOW_LOCALHOST is true AND PRODUCTION_MODE is false,
 *           also allow localhost / 127.0.0.1 / ::1 / host.docker.internal
 *           over http or https (Service Workers treat localhost as secure).
 */
function webpush_validate_endpoint(string $endpoint): ?string {
    $parts = @parse_url($endpoint);
    if (!is_array($parts) || empty($parts['host'])) {
        return null;
    }
    $scheme = strtolower($parts['scheme'] ?? '');
    $host   = strtolower($parts['host']);

    // Dev/test exception: localhost only when explicitly enabled AND not in production
    $allowLocal = defined('WEBPUSH_ALLOW_LOCALHOST') && WEBPUSH_ALLOW_LOCALHOST;
    $isProd     = defined('PRODUCTION_MODE') && PRODUCTION_MODE;
    if ($allowLocal && !$isProd) {
        $localHosts = ['localhost', '127.0.0.1', '::1', 'host.docker.internal'];
        if (in_array($host, $localHosts, true) && ($scheme === 'http' || $scheme === 'https')) {
            return $endpoint;
        }
    }

    // Production path: HTTPS only
    if ($scheme !== 'https') {
        return null;
    }
    // Block IP literals (covers SSRF to 10.x, 192.168.x, link-local, etc.)
    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        return null;
    }

    $allowed = webpush_allowed_hosts();
    if (in_array($host, $allowed['exact'], true)) {
        return $endpoint;
    }
    foreach ($allowed['suffix'] as $suf) {
        // suffix match, but reject the bare suffix as a hostname (e.g. ".notify.windows.com" → "notify.windows.com")
        $bare = ltrim($suf, '.');
        if ($host !== $bare && str_ends_with($host, $suf)) {
            return $endpoint;
        }
    }
    return null;
}

// ── Send push notification ────────────────────────────────────────────────────

/**
 * Send a Web Push notification to a single subscription
 *
 * @param array  $sub      Subscription: {endpoint, keys: {p256dh, auth}}
 * @param string $payload  JSON string (notification data)
 * @param array  $cfg      webpush config (vapid_public_key, vapid_private_key_pem, vapid_subject)
 * @return array {success, status_code, expired, error}
 */
function webpush_send(array $sub, string $payload, array $cfg): array {
    if (empty($sub['endpoint'])) {
        return ['success' => false, 'status_code' => 0, 'expired' => false, 'error' => 'no_endpoint'];
    }

    // Defense in depth: reject if endpoint is no longer allow-listed.
    // Mark expired=true so callers (cron) drop the subscription via the same
    // path used for HTTP 410 Gone, purging any pre-allow-list records.
    if (webpush_validate_endpoint($sub['endpoint']) === null) {
        return ['success' => false, 'status_code' => 0, 'expired' => true, 'error' => 'endpoint_not_allowed'];
    }

    $encrypted = webpush_encrypt($payload, $sub);
    if ($encrypted === null) {
        return ['success' => false, 'status_code' => 0, 'expired' => false, 'error' => 'encrypt_failed'];
    }

    $subject = $cfg['vapid_subject'] ?? 'mailto:admin@stageidol.local';
    $jwt     = webpush_vapid_jwt($sub['endpoint'], $subject, $cfg);
    $vapidAuth = 'vapid t=' . $jwt . ',k=' . ($cfg['vapid_public_key'] ?? '');

    $ch = curl_init($sub['endpoint']);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Authorization: ' . $vapidAuth,
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'TTL: 86400',
            'Urgency: normal',
        ],
        CURLOPT_POSTFIELDS     => $encrypted,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response   = curl_exec($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError  = curl_error($ch);
    curl_close($ch);

    return [
        'success'     => $statusCode >= 200 && $statusCode < 300,
        'status_code' => $statusCode,
        'expired'     => $statusCode === 410,
        'error'       => $curlError ?: ($statusCode >= 400 ? "HTTP $statusCode" : null),
    ];
}

// ── Key generation ────────────────────────────────────────────────────────────

/**
 * Generate VAPID EC P-256 key pair
 *
 * @return array|null {public_key: base64url(65-byte uncompressed point), private_key_pem: PEM string}
 */
function webpush_generate_vapid_keys(): ?array {
    $key = openssl_pkey_new(webpush_openssl_ec_config());
    if (!$key) {
        return null;
    }

    $details = openssl_pkey_get_details($key);
    $publicKeyRaw = webpush_get_raw_public_key($details); // 65 bytes

    $exportCfg = null;
    if (PHP_OS_FAMILY === 'Windows') {
        $wcfg = webpush_openssl_ec_config();
        if (!empty($wcfg['config'])) { $exportCfg = ['config' => $wcfg['config']]; }
    }
    openssl_pkey_export($key, $privateKeyPem, null, $exportCfg);

    return [
        'public_key'      => webpush_urlsafe_b64encode($publicKeyRaw),
        'private_key_pem' => $privateKeyPem,
    ];
}

// ── Status check ──────────────────────────────────────────────────────────────

function webpush_is_enabled(): bool {
    return defined('WEBPUSH_ENABLED') && WEBPUSH_ENABLED
        && defined('WEBPUSH_VAPID_PUBLIC_KEY') && !empty(WEBPUSH_VAPID_PUBLIC_KEY)
        && defined('WEBPUSH_VAPID_PRIVATE_KEY_PEM') && !empty(WEBPUSH_VAPID_PRIVATE_KEY_PEM);
}
