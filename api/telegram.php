<?php
/**
 * Telegram Bot Webhook Handler
 *
 * Receives updates from Telegram servers and processes user commands
 * URL: /api/telegram or /api/telegram.php
 */

require_once __DIR__ . '/../config.php';

// Always return 200 to acknowledge receipt (Telegram requirement)
http_response_code(200);
header('Content-Type: application/json');

// Verify request is from Telegram
function verify_telegram_request() {
    if (empty(TELEGRAM_WEBHOOK_SECRET)) {
        return false;
    }

    $secret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;
    $expected = TELEGRAM_WEBHOOK_SECRET;

    if (!$secret) {
        return false;
    }

    if (!hash_equals($secret, $expected)) {
        return false;
    }

    return true;
}

// Get incoming update from Telegram
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (!is_array($update)) {
    exit(json_encode(['ok' => false]));
}


// Verify the request
if (!verify_telegram_request()) {
    exit(json_encode(['ok' => false]));
}


// Handle commands and callback queries FIRST
if (!empty($update['callback_query'])) {
    // Handle button clicks
    handle_callback_query($update['callback_query']);
} else {
    // Extract message and chat data (for command messages only)
    $message = $update['message'] ?? null;
    if (!$message || !isset($message['text'], $message['from']['id'])) {
        exit(json_encode(['ok' => false]));
    }

    $text = trim($message['text']);
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'];

    // Parse command
    $parts = explode(' ', $text, 2);
    $command = strtolower($parts[0]);
    $payload = $parts[1] ?? '';


    // Get language preference: start from Telegram's UI language as fallback
    $language = telegram_normalize_language($message['from']['language_code'] ?? 'th');

    // Override with user's saved language preference (if account is linked).
    // This ensures error messages, /stop, and unknown commands all respond
    // in the user's chosen language rather than their Telegram UI language.
    // Skip pre-lookup for /start since the user may not be linked yet.
    if ($command !== '/start') {
        $preFound = find_favorites_by_chat_id((int)$chat_id);
        if ($preFound && !empty($preFound['data']['telegram_language'])) {
            $language = $preFound['data']['telegram_language'];
        }
    } else {
        $preFound = null;
    }

    // Handle message commands
    if ($command === '/start') {
        handle_start_command($chat_id, $payload, $language);
    } elseif ($command === '/stop') {
        handle_stop_command($chat_id, $language);
    } elseif ($command === '/today') {
        handle_today_command($chat_id, $language);
    } elseif ($command === '/tomorrow') {
        handle_tomorrow_command($chat_id, $language);
    } elseif ($command === '/week') {
        handle_week_command($chat_id, $language);
    } elseif ($command === '/upcoming') {
        handle_upcoming_command($chat_id, $language, $payload);
    } elseif ($command === '/next') {
        handle_upcoming_command($chat_id, $language, '1');
    } elseif ($command === '/artists') {
        handle_artists_command($chat_id, $language);
    } elseif ($command === '/lang') {
        handle_lang_command($chat_id, $payload, $language);
    } elseif ($command === '/mute') {
        handle_mute_command($chat_id, $payload, $language);
    } elseif ($command === '/notify') {
        handle_notify_command($chat_id, $payload, $language);
    } elseif ($command === '/status') {
        handle_status_command($chat_id, $language);
    } else {
        // Unknown command, send help
        send_help_message($chat_id, $language);
    }
}

exit(json_encode(['ok' => true]));

// ============================================================================
// COMMAND HANDLERS
// ============================================================================

/**
 * Handle callback query (button clicks)
 *
 * Callback data format: link_th:slug, link_en:slug, link_ja:slug
 *
 * @param array $callbackQuery Callback query data from Telegram
 */
