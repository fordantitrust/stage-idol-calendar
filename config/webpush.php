<?php
/**
 * Web Push Notifications Configuration
 *
 * Loads configuration from config/webpush-config.json (editable via Admin UI)
 * Falls back to defaults if file doesn't exist
 */

$defaultWebpushConfig = [
    'enabled'              => false,
    'vapid_public_key'     => '',
    'vapid_private_key_pem' => '',
    'vapid_subject'        => 'mailto:admin@stageidol.local',
    'site_url'             => '',
    'notify_before_minutes' => 60,
    'max_subs_per_token'   => 5,
    // SSRF defense — when true AND PRODUCTION_MODE is false, the endpoint
    // allow-list also accepts localhost / 127.0.0.1 / ::1 / host.docker.internal
    // for testing with a mock push gateway. Never enable in production.
    'allow_localhost'      => false,
];

$webpushConfigFile = __DIR__ . '/webpush-config.json';
$webpushConfig = $defaultWebpushConfig;

if (file_exists($webpushConfigFile)) {
    $jsonData = @json_decode(file_get_contents($webpushConfigFile), true);
    if (is_array($jsonData)) {
        $webpushConfig = array_merge($defaultWebpushConfig, $jsonData);
    }
}

define('WEBPUSH_ENABLED',          (bool)($webpushConfig['enabled'] ?? false));
define('WEBPUSH_VAPID_PUBLIC_KEY', $webpushConfig['vapid_public_key'] ?? '');
define('WEBPUSH_VAPID_PRIVATE_KEY_PEM', $webpushConfig['vapid_private_key_pem'] ?? '');
define('WEBPUSH_VAPID_SUBJECT',    $webpushConfig['vapid_subject'] ?? 'mailto:admin@stageidol.local');
define('WEBPUSH_SITE_URL',         rtrim($webpushConfig['site_url'] ?? '', '/'));
define('WEBPUSH_NOTIFY_BEFORE_MINUTES', (int)($webpushConfig['notify_before_minutes'] ?? 60));
define('WEBPUSH_MAX_SUBS_PER_TOKEN',    (int)($webpushConfig['max_subs_per_token'] ?? 5));
define('WEBPUSH_ALLOW_LOCALHOST',       (bool)($webpushConfig['allow_localhost'] ?? false));
