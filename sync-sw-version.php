<?php
/**
 * sync-sw-version.php
 *
 * Sync CACHE_VERSION in service-worker.js with APP_VERSION from config/app.php.
 * Run manually after `php tools/update-version.php X.Y.Z` to invalidate PWA caches.
 *
 * Usage:
 *   php sync-sw-version.php
 *
 * Exit codes:
 *   0 — success or no change needed (idempotent)
 *   1 — error (file missing, pattern not found, write failed)
 */

// CLI-only guard — also blocked at Apache level via .htaccess
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("This script is CLI-only.\n");
}

$rootDir = __DIR__;
require_once "$rootDir/config/app.php";   // defines APP_VERSION

$swPath = "$rootDir/service-worker.js";

if (!file_exists($swPath)) {
    fwrite(STDERR, "Error: service-worker.js not found at $swPath\n");
    exit(1);
}

$content = file_get_contents($swPath);
if ($content === false) {
    fwrite(STDERR, "Error: failed to read service-worker.js\n");
    exit(1);
}

if (!preg_match("/const\s+CACHE_VERSION\s*=\s*'(\d+\.\d+\.\d+)'\s*;/", $content, $m)) {
    fwrite(STDERR, "Error: CACHE_VERSION line not found in service-worker.js\n");
    exit(1);
}

$currentSwVersion = $m[1];
$targetVersion    = APP_VERSION;

if ($currentSwVersion === $targetVersion) {
    echo "✅ service-worker.js already at v$targetVersion — no changes needed.\n";
    exit(0);
}

$new = preg_replace(
    "/const\s+CACHE_VERSION\s*=\s*'\d+\.\d+\.\d+'\s*;/",
    "const CACHE_VERSION = '$targetVersion';",
    $content,
    1
);

if ($new === null || $new === $content) {
    fwrite(STDERR, "Error: failed to replace CACHE_VERSION in service-worker.js\n");
    exit(1);
}

if (file_put_contents($swPath, $new) === false) {
    fwrite(STDERR, "Error: failed to write service-worker.js\n");
    exit(1);
}

echo "✅ service-worker.js: v$currentSwVersion → v$targetVersion\n";
exit(0);
