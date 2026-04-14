<?php
/**
 * Telegram Webhook Setup Helper
 *
 * Registers the webhook URL with Telegram Bot API
 * Usage: php setup-telegram-webhook.php https://yourdomain.com
 */

require_once __DIR__ . '/../config.php';

if (!TELEGRAM_BOT_TOKEN) {
    echo "❌ TELEGRAM_BOT_TOKEN not configured in config/telegram.php\n";
    exit(1);
}

if (!TELEGRAM_WEBHOOK_SECRET) {
    echo "❌ TELEGRAM_WEBHOOK_SECRET not configured in config/telegram.php\n";
    exit(1);
}

// Get base URL from argument or config
$baseUrl = $argv[1] ?? null;

if (!$baseUrl) {
    echo "Usage: php setup-telegram-webhook.php https://yourdomain.com\n\n";
    echo "Example:\n";
    echo "  php setup-telegram-webhook.php https://idol-calendar.example.com\n";
    exit(1);
}

$baseUrl = rtrim($baseUrl, '/');

// Append the app's base path (for subdirectory installations like /idoltrack)
// Calculate app root path: this script is at /idoltrack/tools/setup-telegram-webhook.php
// We need to remove /tools/setup-telegram-webhook.php to get /idoltrack
$scriptName = $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '';
if ($scriptName && $scriptName !== '') {
    $appRoot = dirname(dirname($scriptName));  // Go up 2 levels: script -> tools -> root
    if ($appRoot === '.' || $appRoot === '\\' || $appRoot === '/') {
        $appRoot = '';
    }
} else {
    $appRoot = '';
}

$webhookUrl = $baseUrl . $appRoot . '/api/telegram';

echo "🔧 Setting up Telegram Webhook\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
echo "Bot Token: " . substr(TELEGRAM_BOT_TOKEN, 0, 10) . "...\n";
echo "Webhook URL: $webhookUrl\n";
echo "Secret Token: " . substr(TELEGRAM_WEBHOOK_SECRET, 0, 10) . "...\n\n";

// Register webhook
$params = [
    'url' => $webhookUrl,
    'secret_token' => TELEGRAM_WEBHOOK_SECRET,
    'allowed_updates' => ['message', 'callback_query'],
    'drop_pending_updates' => false
];

$result = telegram_api_call('setWebhook', $params);

if (!empty($result)) {
    echo "✅ Webhook registered successfully!\n\n";
    echo "Response:\n";
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

    // Get webhook info to verify
    sleep(1);
    $info = telegram_api_call('getWebhookInfo', []);
    if (!empty($info)) {
        echo "\n📊 Webhook Info:\n";
        echo "URL: " . ($info['url'] ?? 'N/A') . "\n";
        echo "Pending: " . ($info['pending_update_count'] ?? '0') . "\n";
        echo "Last error: " . ($info['last_error_message'] ?? 'None') . "\n";
    }
} else {
    echo "❌ Failed to register webhook\n";
    echo "\nPlease check:\n";
    echo "1. Bot token is correct\n";
    echo "2. Webhook URL is accessible from the internet\n";
    echo "3. URL is HTTPS (Telegram requires it)\n";
    exit(1);
}

echo "\n✨ Setup complete!\n";
echo "Now run: */15 * * * * php " . realpath(__DIR__ . '/send-telegram-notifications.php') . " >> /var/log/tg-notify.log 2>&1\n";
exit(0);
