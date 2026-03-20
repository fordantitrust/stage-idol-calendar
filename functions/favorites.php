<?php
/**
 * Favorites System Helper Functions
 * Idol Stage Timetable
 */

// ─── UUID v7 (RFC 9562) ───────────────────────────────────────────────────────
// Layout: 48-bit ms timestamp | 4-bit ver(7) | 12-bit rand_a | 2-bit var(10) | 62-bit rand_b

function fav_generate_uuid_v7(): string {
    $ms   = (int)(microtime(true) * 1000);
    $rand = random_bytes(10);

    $b = [
        ($ms >> 40) & 0xff,
        ($ms >> 32) & 0xff,
        ($ms >> 24) & 0xff,
        ($ms >> 16) & 0xff,
        ($ms >>  8) & 0xff,
        ($ms)       & 0xff,
        0x70 | (ord($rand[0]) & 0x0f),  // version 7 + 4-bit rand_a
        ord($rand[1]),                   // 8-bit rand_a
        0x80 | (ord($rand[2]) & 0x3f),  // variant 10 + 6-bit rand_b
        ord($rand[3]),
        ord($rand[4]),
        ord($rand[5]),
        ord($rand[6]),
        ord($rand[7]),
        ord($rand[8]),
        ord($rand[9]),
    ];

    return sprintf(
        '%02x%02x%02x%02x-%02x%02x-%02x%02x-%02x%02x-%02x%02x%02x%02x%02x%02x',
        $b[0],  $b[1],  $b[2],  $b[3],
        $b[4],  $b[5],
        $b[6],  $b[7],
        $b[8],  $b[9],
        $b[10], $b[11], $b[12], $b[13], $b[14], $b[15]
    );
}

// ─── HMAC ─────────────────────────────────────────────────────────────────────

function fav_hmac(string $token): string {
    return substr(hash_hmac('sha256', $token, FAVORITES_HMAC_SECRET), 0, FAVORITES_HMAC_LENGTH);
}

function fav_build_slug(string $token): string {
    return $token . '-' . fav_hmac($token);
}

// ─── Slug Parsing & Validation ────────────────────────────────────────────────

/**
 * Parse and validate a favorites slug ({uuid}-{hmac}).
 * Returns ['token' => ..., 'hmac' => ...] or null on failure.
 */
function fav_parse_slug(string $slug): ?array {
    $expectedLen = 36 + 1 + FAVORITES_HMAC_LENGTH;
    if (strlen($slug) !== $expectedLen) return null;

    $token = substr($slug, 0, 36);
    $hmac  = substr($slug, 37);

    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $token)) {
        return null;
    }
    if (!preg_match('/^[0-9a-f]+$/', $hmac)) return null;
    if (!hash_equals(fav_hmac($token), $hmac)) return null;

    return ['token' => $token, 'hmac' => $hmac];
}

// ─── File Path (shard on last 3 hex chars of UUID — fully random) ─────────────

function fav_file_path(string $token): string {
    $shard = substr($token, -3); // e.g., 'abc' → 4096 possible shard dirs
    return FAVORITES_DIR . '/' . $shard . '/' . $token . '.json';
}

// ─── Read ─────────────────────────────────────────────────────────────────────

function fav_read(string $token): ?array {
    $path = fav_file_path($token);
    if (!file_exists($path)) return null;

    $raw = @file_get_contents($path);
    if ($raw === false) return null;

    $data = json_decode($raw, true);
    if (!is_array($data)) return null;

    $lastAccess = strtotime($data['last_access'] ?? $data['created_at'] ?? '1970-01-01');
    if (time() - $lastAccess > FAVORITES_TTL) {
        @unlink($path);
        return null;
    }

    return $data;
}

// ─── Write (atomic) ───────────────────────────────────────────────────────────

function fav_write(array $data): bool {
    $path = fav_file_path($data['token']);
    $dir  = dirname($path);

    if (!is_dir($dir) && !mkdir($dir, 0755, true)) return false;

    $tmp = $path . '.tmp.' . getmypid();
    if (file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
        return false;
    }
    return rename($tmp, $path);
}

// ─── Touch last_access (throttled) ───────────────────────────────────────────

function fav_touch(array &$data): bool {
    $lastAccess = strtotime($data['last_access'] ?? $data['created_at']);
    if (time() - $lastAccess < FAVORITES_LAST_ACCESS_THROTTLE) return false;
    $data['last_access'] = date('c');
    return fav_write($data);
}

// ─── Rate Limiting ────────────────────────────────────────────────────────────

function fav_check_rate_limit(string $ip): bool {
    $key  = substr(hash('sha256', $ip), 0, 16);
    $path = FAVORITES_RL_DIR . '/fav_rl_' . $key . '.json';

    $now    = time();
    $window = $now - FAVORITES_RATE_WINDOW;

    $fp = @fopen($path, file_exists($path) ? 'r+' : 'w+');
    if (!$fp) return false;

    flock($fp, LOCK_EX);

    $raw        = stream_get_contents($fp);
    $timestamps = [];
    if ($raw) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $timestamps = array_values(array_filter($decoded, fn($t) => $t > $window));
        }
    }

    $allowed = count($timestamps) < FAVORITES_RATE_LIMIT;
    if ($allowed) {
        $timestamps[] = $now;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($timestamps));
    }

    flock($fp, LOCK_UN);
    fclose($fp);

    return $allowed;
}

// ─── Probabilistic GC ─────────────────────────────────────────────────────────

function fav_maybe_cleanup(int $probability = 200): void {
    if (mt_rand(1, $probability) !== 1) return;
    fav_cleanup_expired();
}

function fav_cleanup_expired(): int {
    $deleted   = 0;
    $now       = time();
    $shardDirs = glob(FAVORITES_DIR . '/???', GLOB_ONLYDIR);
    if (!$shardDirs) return 0;

    foreach ($shardDirs as $dir) {
        $files = glob($dir . '/*.json');
        if (!$files) continue;
        foreach ($files as $file) {
            $raw  = @file_get_contents($file);
            $data = $raw ? json_decode($raw, true) : null;
            if (!$data) { @unlink($file); $deleted++; continue; }
            $lastAccess = strtotime($data['last_access'] ?? $data['created_at'] ?? '1970-01-01');
            if ($now - $lastAccess > FAVORITES_TTL) {
                @unlink($file);
                // Remove co-located personal feed cache if it exists
                $icsFile = substr($file, 0, -5) . '.ics'; // replace .json → .ics
                if (file_exists($icsFile)) @unlink($icsFile);
                $deleted++;
            }
        }
    }
    return $deleted;
}
