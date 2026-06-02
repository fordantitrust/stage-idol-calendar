<?php
/**
 * Email notification tests.
 */

require_once __DIR__ . '/../config.php';

function testEmailConfigFilesLoaded($test) {
    $config = file_get_contents(dirname(__DIR__) . '/config.php');
    $test->assertContains("config/email.php", $config, 'config.php should load email config');
    $test->assertContains("functions/email.php", $config, 'config.php should load email helpers');

    $test->assertTrue(defined('EMAIL_ENABLED'), 'EMAIL_ENABLED should be defined');
    $test->assertTrue(function_exists('email_send'), 'email_send() should be loaded');
    $test->assertTrue(function_exists('email_notify_program_request_created'), 'Program request email helper should be loaded');
    $test->assertTrue(function_exists('email_notify_event_request_created'), 'Event request email helper should be loaded');
}

function testEmailDefaultConfigDisabled($test) {
    $content = file_get_contents(dirname(__DIR__) . '/config/email.php');
    $test->assertContains("'enabled' => false", $content, 'Default email config should be disabled');
    $test->assertContains("'smtp_port' => 587", $content, 'Default SMTP port should be 587');
    $test->assertContains("'smtp_encryption' => 'tls'", $content, 'Default SMTP encryption should be TLS');
}

function testEmailParseRecipients($test) {
    $recipients = email_parse_recipients("Admin <bad>\nadmin@example.com, staff@example.com; invalid@@example\nadmin@example.com");
    sort($recipients);
    $test->assertEquals(['admin@example.com', 'staff@example.com'], $recipients, 'Should parse valid unique recipients and drop invalid values');
}

function testEmailBodyEscapesHtml($test) {
    [$html, $text] = email_format_request_body('New Program Request', [
        'Title' => '<script>alert(1)</script>',
        'Requester' => 'Ann "Admin"',
    ]);

    $test->assertContains('&lt;script&gt;alert(1)&lt;/script&gt;', $html, 'HTML body should escape script tags');
    $test->assertContains('Ann &quot;Admin&quot;', $html, 'HTML body should escape quotes');
    $test->assertContains('<script>alert(1)</script>', $text, 'Plain-text body should preserve readable text');
}

function testEmailSubjectsUseSiteName($test) {
    $emailContent = file_get_contents(dirname(__DIR__) . '/functions/email.php');
    $adminContent = file_get_contents(dirname(__DIR__) . '/admin/api.php');

    $test->assertTrue(function_exists('email_site_name'), 'email_site_name() helper should exist');
    $test->assertEquals(get_site_title(), email_site_name(), 'Email site name should use configured site title');
    $test->assertContains("'[' . email_site_name() . '] New Program Request'", $emailContent, 'Program request subject should use site name');
    $test->assertContains("'[' . email_site_name() . '] New EventRequest'", $emailContent, 'Event request subject should use site name');
    $test->assertContains("'] Email Notification Test'", $adminContent, 'Test email subject should use site name');
    $test->assertFalse(strpos($emailContent, '[Idol Stage] New Program Request') !== false, 'Program request subject should not hardcode Idol Stage');
    $test->assertFalse(strpos($emailContent, '[Idol Stage] New Event Request') !== false, 'Event request subject should not hardcode Idol Stage');
    $test->assertFalse(strpos($adminContent, 'Email notification test from <strong>Idol Stage Timetable</strong>') !== false, 'Test email body should not hardcode app name');
}

function testEmailAdminUrlIgnoresApiScriptDirectory($test) {
    $oldServer = $_SERVER;

    $_SERVER['HTTPS'] = 'on';
    $_SERVER['HTTP_HOST'] = 'idoltrack.example.com';
    $_SERVER['SCRIPT_NAME'] = '/api/request.php';
    $test->assertEquals('https://idoltrack.example.com/admin/', email_admin_url(), 'Public API request emails should link to /admin/');

    $_SERVER['SCRIPT_NAME'] = '/idoltrack/api/event-request.php';
    $test->assertEquals('https://idoltrack.example.com/idoltrack/admin/', email_admin_url(), 'Subdirectory API request emails should preserve app base path');

    $_SERVER['SCRIPT_NAME'] = '/admin/api.php';
    $test->assertEquals('https://idoltrack.example.com/admin/', email_admin_url(), 'Admin API test emails should link to /admin/ without duplicating /admin');

    $_SERVER['SCRIPT_NAME'] = '';
    $_SERVER['PHP_SELF'] = '';
    $_SERVER['REQUEST_URI'] = '/api/request.php?action=create';
    $test->assertEquals('https://idoltrack.example.com/admin/', email_admin_url(), 'Request URI fallback should link to /admin/, not /api/admin/');

    [$html, $text] = email_format_request_body('New Program Request', ['Title' => 'Test']);
    $test->assertFalse(strpos($html, '/api/admin') !== false, 'HTML email body should not link to /api/admin');
    $test->assertFalse(strpos($text, '/api/admin') !== false, 'Plain-text email body should not link to /api/admin');

    $_SERVER = $oldServer;
}

