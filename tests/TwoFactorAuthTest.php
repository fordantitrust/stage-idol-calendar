<?php
/**
 * Admin TOTP 2FA tests.
 */

require_once __DIR__ . '/../config.php';

function testTotpBase32RoundTrip($test) {
    $raw = 'Hello 2FA Secret';
    $encoded = totp_base32_encode($raw);
    $test->assertEquals($raw, totp_base32_decode($encoded), 'Base32 should round-trip binary data');
    $test->assertGreaterThanOrEqual(16, strlen(totp_generate_secret()), 'Generated secret should be long enough for authenticator apps');
}

function testTotpRfc6238Vectors($test) {
    $secret = '12345678901234567890';
    $vectors = [
        59 => '94287082',
        1111111109 => '07081804',
        1111111111 => '14050471',
        1234567890 => '89005924',
        2000000000 => '69279037',
        20000000000 => '65353130',
    ];

    foreach ($vectors as $time => $expected) {
        $counter = intdiv($time, 30);
        $test->assertEquals($expected, totp_hotp($secret, $counter, 8), "RFC 6238 vector should match for {$time}");
    }
}

function testTotpVerifyWindowAndReplay($test) {
    $secret = totp_base32_encode('12345678901234567890');
    $time = 1111111111;
    $code = totp_code($secret, $time);
    $step = intdiv($time, 30);

    $ok = totp_verify($secret, $code, $time + 30);
    $test->assertTrue($ok['valid'], 'TOTP should verify within +/-1 time step');

    $replay = totp_verify($secret, $code, $time, 1, 30, 6, $step);
    $test->assertFalse($replay['valid'], 'TOTP should reject a reused or older time step');

    $bad = totp_verify($secret, $code, $time + 90);
    $test->assertFalse($bad['valid'], 'TOTP should reject code outside allowed window');
}

function testTotpOtpAuthUri($test) {
    $uri = totp_otpauth_uri('Idol Track', 'admin', 'JBSWY3DPEHPK3PXP');
    $test->assertContains('otpauth://totp/', $uri, 'otpauth URI should use TOTP scheme');
    $test->assertContains('issuer=Idol%20Track', $uri, 'otpauth URI should include issuer');
    $test->assertContains('digits=6', $uri, 'otpauth URI should declare 6 digits');
    $test->assertContains('period=30', $uri, 'otpauth URI should declare 30-second period');
}

function testTwofaBackupCodesHashAndConsume($test) {
    $codes = ['ABCDE-12345', 'FGHIJ-67890'];
    $json = twofa_hash_backup_codes($codes);
    $test->assertFalse(strpos($json, 'ABCDE') !== false, 'Backup codes should be hashed, not stored as plaintext');

    $result = twofa_consume_backup_code($json, 'abcde12345');
    $test->assertTrue($result['valid'], 'Backup code should verify case-insensitively without hyphen');
    $test->assertEquals(1, $result['remaining'], 'Consuming a backup code should remove one hash');

    $again = twofa_consume_backup_code($result['hashes_json'], 'ABCDE-12345');
    $test->assertFalse($again['valid'], 'Consumed backup code should not verify again');
}

function testTwofaSchemaAndMigrationSources($test) {
    $setup = file_get_contents(dirname(__DIR__) . '/setup.php');
    $migration = file_get_contents(dirname(__DIR__) . '/tools/migrate-add-admin-2fa-columns.php');
    foreach (['twofa_enabled', 'twofa_secret', 'twofa_backup_codes', 'twofa_confirmed_at', 'twofa_last_used_step'] as $column) {
        $test->assertContains($column, $setup, "setup.php should include {$column}");
        $test->assertContains($column, $migration, "2FA migration should include {$column}");
    }
}

function testTwofaAdminApiEndpoints($test) {
    $api = file_get_contents(dirname(__DIR__) . '/admin/api.php');
    foreach (['twofa_status', 'twofa_begin_setup', 'twofa_confirm_setup', 'twofa_disable', 'twofa_regenerate_backup_codes', 'twofa_reset_user'] as $action) {
        $test->assertContains($action, $api, "Admin API should expose {$action}");
    }
    $test->assertContains('twofa_enabled, twofa_confirmed_at', $api, 'User API should expose public 2FA status fields');
    $test->assertContains('.admin_2fa_columns_ready', $api, 'Admin API should cache confirmed 2FA schema with a file flag');
    $test->assertFalse(strpos($api, 'ensureAdminTwofaColumns') !== false, 'Admin API should not run 2FA schema migration automatically');
    $test->assertFalse(strpos($api, 'ALTER TABLE admin_users ADD COLUMN') !== false, 'Admin API should not alter admin_users schema automatically');
    $test->assertFalse(strpos($api, 'SELECT id, username, display_name, role, is_active, created_at, updated_at, last_login_at, twofa_secret') !== false, 'User API should not expose 2FA secret');
}

function testTwofaLoginFlowSources($test) {
    $admin = file_get_contents(dirname(__DIR__) . '/functions/admin.php');
    $login = file_get_contents(dirname(__DIR__) . '/admin/login.php');

    $test->assertContains('twofa_pending_user_id', $admin, 'Password success with enabled 2FA should create pending session');
    $test->assertContains('admin_complete_twofa', $login, 'Login page should complete pending 2FA step');
    $test->assertContains('login.twofaCode', $login, 'Login page should render 2FA code field');
}

function testTwofaAdminUiAndI18nSources($test) {
    $index = file_get_contents(dirname(__DIR__) . '/admin/index.php');
    $i18n = file_get_contents(dirname(__DIR__) . '/admin/js/admin-i18n.js');

    foreach (['twofaPanel', 'beginTwofaSetup', 'confirmTwofaSetup', 'resetUserTwofa', 'twofaQrBox'] as $needle) {
        $test->assertContains($needle, $index, "Admin UI should include {$needle}");
    }
    foreach (['twofa.title', 'twofa.enable', 'twofa.disable', 'login.err2faInvalid', 'users.th2fa'] as $key) {
        $test->assertContains($key, $i18n, "Admin i18n should include {$key}");
    }
}
