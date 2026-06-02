<?php
/**
 * Email Notification Configuration
 *
 * Loads SMTP settings from config/email-config.json (editable via Admin UI).
 */

$defaultEmailConfig = [
    'enabled' => false,
    'smtp_host' => '',
    'smtp_port' => 587,
    'smtp_encryption' => 'tls',
    'smtp_username' => '',
    'smtp_password' => '',
    'from_email' => '',
    'from_name' => defined('APP_NAME') ? APP_NAME : 'Idol Stage Timetable',
    'recipients' => '',
];

$emailConfigFile = __DIR__ . '/email-config.json';
$emailConfig = $defaultEmailConfig;

if (file_exists($emailConfigFile)) {
    $jsonData = @json_decode(file_get_contents($emailConfigFile), true);
    if (is_array($jsonData)) {
        $emailConfig = array_merge($defaultEmailConfig, $jsonData);
    }
}

define('EMAIL_ENABLED', (bool)($emailConfig['enabled'] ?? false));
define('EMAIL_SMTP_HOST', $emailConfig['smtp_host'] ?? '');
define('EMAIL_SMTP_PORT', (int)($emailConfig['smtp_port'] ?? 587));
define('EMAIL_SMTP_ENCRYPTION', $emailConfig['smtp_encryption'] ?? 'tls');
define('EMAIL_SMTP_USERNAME', $emailConfig['smtp_username'] ?? '');
define('EMAIL_SMTP_PASSWORD', $emailConfig['smtp_password'] ?? '');
define('EMAIL_FROM_EMAIL', $emailConfig['from_email'] ?? '');
define('EMAIL_FROM_NAME', $emailConfig['from_name'] ?? (defined('APP_NAME') ? APP_NAME : 'Idol Stage Timetable'));
define('EMAIL_RECIPIENTS', $emailConfig['recipients'] ?? '');
