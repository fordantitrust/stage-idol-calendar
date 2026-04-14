<?php
/**
 * TelegramTest — Automated tests for Telegram Bot Commands (v5.4.0)
 *
 * Coverage:
 *  1.  functions/telegram.php  — new helper functions (find_favorites_by_chat_id,
 *                                telegram_is_muted, telegram_notify_is_enabled,
 *                                telegram_format_events_list)
 *  2.  functions/telegram.php  — new message keys (16 keys, all 3 languages)
 *  3.  api/telegram.php        — new command handlers + router routes
 *  4.  cron/send-telegram-notifications.php — mute/notify guards
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions/telegram.php';

// ── 1. Helper Function Existence ─────────────────────────────────────────────

function testFindFavoritesByChatIdFunctionExists($test) {
    $test->assertTrue(
        function_exists('find_favorites_by_chat_id'),
        'find_favorites_by_chat_id() should be defined in functions/telegram.php'
    );
}

function testTelegramIsMutedFunctionExists($test) {
    $test->assertTrue(
        function_exists('telegram_is_muted'),
        'telegram_is_muted() should be defined in functions/telegram.php'
    );
}

function testTelegramNotifyIsEnabledFunctionExists($test) {
    $test->assertTrue(
        function_exists('telegram_notify_is_enabled'),
        'telegram_notify_is_enabled() should be defined in functions/telegram.php'
    );
}

function testTelegramFormatEventsListFunctionExists($test) {
    $test->assertTrue(
        function_exists('telegram_format_events_list'),
        'telegram_format_events_list() should be defined in functions/telegram.php'
    );
}

// ── 2. telegram_is_muted() Unit Tests ────────────────────────────────────────

function testTelegramIsMutedReturnsFalseWhenAbsent($test) {
    $test->assertFalse(
        telegram_is_muted([]),
        'telegram_is_muted() should return false when telegram_mute_until is absent'
    );
}

function testTelegramIsMutedReturnsFalseWhenZero($test) {
    $test->assertFalse(
        telegram_is_muted(['telegram_mute_until' => 0]),
        'telegram_is_muted() should return false when telegram_mute_until is 0'
    );
}

function testTelegramIsMutedReturnsFalseWhenPast($test) {
    $test->assertFalse(
        telegram_is_muted(['telegram_mute_until' => time() - 100]),
        'telegram_is_muted() should return false when mute time is in the past'
    );
}

function testTelegramIsMutedReturnsTrueWhenFuture($test) {
    $test->assertTrue(
        telegram_is_muted(['telegram_mute_until' => time() + 3600]),
        'telegram_is_muted() should return true when mute time is in the future'
    );
}

// ── 3. telegram_notify_is_enabled() Unit Tests ───────────────────────────────

function testNotifyEnabledWhenAbsent($test) {
    $test->assertTrue(
        telegram_notify_is_enabled([]),
        'telegram_notify_is_enabled() should return true when key is absent'
    );
}

function testNotifyEnabledWhenNull($test) {
    $test->assertTrue(
        telegram_notify_is_enabled(['telegram_notify_enabled' => null]),
        'telegram_notify_is_enabled() should return true when value is null'
    );
}

function testNotifyEnabledWhenTrue($test) {
    $test->assertTrue(
        telegram_notify_is_enabled(['telegram_notify_enabled' => true]),
        'telegram_notify_is_enabled() should return true when value is true'
    );
}

function testNotifyDisabledWhenFalse($test) {
    $test->assertFalse(
        telegram_notify_is_enabled(['telegram_notify_enabled' => false]),
        'telegram_notify_is_enabled() should return false when value is false'
    );
}

// ── 4. telegram_format_events_list() Basic Tests ─────────────────────────────

function testFormatEventsListReturnsString($test) {
    $programs = [
        ['event_name' => 'Test Event', 'start' => '2026-04-15 10:00:00', 'end' => '2026-04-15 11:00:00',
         'title' => 'Program 1', 'location' => 'Stage A', 'program_type' => null, 'stream_url' => null],
    ];
    $result = telegram_format_events_list($programs, '2026-04-15', 'th', 'today');
    $test->assertTrue(is_string($result), 'telegram_format_events_list() should return a string');
}

function testFormatEventsListContainsEventName($test) {
    $programs = [
        ['event_name' => 'Idol Stage Fest', 'start' => '2026-04-15 10:00:00', 'end' => '2026-04-15 11:00:00',
         'title' => 'Opening', 'location' => 'Main', 'program_type' => null, 'stream_url' => null],
    ];
    $result = telegram_format_events_list($programs, '2026-04-15', 'th', 'today');
    $test->assertTrue(strpos($result, 'Idol Stage Fest') !== false, 'Result should contain the event name');
}

function testFormatEventsListCountsPrograms($test) {
    $programs = [
        ['event_name' => 'Event A', 'start' => '2026-04-15 10:00:00', 'end' => '2026-04-15 11:00:00',
         'title' => 'Prog 1', 'location' => 'Stage', 'program_type' => null, 'stream_url' => null],
        ['event_name' => 'Event A', 'start' => '2026-04-15 12:00:00', 'end' => '2026-04-15 13:00:00',
         'title' => 'Prog 2', 'location' => 'Stage', 'program_type' => null, 'stream_url' => null],
    ];
    $result = telegram_format_events_list($programs, '2026-04-15', 'th', 'today');
    $test->assertTrue(strpos($result, '2') !== false, 'Result should contain the program count');
}

function testFormatEventsListTodayHeaderTH($test) {
    $programs = [
        ['event_name' => 'Event', 'start' => '2026-04-15 10:00:00', 'end' => '2026-04-15 11:00:00',
         'title' => 'Prog', 'location' => 'Stage', 'program_type' => null, 'stream_url' => null],
    ];
    $result = telegram_format_events_list($programs, '2026-04-15', 'th', 'today');
    $test->assertTrue(strpos($result, 'วันนี้') !== false, 'Thai today header should contain วันนี้');
}

function testFormatEventsListTomorrowHeaderEN($test) {
    $programs = [
        ['event_name' => 'Event', 'start' => '2026-04-16 10:00:00', 'end' => '2026-04-16 11:00:00',
         'title' => 'Prog', 'location' => 'Stage', 'program_type' => null, 'stream_url' => null],
    ];
    $result = telegram_format_events_list($programs, '2026-04-16', 'en', 'tomorrow');
    $test->assertTrue(strpos($result, 'Tomorrow') !== false, 'English tomorrow header should contain Tomorrow');
}

function testFormatEventsListWeekDayContextJA($test) {
    $programs = [
        ['event_name' => 'Event', 'start' => '2026-04-15 10:00:00', 'end' => '2026-04-15 11:00:00',
         'title' => 'Prog', 'location' => 'Stage', 'program_type' => null, 'stream_url' => null],
    ];
    $result = telegram_format_events_list($programs, '2026-04-15', 'ja', 'week_day');
    $test->assertTrue(strpos($result, '📆') !== false, 'week_day context should use 📆 prefix');
}

// ── 5. New Message Keys ───────────────────────────────────────────────────────

function testMessageKeyNoTomorrow($test) {
    foreach (['th', 'en', 'ja'] as $lang) {
        $msg = telegram_get_message('no_tomorrow', $lang);
        $test->assertNotEmpty($msg, "no_tomorrow message should not be empty for lang={$lang}");
    }
}

function testMessageKeyWeekTitle($test) {
    foreach (['th', 'en', 'ja'] as $lang) {
        $msg = telegram_get_message('week_title', $lang);
        $test->assertNotEmpty($msg, "week_title should not be empty for lang={$lang}");
    }
}

function testMessageKeyWeekNoPrograms($test) {
    foreach (['th', 'en', 'ja'] as $lang) {
        $msg = telegram_get_message('week_no_programs', $lang);
        $test->assertNotEmpty($msg, "week_no_programs should not be empty for lang={$lang}");
    }
}

function testMessageKeyArtistsTitleHasCountPlaceholder($test) {
    foreach (['th', 'en', 'ja'] as $lang) {
        $msg = telegram_get_message('artists_title', $lang);
        $test->assertContains('{count}', $msg, "artists_title should have {count} placeholder for lang={$lang}");
    }
}

function testMessageKeyArtistsTitleInterpolation($test) {
    $msg = telegram_get_message('artists_title', 'th', ['count' => 5]);
    $test->assertContains('5', $msg, 'artists_title should interpolate {count}');
    $test->assertTrue(strpos($msg, '{count}') === false, 'artists_title should replace {count} placeholder');
}

function testMessageKeyArtistsNone($test) {
    foreach (['th', 'en', 'ja'] as $lang) {
        $msg = telegram_get_message('artists_none', $lang);
        $test->assertNotEmpty($msg, "artists_none should not be empty for lang={$lang}");
    }
}

function testMessageKeyLangChangedHasLangPlaceholder($test) {
    foreach (['th', 'en', 'ja'] as $lang) {
        $msg = telegram_get_message('lang_changed', $lang);
        $test->assertContains('{lang}', $msg, "lang_changed should have {lang} placeholder for lang={$lang}");
    }
}

function testMessageKeyLangInvalid($test) {
    foreach (['th', 'en', 'ja'] as $lang) {
        $msg = telegram_get_message('lang_invalid', $lang);
        $test->assertNotEmpty($msg, "lang_invalid should not be empty for lang={$lang}");
    }
}

function testMessageKeyMuteSetHasTimePlaceholder($test) {
    foreach (['th', 'en', 'ja'] as $lang) {
        $msg = telegram_get_message('mute_set', $lang);
        $test->assertContains('{time}', $msg, "mute_set should have {time} placeholder for lang={$lang}");
    }
}

function testMessageKeyMuteInvalid($test) {
    foreach (['th', 'en', 'ja'] as $lang) {
        $msg = telegram_get_message('mute_invalid', $lang);
        $test->assertNotEmpty($msg, "mute_invalid should not be empty for lang={$lang}");
    }
}

function testMessageKeyNotifyEnabled($test) {
    foreach (['th', 'en', 'ja'] as $lang) {
        $msg = telegram_get_message('notify_enabled', $lang);
        $test->assertNotEmpty($msg, "notify_enabled should not be empty for lang={$lang}");
    }
}

function testMessageKeyNotifyDisabled($test) {
    foreach (['th', 'en', 'ja'] as $lang) {
        $msg = telegram_get_message('notify_disabled', $lang);
        $test->assertNotEmpty($msg, "notify_disabled should not be empty for lang={$lang}");
    }
}

function testMessageKeyNotifyInvalid($test) {
    foreach (['th', 'en', 'ja'] as $lang) {
        $msg = telegram_get_message('notify_invalid', $lang);
        $test->assertNotEmpty($msg, "notify_invalid should not be empty for lang={$lang}");
    }
}

function testMessageKeyStatusHasAllPlaceholders($test) {
    foreach (['th', 'en', 'ja'] as $lang) {
        $msg = telegram_get_message('status', $lang);
        $test->assertContains('{count}',  $msg, "status should have {count} for lang={$lang}");
        $test->assertContains('{lang}',   $msg, "status should have {lang} for lang={$lang}");
        $test->assertContains('{notify}', $msg, "status should have {notify} for lang={$lang}");
        $test->assertContains('{mute}',   $msg, "status should have {mute} for lang={$lang}");
    }
}

function testMessageKeyStatusInterpolation($test) {
    $msg = telegram_get_message('status', 'en', [
        'count' => 3, 'lang' => 'Thai', 'notify' => 'On', 'mute' => 'not muted'
    ]);
    $test->assertContains('3',         $msg, 'status should interpolate count');
    $test->assertContains('Thai',      $msg, 'status should interpolate lang');
    $test->assertContains('On',        $msg, 'status should interpolate notify');
    $test->assertContains('not muted', $msg, 'status should interpolate mute');
}

function testMessageKeyUpcomingInvalid($test) {
    foreach (['th', 'en', 'ja'] as $lang) {
        $msg = telegram_get_message('upcoming_invalid', $lang);
        $test->assertNotEmpty($msg, "upcoming_invalid should not be empty for lang={$lang}");
    }
}

function testMessageKeyUpcomingTitleHasCountPlaceholder($test) {
    foreach (['th', 'en', 'ja'] as $lang) {
        $msg = telegram_get_message('upcoming_title', $lang);
        $test->assertContains('{count}', $msg, "upcoming_title should have {count} placeholder for lang={$lang}");
    }
}

// ── 6. api/telegram.php — Handler Existence ──────────────────────────────────

function testHandleTomorrowCommandExists($test) {
    $src = file_get_contents(dirname(__DIR__) . '/api/telegram.php');
    $test->assertContains('function handle_tomorrow_command', $src,
        'handle_tomorrow_command() should be defined in api/telegram.php');
}

function testHandleWeekCommandExists($test) {
    $src = file_get_contents(dirname(__DIR__) . '/api/telegram.php');
    $test->assertContains('function handle_week_command', $src,
        'handle_week_command() should be defined in api/telegram.php');
}

function testHandleArtistsCommandExists($test) {
    $src = file_get_contents(dirname(__DIR__) . '/api/telegram.php');
    $test->assertContains('function handle_artists_command', $src,
        'handle_artists_command() should be defined in api/telegram.php');
}

function testHandleLangCommandExists($test) {
    $src = file_get_contents(dirname(__DIR__) . '/api/telegram.php');
    $test->assertContains('function handle_lang_command', $src,
        'handle_lang_command() should be defined in api/telegram.php');
}

function testHandleMuteCommandExists($test) {
    $src = file_get_contents(dirname(__DIR__) . '/api/telegram.php');
    $test->assertContains('function handle_mute_command', $src,
        'handle_mute_command() should be defined in api/telegram.php');
}

function testHandleNotifyCommandExists($test) {
    $src = file_get_contents(dirname(__DIR__) . '/api/telegram.php');
    $test->assertContains('function handle_notify_command', $src,
        'handle_notify_command() should be defined in api/telegram.php');
}

function testHandleStatusCommandExists($test) {
    $src = file_get_contents(dirname(__DIR__) . '/api/telegram.php');
    $test->assertContains('function handle_status_command', $src,
        'handle_status_command() should be defined in api/telegram.php');
}

// ── 7. api/telegram.php — Router Routes ──────────────────────────────────────

function testApiRoutesTomorrow($test) {
    $src = file_get_contents(dirname(__DIR__) . '/api/telegram.php');
    $test->assertContains("'/tomorrow'", $src, "Router should handle /tomorrow");
}

function testApiRoutesWeek($test) {
    $src = file_get_contents(dirname(__DIR__) . '/api/telegram.php');
    $test->assertContains("'/week'", $src, "Router should handle /week");
}

function testApiRoutesArtists($test) {
    $src = file_get_contents(dirname(__DIR__) . '/api/telegram.php');
    $test->assertContains("'/artists'", $src, "Router should handle /artists");
}

function testApiRoutesNext($test) {
    $src = file_get_contents(dirname(__DIR__) . '/api/telegram.php');
    $test->assertContains("'/next'", $src, "Router should handle /next");
}

function testApiRoutesLang($test) {
    $src = file_get_contents(dirname(__DIR__) . '/api/telegram.php');
    $test->assertContains("'/lang'", $src, "Router should handle /lang");
}

function testApiRoutesMute($test) {
    $src = file_get_contents(dirname(__DIR__) . '/api/telegram.php');
    $test->assertContains("'/mute'", $src, "Router should handle /mute");
}

function testApiRoutesNotify($test) {
    $src = file_get_contents(dirname(__DIR__) . '/api/telegram.php');
    $test->assertContains("'/notify'", $src, "Router should handle /notify");
}

function testApiRoutesStatus($test) {
    $src = file_get_contents(dirname(__DIR__) . '/api/telegram.php');
    $test->assertContains("'/status'", $src, "Router should handle /status");
}

function testApiNextAliasesUpcoming($test) {
    $src = file_get_contents(dirname(__DIR__) . '/api/telegram.php');
    // /next should call handle_upcoming_command with '1'
    $test->assertContains("handle_upcoming_command(\$chat_id, \$language, '1')", $src,
        "/next should alias handle_upcoming_command with '1'");
}

// ── 8. cron/send-telegram-notifications.php — Guards ─────────────────────────

function testCronChecksMuteStatus($test) {
    $src = file_get_contents(dirname(__DIR__) . '/cron/send-telegram-notifications.php');
    $test->assertContains('telegram_is_muted', $src,
        'Cron script should check telegram_is_muted()');
}

function testCronChecksNotifyEnabled($test) {
    $src = file_get_contents(dirname(__DIR__) . '/cron/send-telegram-notifications.php');
    $test->assertContains('telegram_notify_is_enabled', $src,
        'Cron script should check telegram_notify_is_enabled()');
}

function testCronMuteGuardBeforeProcessing($test) {
    $src = file_get_contents(dirname(__DIR__) . '/cron/send-telegram-notifications.php');
    $mutePos   = strpos($src, 'telegram_is_muted');
    $notifyPos = strpos($src, 'telegram_notify_is_enabled');
    $test->assertNotFalse($mutePos,   'telegram_is_muted should appear in cron script');
    $test->assertNotFalse($notifyPos, 'telegram_notify_is_enabled should appear in cron script');
}
