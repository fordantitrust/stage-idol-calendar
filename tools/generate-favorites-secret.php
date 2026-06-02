<?php
/**
 * Generate HMAC Secret for Favorites System
 *
 * Usage:
 *   php tools/generate-favorites-secret.php
 *
 * Writes the secret to config/favorites-config.json, which is gitignored
 * (config/*-config.json) and HTTP-denied (config/.htaccess). The secret is
 * never stored in a tracked source file.
 */
$secret = bin2hex(random_bytes(32)); // 64 hex chars

$configFile = __DIR__ . '/../config/favorites-config.json';

$existing = [];
if (is_file($configFile)) {
    $decoded = @json_decode(file_get_contents($configFile), true);
    if (is_array($decoded)) {
        $existing = $decoded;
    }
}

if (!empty($existing['hmac_secret'])) {
    fwrite(STDERR, "\n⚠️  config/favorites-config.json already has an hmac_secret.\n");
    fwrite(STDERR, "    Overwriting it will invalidate ALL existing Favorites URLs.\n");
    fwrite(STDERR, "    To proceed, delete the file (or its hmac_secret) and re-run.\n\n");
    exit(1);
}

$existing['hmac_secret'] = $secret;

if (file_put_contents($configFile, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX) === false) {
    fwrite(STDERR, "\n❌ Failed to write {$configFile}\n\n");
    exit(1);
}

echo "\n";
echo "=== Favorites HMAC Secret ===\n\n";
echo "Wrote a new 64-char secret to: config/favorites-config.json\n";
echo "(gitignored — never committed)\n\n";
echo "Keep this file safe — if lost, all existing Favorites URLs become invalid.\n\n";
