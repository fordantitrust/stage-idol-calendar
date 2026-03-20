<?php
/**
 * Generate HMAC Secret for Favorites System
 *
 * Usage:
 *   php tools/generate-favorites-secret.php
 *
 * Then paste the output into config/favorites.php:
 *   define('FAVORITES_HMAC_SECRET', '...');
 */
$secret = bin2hex(random_bytes(32)); // 64 hex chars

echo "\n";
echo "=== Favorites HMAC Secret ===\n\n";
echo $secret . "\n\n";
echo "Paste into config/favorites.php:\n";
echo "define('FAVORITES_HMAC_SECRET', '{$secret}');\n\n";
echo "Keep this secret safe — if lost, all existing Favorites URLs become invalid.\n\n";