function handle_callback_query($callbackQuery) {
    $callback_id = $callbackQuery['id'];
    $chat_id = $callbackQuery['from']['id'];
    $message_id = $callbackQuery['message']['message_id'] ?? null;
    $data = $callbackQuery['data'] ?? '';


    // Parse callback data: format is "link_lang:slug"
    // Slug format: UUID v7 (36 chars) + dash + HMAC (12 chars) = 49 chars, or shorter variants
    if (!preg_match('/^link_(th|en|ja):([0-9a-f\-]{30,50})$/', $data, $matches)) {
        $answerResult = telegram_api_call('answerCallbackQuery', [
            'callback_query_id' => $callback_id,
            'text' => '❌ Invalid data',
            'show_alert' => false
        ]);
        return;
    }

    $language = $matches[1];
    $slug = $matches[2];


    // Link account with selected language
    $result = telegram_link_account($slug, $chat_id, $language);

    // Answer callback query (remove loading state from button)
    if ($result['success']) {
        $answerResult = telegram_api_call('answerCallbackQuery', [
            'callback_query_id' => $callback_id,
            'text' => '✅ ' . telegram_get_message('linked', $language),
            'show_alert' => false
        ]);

        // Edit message to show success
        if ($message_id) {
            $text = telegram_get_message('linked', $language);
            $editResult = telegram_api_call('editMessageText', [
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => $text
            ]);
        }
    } else {
        $answerResult = telegram_api_call('answerCallbackQuery', [
            'callback_query_id' => $callback_id,
            'text' => '❌ ' . $result['message'],
            'show_alert' => true
        ]);
    }
}

/**
 * Handle /start command
 *
 * /start {slug} — Link account
 *
 * @param int $chat_id Telegram chat ID
 * @param string $payload Slug to link
 * @param string $language Language code (th, en, ja)
 */
function handle_start_command($chat_id, $payload, $language = 'th') {
    if (empty($payload)) {
        // No payload, show welcome message
        $text = telegram_get_message('welcome', $language);
        telegram_send_message($chat_id, $text);
        return;
    }

    // Validate slug format first
    if (!preg_match('/^[0-9a-f\-]{49}$/', $payload)) {
        $text = telegram_get_message('link_error', $language, ['error' => 'Invalid slug']);
        telegram_send_message($chat_id, $text);
        return;
    }

    // Show language selection keyboard
    // Store slug temporarily in callback_data: "link_th:{slug}", "link_en:{slug}", "link_ja:{slug}"
    $markup = [
        'inline_keyboard' => [
            [
                ['text' => '🇹🇭 ไทย', 'callback_data' => 'link_th:' . $payload],
                ['text' => '🇬🇧 English', 'callback_data' => 'link_en:' . $payload],
                ['text' => '🇯🇵 日本語', 'callback_data' => 'link_ja:' . $payload],
            ]
        ]
    ];

    $text = telegram_get_message('select_language', $language);
    telegram_send_message($chat_id, $text, ['reply_markup' => json_encode($markup)]);
}

/**
 * Handle /stop command
 *
 * Unlink the account
 *
 * @param int $chat_id Telegram chat ID
 * @param string $language Language code (th, en, ja)
 */
function handle_stop_command($chat_id, $language = 'th') {
    // We don't have the slug here, so we can't unlink directly
    // Instead, show instructions to unlink from the web interface
    $text = telegram_get_message('stop', $language);
    telegram_send_message($chat_id, $text);
}

/**
 * Handle /upcoming command
 *
 * Show next N upcoming programs for this chat_id (default 3, max 10).
 *
 * @param int    $chat_id  Telegram chat ID
 * @param string $language Language code (th, en, ja)
 * @param string $payload  Optional numeric argument (1–10)
 */
