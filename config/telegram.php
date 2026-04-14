<?php
/**
 * Telegram Bot Configuration
 *
 * Loads configuration from config/telegram-config.json (editable via Admin UI)
 * Falls back to defaults if file doesn't exist
 */

// Default configuration (used on first install or if JSON file is missing)
$defaultTelegramConfig = [
    'bot_token' => '',
    'bot_username' => '',
    'webhook_secret' => '',
    'notify_before_minutes' => 60,
    'daily_summary_start_hour' => 9,
    'daily_summary_start_minute' => 0,
    'daily_summary_end_hour' => 9,
    'daily_summary_end_minute' => 30,
    'enabled' => false,
];

// Try to load from JSON file (editable by Admin UI)
$telegramConfigFile = __DIR__ . '/telegram-config.json';
$telegramConfig = $defaultTelegramConfig;

if (file_exists($telegramConfigFile)) {
    $jsonData = @json_decode(file_get_contents($telegramConfigFile), true);
    if (is_array($jsonData)) {
        $telegramConfig = array_merge($defaultTelegramConfig, $jsonData);
    }
}

// Define constants for backward compatibility with existing code
define('TELEGRAM_BOT_TOKEN', $telegramConfig['bot_token'] ?? '');
define('TELEGRAM_BOT_USERNAME', $telegramConfig['bot_username'] ?? '');
define('TELEGRAM_WEBHOOK_SECRET', $telegramConfig['webhook_secret'] ?? '');
define('TELEGRAM_NOTIFY_BEFORE_MINUTES', (int)($telegramConfig['notify_before_minutes'] ?? 60));
define('TELEGRAM_DAILY_SUMMARY_START_HOUR', (int)($telegramConfig['daily_summary_start_hour'] ?? 9));
define('TELEGRAM_DAILY_SUMMARY_START_MINUTE', (int)($telegramConfig['daily_summary_start_minute'] ?? 0));
define('TELEGRAM_DAILY_SUMMARY_END_HOUR', (int)($telegramConfig['daily_summary_end_hour'] ?? 9));
define('TELEGRAM_DAILY_SUMMARY_END_MINUTE', (int)($telegramConfig['daily_summary_end_minute'] ?? 30));
define('TELEGRAM_ENABLED', (bool)($telegramConfig['enabled'] ?? false));

// Legacy constants for retention (not used in v5.0.0 but kept for compatibility)
define('TELEGRAM_NOTIFY_HISTORY_DAYS', 7);
define('TELEGRAM_NOTIFY_DUPLICATE_WINDOW', 86400);