function testEmailSendDisabledGuardBeforeSmtp($test) {
    $content = file_get_contents(dirname(__DIR__) . '/functions/email.php');
    $guardPos = strpos($content, "empty(\$options['force']) && !email_is_enabled()");
    $socketPos = strpos($content, 'stream_socket_client');

    $test->assertNotFalse($guardPos, 'email_send() should have a disabled guard');
    $test->assertNotFalse($socketPos, 'email_send() should use native SMTP socket');
    $test->assertTrue($guardPos < $socketPos, 'Disabled guard should run before opening SMTP socket');
}

function testRequestApisTriggerEmailNotifications($test) {
    $programApi = file_get_contents(dirname(__DIR__) . '/api/request.php');
    $eventApi = file_get_contents(dirname(__DIR__) . '/api/event-request.php');

    $test->assertContains('email_notify_program_request_created($requestId', $programApi, 'Program request API should trigger email notification');
    $test->assertContains('email_notify_event_request_created($requestId', $eventApi, 'Event request API should trigger email notification');
}

function testAdminApiHasEmailActions($test) {
    $content = file_get_contents(dirname(__DIR__) . '/admin/api.php');

    foreach (['email_config_get', 'email_config_save', 'email_test_send'] as $action) {
        $test->assertContains("case '{$action}'", $content, "Admin API should include {$action}");
    }

    $test->assertContains('function getEmailConfig()', $content, 'Admin API should implement getEmailConfig()');
    $test->assertContains('function saveEmailConfig()', $content, 'Admin API should implement saveEmailConfig()');
    $test->assertContains('function sendEmailTest()', $content, 'Admin API should implement sendEmailTest()');
}

function testAdminApiLoadsEmailHelpersDefensively($test) {
    $content = file_get_contents(dirname(__DIR__) . '/admin/api.php');

    $test->assertContains('function ensureEmailHelpersLoaded()', $content, 'Admin API should defensively load email helpers');
    $test->assertContains("../functions/email.php", $content, 'Admin API should require functions/email.php when helpers are missing');
    $test->assertContains("!function_exists('email_parse_recipients')", $content, 'Admin API should guard missing email_parse_recipients()');
    $test->assertContains("!function_exists('email_send')", $content, 'Admin API should guard missing email_send()');
    $test->assertContains('ensureEmailHelpersLoaded();', $content, 'Email config/test actions should call helper loader before using email functions');
}

function testAdminI18nHasEmailKeys($test) {
    $content = file_get_contents(dirname(__DIR__) . '/admin/js/admin-i18n.js');

    foreach ([
        'settings.subtab.email',
        'settings.email',
        'settings.emailDesc',
        'settings.emailRecipients',
        'settings.emailEnabled',
        'settings.saveEmail',
        'settings.emailTest',
    ] as $key) {
        $test->assertContains("'{$key}'", $content, "Admin i18n should include {$key}");
    }
}

function testAdminUiHasEmailSettings($test) {
    $content = file_get_contents(dirname(__DIR__) . '/admin/index.php');

    $test->assertContains('settingsSubtab-email', $content, 'Admin UI should have Email settings sub-tab');
    $test->assertContains('emailSmtpHost', $content, 'Admin UI should have SMTP host input');
    $test->assertContains('emailRecipients', $content, 'Admin UI should have recipients textarea');
    $test->assertContains('loadEmailSetting()', $content, 'Admin UI should load email settings');
    $test->assertContains('saveEmailSetting()', $content, 'Admin UI should save email settings');
    $test->assertContains('sendEmailTest()', $content, 'Admin UI should test email settings');
}

function testRequestEmptyStatesMatch($test) {
    $content = file_get_contents(dirname(__DIR__) . '/admin/index.php');

    $test->assertContains(
        '<td colspan="7" style="text-align:center;color:#999">${adminT(\'req.noRequests\')}</td>',
        $content,
        'Program Requests empty state should use centered muted No requests text'
    );
    $test->assertContains(
        '<td colspan="6" style="text-align:center;color:#999">${adminT(\'req.noRequests\')}</td>',
        $content,
        'Event Requests empty state should match Program Requests empty state'
    );
}