function handle_upcoming_command($chat_id, $language = 'th', $payload = '') {
    $found = find_favorites_by_chat_id((int)$chat_id);
    if ($found && !empty($found['data']['telegram_language'])) {
        $language = $found['data']['telegram_language'];
    }
    if (!$found || empty($found['data']['artists'])) {
        telegram_send_message($chat_id, telegram_get_message('no_account', $language));
        return;
    }

    // Parse optional count argument
    $limit = 3;
    $payload = trim($payload);
    if ($payload !== '') {
        if (!ctype_digit($payload) || (int)$payload < 1 || (int)$payload > 10) {
            telegram_send_message($chat_id, telegram_get_message('upcoming_invalid', $language));
            // Continue with default limit
        } else {
            $limit = (int)$payload;
        }
    }

    $favData = $found['data'];
    $allArtistIds = _telegram_resolve_artists($favData['artists']);
    $placeholders = implode(',', array_fill(0, count($allArtistIds), '?'));

    $db = get_db();
    $stmt = $db->prepare("
        SELECT DISTINCT
            p.id, p.title, p.start, p.end, p.location,
            p.program_type, p.stream_url, p.event_id,
            e.name as event_name
        FROM programs p
        JOIN events e ON p.event_id = e.id
        JOIN program_artists pa ON p.id = pa.program_id
        WHERE pa.artist_id IN ($placeholders)
            AND e.is_active = 1
            AND p.start >= :now
        ORDER BY p.start ASC
        LIMIT :lim
    ");

    $stmt->execute(array_merge($allArtistIds, [':now' => date('Y-m-d H:i:s'), ':lim' => $limit]));
    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = null;

    if (empty($programs)) {
        telegram_send_message($chat_id, telegram_get_message('no_programs', $language));
        return;
    }

    $text = telegram_get_message('upcoming_title', $language, ['count' => count($programs)]) . "\n\n";
    foreach ($programs as $prog) {
        $text .= telegram_format_notification($prog) . "\n\n";
    }
    telegram_send_message($chat_id, $text);
}

/**
 * Handle /today command — show events today with program count per event.
 *
 * @param int    $chat_id  Telegram chat ID
 * @param string $language Language code (th, en, ja)
 */
function handle_today_command($chat_id, $language = 'th') {
    $found = find_favorites_by_chat_id((int)$chat_id);
    if ($found && !empty($found['data']['telegram_language'])) {
        $language = $found['data']['telegram_language'];
    }
    if (!$found || empty($found['data']['artists'])) {
        telegram_send_message($chat_id, telegram_get_message('no_account', $language));
        return;
    }
    $favData = $found['data'];
    $allArtistIds = _telegram_resolve_artists($favData['artists']);
    $today = date('Y-m-d');
    $programs = _telegram_query_programs_by_date($allArtistIds, $today);
    if (empty($programs)) {
        telegram_send_message($chat_id, telegram_get_message('no_today', $language));
        return;
    }
    telegram_send_message($chat_id, telegram_format_events_list($programs, $today, $language, 'today'));
}

/**
 * Handle /tomorrow command — show events tomorrow with program count per event.
 *
 * @param int    $chat_id  Telegram chat ID
 * @param string $language Language code (th, en, ja)
 */
function handle_tomorrow_command($chat_id, $language = 'th') {
    $found = find_favorites_by_chat_id((int)$chat_id);
    if ($found && !empty($found['data']['telegram_language'])) {
        $language = $found['data']['telegram_language'];
    }
    if (!$found || empty($found['data']['artists'])) {
        telegram_send_message($chat_id, telegram_get_message('no_account', $language));
        return;
    }
    $favData = $found['data'];
    $allArtistIds = _telegram_resolve_artists($favData['artists']);
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $programs = _telegram_query_programs_by_date($allArtistIds, $tomorrow);
    if (empty($programs)) {
        telegram_send_message($chat_id, telegram_get_message('no_tomorrow', $language));
        return;
    }
    telegram_send_message($chat_id, telegram_format_events_list($programs, $tomorrow, $language, 'tomorrow'));
}

/**
 * Handle /week command — show events in the next 7 days grouped by day.
 *
 * @param int    $chat_id  Telegram chat ID
 * @param string $language Language code (th, en, ja)
 */
function handle_week_command($chat_id, $language = 'th') {
    $found = find_favorites_by_chat_id((int)$chat_id);
    if ($found && !empty($found['data']['telegram_language'])) {
        $language = $found['data']['telegram_language'];
    }
    if (!$found || empty($found['data']['artists'])) {
        telegram_send_message($chat_id, telegram_get_message('no_account', $language));
        return;
    }
    $favData = $found['data'];
    $allArtistIds = _telegram_resolve_artists($favData['artists']);

    $today   = date('Y-m-d');
    $endDate = date('Y-m-d', strtotime('+7 days'));
    $placeholders = implode(',', array_fill(0, count($allArtistIds), '?'));

    try {
        $db = get_db();
        $stmt = $db->prepare("
            SELECT DISTINCT
                p.id, p.title, p.start, p.end, p.location, p.program_type, p.stream_url,
                e.name as event_name
            FROM programs p
            JOIN events e ON p.event_id = e.id
            JOIN program_artists pa ON p.id = pa.program_id
            WHERE pa.artist_id IN ($placeholders)
                AND e.is_active = 1
                AND DATE(p.start) >= :today
                AND DATE(p.start) < :end_date
            ORDER BY p.start ASC
        ");
        $stmt->execute(array_merge($allArtistIds, [':today' => $today, ':end_date' => $endDate]));
        $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = null;
    } catch (Exception $e) {
        $programs = [];
    }

    if (empty($programs)) {
        telegram_send_message($chat_id, telegram_get_message('week_no_programs', $language));
        return;
    }

    // Group by date
    $byDay = [];
    foreach ($programs as $prog) {
        $d = substr($prog['start'], 0, 10);
        $byDay[$d][] = $prog;
    }

    $text = telegram_get_message('week_title', $language) . "\n\n";
    foreach ($byDay as $dateStr => $dayPrograms) {
        $text .= telegram_format_events_list($dayPrograms, $dateStr, $language, 'week_day') . "\n";
    }
    telegram_send_message($chat_id, rtrim($text));
}

/**
 * Handle /artists command — list followed artist names.
 *
 * @param int    $chat_id  Telegram chat ID
 * @param string $language Language code (th, en, ja)
 */
function handle_artists_command($chat_id, $language = 'th') {
    $found = find_favorites_by_chat_id((int)$chat_id);
    if ($found && !empty($found['data']['telegram_language'])) {
        $language = $found['data']['telegram_language'];
    }
    if (!$found) {
        telegram_send_message($chat_id, telegram_get_message('no_account', $language));
        return;
    }
    $artistIds = $found['data']['artists'] ?? [];
    if (empty($artistIds)) {
        telegram_send_message($chat_id, telegram_get_message('artists_none', $language));
        return;
    }
    $placeholders = implode(',', array_fill(0, count($artistIds), '?'));
    try {
        $db = get_db();
        $stmt = $db->prepare("SELECT name FROM artists WHERE id IN ($placeholders) ORDER BY name ASC");
        $stmt->execute($artistIds);
        $names = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        $stmt = null;
    } catch (Exception $e) {
        $names = [];
    }
    $count = count($names);
    $text = telegram_get_message('artists_title', $language, ['count' => $count]) . "\n\n";
    foreach ($names as $name) {
        $text .= "• " . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "\n";
    }
    telegram_send_message($chat_id, rtrim($text));
}

/**
 * Handle /lang command — change notification language.
 *
 * @param int    $chat_id  Telegram chat ID
 * @param string $payload  Language code (th|en|ja)
 * @param string $language Current language for error messages
 */
function handle_lang_command($chat_id, $payload, $language = 'th') {
    $newLang = strtolower(trim($payload));
    if (!in_array($newLang, ['th', 'en', 'ja'], true)) {
        telegram_send_message($chat_id, telegram_get_message('lang_invalid', $language));
        return;
    }
    $found = find_favorites_by_chat_id((int)$chat_id);
    if (!$found) {
        telegram_send_message($chat_id, telegram_get_message('no_account', $language));
        return;
    }
    $favData = $found['data'];
    $favData['telegram_language'] = $newLang;
    file_put_contents($found['path'], json_encode($favData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    $langLabels = ['th' => 'ไทย', 'en' => 'English', 'ja' => '日本語'];
    $label = $langLabels[$newLang] ?? $newLang;
    telegram_send_message($chat_id, telegram_get_message('lang_changed', $newLang, ['lang' => $label]));
}

/**
 * Handle /mute command — mute notifications for N hours.
 *
 * @param int    $chat_id  Telegram chat ID
 * @param string $payload  Number of hours (1–72)
 * @param string $language Language code (th, en, ja)
 */
function handle_mute_command($chat_id, $payload, $language = 'th') {
    $found = find_favorites_by_chat_id((int)$chat_id);
    if ($found && !empty($found['data']['telegram_language'])) {
        $language = $found['data']['telegram_language'];
    }
    if (!$found) {
        telegram_send_message($chat_id, telegram_get_message('no_account', $language));
        return;
    }
    $hours = trim($payload);
    if (!ctype_digit($hours) || (int)$hours < 1 || (int)$hours > 72) {
        telegram_send_message($chat_id, telegram_get_message('mute_invalid', $language));
        return;
    }
    $hours = (int)$hours;
    $muteUntil = time() + $hours * 3600;
    $favData = $found['data'];
    $favData['telegram_mute_until'] = $muteUntil;
    file_put_contents($found['path'], json_encode($favData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    $dt = new DateTime('@' . $muteUntil);
    $dt->setTimezone(new DateTimeZone('Asia/Bangkok'));
    $timeStr = $dt->format('H:i');
    telegram_send_message($chat_id, telegram_get_message('mute_set', $language, ['time' => $timeStr]));
}

/**
 * Handle /notify command — enable or disable push notifications.
 *
 * @param int    $chat_id  Telegram chat ID
 * @param string $payload  on|off
 * @param string $language Language code (th, en, ja)
 */
function handle_notify_command($chat_id, $payload, $language = 'th') {
    $found = find_favorites_by_chat_id((int)$chat_id);
    if ($found && !empty($found['data']['telegram_language'])) {
        $language = $found['data']['telegram_language'];
    }
    if (!$found) {
        telegram_send_message($chat_id, telegram_get_message('no_account', $language));
        return;
    }
    $arg = strtolower(trim($payload));
    if ($arg !== 'on' && $arg !== 'off') {
        telegram_send_message($chat_id, telegram_get_message('notify_invalid', $language));
        return;
    }
    $enabled = ($arg === 'on');
    $favData = $found['data'];
    $favData['telegram_notify_enabled'] = $enabled;
    file_put_contents($found['path'], json_encode($favData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    $key = $enabled ? 'notify_enabled' : 'notify_disabled';
    telegram_send_message($chat_id, telegram_get_message($key, $language));
}

/**
 * Handle /status command — show account status summary.
 *
 * @param int    $chat_id  Telegram chat ID
 * @param string $language Language code (th, en, ja)
 */
function handle_status_command($chat_id, $language = 'th') {
    $found = find_favorites_by_chat_id((int)$chat_id);
    if ($found && !empty($found['data']['telegram_language'])) {
        $language = $found['data']['telegram_language'];
    }
    if (!$found) {
        telegram_send_message($chat_id, telegram_get_message('no_account', $language));
        return;
    }
    $favData = $found['data'];
    $count   = count($favData['artists'] ?? []);
    $lang    = $favData['telegram_language'] ?? 'th';

    // Localized language label
    $langMap = [
        'th' => ['th' => 'ไทย',    'en' => 'Thai',     'ja' => 'タイ語'],
        'en' => ['th' => 'อังกฤษ', 'en' => 'English',  'ja' => '英語'],
        'ja' => ['th' => 'ญี่ปุ่น','en' => 'Japanese', 'ja' => '日本語'],
    ];
    $langLabel = $langMap[$lang][$language] ?? strtoupper($lang);

    // Notify label
    $notifyOn  = telegram_notify_is_enabled($favData);
    $notifyMap = ['th' => ['เปิด', 'ปิด'], 'en' => ['On', 'Off'], 'ja' => ['オン', 'オフ']];
    $notifyArr = $notifyMap[$language] ?? $notifyMap['en'];
    $notifyLabel = $notifyOn ? $notifyArr[0] : $notifyArr[1];

    // Mute label
    if (telegram_is_muted($favData)) {
        $dt = new DateTime('@' . (int)$favData['telegram_mute_until']);
        $dt->setTimezone(new DateTimeZone('Asia/Bangkok'));
        $t = $dt->format('H:i');
        $muteMap = ['th' => "จนถึง {$t} น.", 'en' => "until {$t}", 'ja' => "{$t}まで"];
        $muteLabel = $muteMap[$language] ?? "until {$t}";
    } else {
        $noMuteMap = ['th' => 'ไม่ได้ mute', 'en' => 'not muted', 'ja' => 'ミュートなし'];
        $muteLabel = $noMuteMap[$language] ?? 'not muted';
    }

    telegram_send_message($chat_id, telegram_get_message('status', $language, [
        'count'  => $count,
        'lang'   => $langLabel,
        'notify' => $notifyLabel,
        'mute'   => $muteLabel,
    ]));
}

// ============================================================================
// INTERNAL HELPERS (prefix _ to signal non-public use)
// ============================================================================

/**
 * Resolve artist IDs including group members.
 *
 * @param array $artistIds Base artist IDs from favorites
 * @return array Unique array of artist IDs (including group members)
 */
function _telegram_resolve_artists(array $artistIds): array {
    if (empty($artistIds)) return $artistIds;
    try {
        $db = get_db();
        $placeholders = implode(',', array_fill(0, count($artistIds), '?'));
        $stmt = $db->prepare("
            SELECT DISTINCT a.id FROM artists a
            WHERE a.group_id IN (
                SELECT DISTINCT group_id FROM artists WHERE id IN ($placeholders) AND group_id IS NOT NULL
            ) AND a.is_group = 0
        ");
        $stmt->execute($artistIds);
        $groupMembers = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        $stmt = null;
        return array_unique(array_merge($artistIds, $groupMembers));
    } catch (Exception $e) {
        return $artistIds;
    }
}

/**
 * Query programs for a specific date, for given artist IDs.
 *
 * @param array  $artistIds Artist IDs to filter by
 * @param string $dateStr   Date string Y-m-d
 * @return array Program rows with event_name
 */
function _telegram_query_programs_by_date(array $artistIds, string $dateStr): array {
    if (empty($artistIds)) return [];
    $placeholders = implode(',', array_fill(0, count($artistIds), '?'));
    try {
        $db = get_db();
        $stmt = $db->prepare("
            SELECT DISTINCT
                p.id, p.title, p.start, p.end, p.location, p.program_type, p.stream_url,
                e.name as event_name
            FROM programs p
            JOIN events e ON p.event_id = e.id
            JOIN program_artists pa ON p.id = pa.program_id
            WHERE pa.artist_id IN ($placeholders)
                AND e.is_active = 1
                AND DATE(p.start) = :date
            ORDER BY e.name, p.start ASC
        ");
        $stmt->execute(array_merge($artistIds, [':date' => $dateStr]));
        $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = null;
        return $programs;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Send help message
 *
 * @param int $chat_id Telegram chat ID
 * @param string $language Language code (th, en, ja)
 */
function send_help_message($chat_id, $language = 'th') {
    $text = telegram_get_message('help', $language);
    telegram_send_message($chat_id, $text);
}
