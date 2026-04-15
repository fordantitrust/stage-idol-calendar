<?php
/**
 * Telegram Bot Helper Functions
 *
 * Functions for sending notifications, managing user accounts, and webhook handling
 */

/**
 * Check if Telegram bot is enabled and configured
 *
 * @return bool
 */
function telegram_is_enabled() {
    return TELEGRAM_ENABLED && !empty(TELEGRAM_BOT_TOKEN) && !empty(TELEGRAM_BOT_USERNAME);
}

/**
 * Make a call to Telegram Bot API
 *
 * @param string $method API method name (e.g., 'sendMessage', 'setWebhook')
 * @param array $params Request parameters
 * @return array API response decoded to array, or empty array on failure
 */
function telegram_api_call($method, $params = []) {
    if (!telegram_is_enabled()) {
        return [];
    }

    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/" . $method;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("Telegram API error ($method): " . $error);
        return [];
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        error_log("Telegram API invalid response ($method): " . $response);
        return [];
    }

    if (!($data['ok'] ?? false)) {
        error_log("Telegram API error ($method): " . ($data['description'] ?? 'Unknown error'));
        return [];
    }

    return $data['result'] ?? [];
}

/**
 * Send a text message to a Telegram chat
 *
 * @param int|string $chat_id Telegram chat ID
 * @param string $text Message text (HTML formatting supported)
 * @param array $options Additional options (parse_mode, reply_markup, etc.)
 * @return bool True if message sent successfully
 */
function telegram_send_message($chat_id, $text, $options = []) {
    if (!telegram_is_enabled()) {
        return false;
    }

    $params = array_merge([
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ], $options);

    $result = telegram_api_call('sendMessage', $params);
    return !empty($result);
}

/**
 * Format a program as Telegram notification message
 *
 * @param array $program Program data (title, start, end, location, event_name, program_type, stream_url)
 * @return string Formatted HTML message
 */
function telegram_format_notification($program) {
    $title = htmlspecialchars($program['title'] ?? 'Program', ENT_QUOTES, 'UTF-8');
    $event = htmlspecialchars($program['event_name'] ?? '', ENT_QUOTES, 'UTF-8');
    $location = htmlspecialchars($program['location'] ?? 'TBA', ENT_QUOTES, 'UTF-8');
    $type = htmlspecialchars($program['program_type'] ?? '', ENT_QUOTES, 'UTF-8');

    // Parse datetime
    $start = new DateTime($program['start'] ?? 'now', new DateTimeZone('Asia/Bangkok'));
    $end = new DateTime($program['end'] ?? 'now', new DateTimeZone('Asia/Bangkok'));

    $timeStr = $start->format('H:i');
    if ($start->format('Y-m-d') !== $end->format('Y-m-d')) {
        // Cross-day program
        $timeStr .= ' – ' . $end->format('H:i (next day)');
    } else if ($start->format('H:i') !== $end->format('H:i')) {
        $timeStr .= ' – ' . $end->format('H:i');
    }

    $msg = "🎪 <b>$title</b>\n";
    if (!empty($event)) {
        $msg .= "📅 $event\n";
    }
    $msg .= "⏰ " . $timeStr . "\n";
    $msg .= "📍 " . $location;

    if (!empty($type)) {
        $msg .= " · 🏷️ " . htmlspecialchars($type, ENT_QUOTES, 'UTF-8');
    }
    $msg .= "\n";

    if (!empty($program['stream_url'])) {
        $url = htmlspecialchars($program['stream_url'], ENT_QUOTES, 'UTF-8');
        $msg .= "<a href=\"$url\">🔴 Watch Live</a>";
    }

    return $msg;
}

/**
 * Link a Telegram account to a favorites token
 *
 * Validates the slug HMAC, loads the favorites JSON file, and saves the telegram_chat_id
 *
 * @param string $slug Favorites slug (UUID-HMAC format)
 * @param int|string $chat_id Telegram chat ID
 * @param string $language Language code (th, en, ja) - auto-detected from Telegram if available
 * @return array ['success' => bool, 'message' => string]
 */
function telegram_link_account($slug, $chat_id, $language = 'th') {
    $result = ['success' => false, 'message' => 'Unknown error'];

    // Validate slug format
    $slug = trim($slug);
    if (!preg_match('/^[0-9a-f\-]{49}$/', $slug)) {
        $result['message'] = 'Invalid slug format';
        return $result;
    }

    // Normalize language to supported ones (th, en, ja)
    $language = strtolower(trim($language));
    if (!in_array($language, ['th', 'en', 'ja'])) {
        $language = 'th';  // Default to Thai
    }

    // Parse and validate slug
    $slugData = fav_parse_slug($slug);
    if (!$slugData) {
        error_log("Telegram ERROR: fav_parse_slug failed for slug=$slug");
        $result['message'] = 'Invalid or tampered slug';
        return $result;
    }
    $token = $slugData['token'];

    // Load favorites JSON file
    $shard = substr($token, -3);  // Use last 3 chars for shard (same as fav_file_path)
    $favFile = FAVORITES_DIR . '/' . $shard . '/' . $token . '.json';

    if (!file_exists($favFile)) {
        error_log("Telegram ERROR: Favorites file not found at $favFile");
        $result['message'] = 'Favorites not found';
        return $result;
    }

    // Read, update, and write back
    $fh = @fopen($favFile, 'r+');
    if (!$fh) {
        error_log("Telegram ERROR: Cannot open file $favFile");
        $result['message'] = 'Cannot update favorites';
        return $result;
    }

    if (!flock($fh, LOCK_EX)) {
        error_log("Telegram ERROR: Cannot lock file $favFile");
        fclose($fh);
        $result['message'] = 'Favorites locked, try again';
        return $result;
    }

    $content = stream_get_contents($fh);
    $favData = json_decode($content, true);

    if (!is_array($favData)) {
        error_log("Telegram ERROR: Invalid JSON in $favFile");
        flock($fh, LOCK_UN);
        fclose($fh);
        $result['message'] = 'Corrupt favorites data';
        return $result;
    }

    // Save Telegram chat ID and language preference
    $favData['telegram_chat_id'] = (int)$chat_id;
    $favData['telegram_language'] = $language;
    $favData['telegram_linked_at'] = date('c');

    // Write back
    rewind($fh);
    ftruncate($fh, 0);
    $jsonStr = json_encode($favData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $written = fwrite($fh, $jsonStr);
    if (!$written) {
        error_log("Telegram ERROR: Failed to write to $favFile");
    }
    flock($fh, LOCK_UN);
    fclose($fh);

    $result['success'] = true;
    $result['message'] = 'Linked successfully';
    return $result;
}

/**
 * Unlink a Telegram account from a favorites token
 *
 * @param string $slug Favorites slug
 * @return array ['success' => bool, 'message' => string]
 */
function telegram_unlink_account($slug) {
    $result = ['success' => false, 'message' => 'Unknown error'];

    $slug = trim($slug);
    if (!preg_match('/^[0-9a-f\-]{49}$/', $slug)) {
        $result['message'] = 'Invalid slug format';
        return $result;
    }

    $slugData = fav_parse_slug($slug);
    if (!$slugData) {
        $result['message'] = 'Invalid or tampered slug';
        return $result;
    }
    $token = $slugData['token'];

    $shard = substr($token, -3);  // Use last 3 chars for shard (same as fav_file_path)
    $favFile = FAVORITES_DIR . '/' . $shard . '/' . $token . '.json';

    if (!file_exists($favFile)) {
        $result['message'] = 'Favorites not found';
        return $result;
    }

    $fh = @fopen($favFile, 'r+');
    if (!$fh) {
        $result['message'] = 'Cannot update favorites';
        return $result;
    }

    if (!flock($fh, LOCK_EX)) {
        fclose($fh);
        $result['message'] = 'Favorites locked, try again';
        return $result;
    }

    $content = stream_get_contents($fh);
    $favData = json_decode($content, true);

    if (!is_array($favData)) {
        flock($fh, LOCK_UN);
        fclose($fh);
        $result['message'] = 'Corrupt favorites data';
        return $result;
    }

    // Clear Telegram data
    unset($favData['telegram_chat_id']);
    unset($favData['telegram_linked_at']);
    unset($favData['telegram_notified']);

    rewind($fh);
    ftruncate($fh, 0);
    fwrite($fh, json_encode($favData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    flock($fh, LOCK_UN);
    fclose($fh);

    $result['success'] = true;
    $result['message'] = 'Unlinked successfully';
    return $result;
}

/**
 * Get program with event information for notification
 *
 * @param int $program_id Program ID
 * @return array|null Program data with event name, or null if not found
 */
function telegram_get_program($program_id) {
    $db = get_db();

    $stmt = $db->prepare("
        SELECT
            p.id, p.title, p.start, p.end, p.location,
            p.program_type, p.stream_url, p.event_id,
            e.name as event_name, e.slug as event_slug
        FROM programs p
        JOIN events e ON p.event_id = e.id
        WHERE p.id = :id AND e.is_active = 1
    ");
    $stmt->execute([':id' => $program_id]);
    $program = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt = null;

    return $program ?: null;
}

/**
 * Check if a notification should be sent (duplicate prevention)
 *
 * @param array $telegram_notified Notified map from favorites JSON
 * @param int $program_id Program ID to check
 * @return bool True if we should send notification
 */
function telegram_should_notify($telegram_notified, $program_id) {
    if (empty($telegram_notified) || !is_array($telegram_notified)) {
        return true;
    }

    if (!isset($telegram_notified[$program_id])) {
        return true;
    }

    $lastNotified = (int)$telegram_notified[$program_id];
    $now = time();

    // Don't re-notify within the duplicate window (default 24 hours)
    return ($now - $lastNotified) > TELEGRAM_NOTIFY_DUPLICATE_WINDOW;
}

/**
 * Cleanup old notification entries from favorites JSON
 *
 * Removes notifications older than TELEGRAM_NOTIFY_HISTORY_DAYS
 *
 * @param array &$favData Favorites data array (passed by reference)
 * @return void
 */
function telegram_cleanup_old_notifications(&$favData) {
    if (empty($favData['telegram_notified']) || !is_array($favData['telegram_notified'])) {
        return;
    }

    $cutoffTime = time() - (TELEGRAM_NOTIFY_HISTORY_DAYS * 86400);
    $cleaned = [];

    foreach ($favData['telegram_notified'] as $programId => $timestamp) {
        if ($timestamp > $cutoffTime) {
            $cleaned[$programId] = $timestamp;
        }
    }

    $favData['telegram_notified'] = $cleaned;
}

/**
 * Format a daily summary message grouped by event
 *
 * @param array $programs Array of program data (must have event_name field)
 * @param string $dateStr Date string in YYYY-MM-DD format
 * @param string $language Language code (th, en, ja)
 * @return string Formatted HTML message
 */
function telegram_format_summary($programs, $dateStr, $language = 'th') {
    if (empty($programs)) {
        return telegram_get_message('no_programs', $language);
    }

    // Parse date for display
    $date = DateTime::createFromFormat('Y-m-d', $dateStr);
    $dayName = $date ? $date->format('l') : '';

    // Day name translations
    $dayMaps = [
        'th' => [
            'Monday' => 'จันทร์',
            'Tuesday' => 'อังคาร',
            'Wednesday' => 'พุธ',
            'Thursday' => 'พฤหัสบดี',
            'Friday' => 'ศุกร์',
            'Saturday' => 'เสาร์',
            'Sunday' => 'อาทิตย์'
        ],
        'en' => [
            'Monday' => 'Monday',
            'Tuesday' => 'Tuesday',
            'Wednesday' => 'Wednesday',
            'Thursday' => 'Thursday',
            'Friday' => 'Friday',
            'Saturday' => 'Saturday',
            'Sunday' => 'Sunday'
        ],
        'ja' => [
            'Monday' => '月曜日',
            'Tuesday' => '火曜日',
            'Wednesday' => '水曜日',
            'Thursday' => '木曜日',
            'Friday' => '金曜日',
            'Saturday' => '土曜日',
            'Sunday' => '日曜日'
        ]
    ];

    $dayMap = $dayMaps[$language] ?? $dayMaps['th'];
    $dayNameTrans = $dayMap[$dayName] ?? $dayName;

    // Date format
    $dateFormats = [
        'th' => $dateStr . " (" . $dayNameTrans . ")",
        'en' => $dateStr . " (" . $dayNameTrans . ")",
        'ja' => $dateStr . "（" . $dayNameTrans . "）"
    ];
    $dateFormatted = $dateFormats[$language] ?? $dateFormats['th'];

    // Headers
    $headers = [
        'th' => "📅 <b>โปรแกรมวันนี้</b>",
        'en' => "📅 <b>Today's Programs</b>",
        'ja' => "📅 <b>本日のプログラム</b>"
    ];
    $header = $headers[$language] ?? $headers['th'];

    // Separators
    $separators = [
        'th' => "━━━━━━━━━━━━━━",
        'en' => "━━━━━━━━━━━━━━",
        'ja' => "━━━━━━━━━━━━━━"
    ];
    $separator = $separators[$language] ?? $separators['th'];

    // Count labels
    $countLabels = [
        'th' => function($count) { return "<i>" . $count . " โปรแกรม</i>"; },
        'en' => function($count) { return "<i>" . $count . " program" . ($count !== 1 ? "s" : "") . "</i>"; },
        'ja' => function($count) { return "<i>" . $count . "個のプログラム</i>"; }
    ];
    $countLabel = $countLabels[$language] ?? $countLabels['th'];

    // Group programs by event
    $byEvent = [];
    foreach ($programs as $prog) {
        $eventName = $prog['event_name'] ?? 'Unknown Event';
        if (!isset($byEvent[$eventName])) {
            $byEvent[$eventName] = [];
        }
        $byEvent[$eventName][] = $prog;
    }

    $msg = $header . "\n";
    $msg .= $dateFormatted . "\n";
    $msg .= $separator . "\n\n";

    foreach ($byEvent as $eventName => $eventPrograms) {
        $eventNameEsc = htmlspecialchars($eventName, ENT_QUOTES, 'UTF-8');
        $count = count($eventPrograms);
        $msg .= "🎪 <b>" . $eventNameEsc . "</b>\n";
        $msg .= "   " . $countLabel($count) . "\n";
        $msg .= "\n";
    }

    return $msg;
}

/**
 * Check if daily summary should be sent (only once per day at 9 AM)
 *
 * @param array $favData Favorites data
 * @return bool True if summary should be sent
 */
function telegram_should_send_summary($favData) {
    // Get last summary send date
    $lastSummaryDate = $favData['telegram_summary_date'] ?? '';
    $today = date('Y-m-d');

    // Check if already sent today
    if ($lastSummaryDate === $today) {
        return false;
    }

    // Get Daily Summary time window from config
    $startHour = TELEGRAM_DAILY_SUMMARY_START_HOUR ?? 9;
    $startMinute = TELEGRAM_DAILY_SUMMARY_START_MINUTE ?? 0;
    $endHour = TELEGRAM_DAILY_SUMMARY_END_HOUR ?? 9;
    $endMinute = TELEGRAM_DAILY_SUMMARY_END_MINUTE ?? 30;

    // Check if current time is within the configured window
    $hour = (int)date('H');
    $minute = (int)date('i');
    $currentTimeInMinutes = $hour * 60 + $minute;
    $startTimeInMinutes = $startHour * 60 + $startMinute;
    $endTimeInMinutes = $endHour * 60 + $endMinute;

    if ($currentTimeInMinutes >= $startTimeInMinutes && $currentTimeInMinutes < $endTimeInMinutes) {
        return true;
    }

    return false;
}

/**
 * Get Telegram message in specified language
 *
 * @param string $key Message key (e.g., 'welcome', 'linked', 'no_account')
 * @param string $language Language code (th, en, ja)
 * @param array $params Optional parameters for substitution
 * @return string Formatted message text
 */
function telegram_get_message($key, $language = 'th', $params = []) {
    $messages = [
        'welcome' => [
            'th' => "👋 สวัสดี! {bot_name}\n\nฉันจะแจ้งเตือนให้คุณก่อนเริ่มโปรแกรมของศิลปินที่คุณติดตาม\n\nเพื่อเชื่อมต่อบัญชี กรุณา:\n1. เข้าไปที่ หน้า My Upcoming Programs\n2. กดปุ่ม 🔔 Link Telegram\n3. ทำตามคำแนะนำ\n\n📖 คำสั่ง:\n/today — events วันนี้ + จำนวน program\n/tomorrow — events พรุ่งนี้\n/week — 7 วันข้างหน้า\n/upcoming [N] — N โปรแกรมถัดไป (1–10, default 3)\n/next — โปรแกรมถัดไป 1 รายการ\n/artists — ศิลปินที่ติดตาม\n/lang th|en|ja — เปลี่ยนภาษา\n/mute N — ปิดเสียง N ชั่วโมง\n/notify on|off — เปิด/ปิดแจ้งเตือน\n/status — สถานะบัญชี\n/stop — ยกเลิกการเชื่อมต่อ",
            'en' => "👋 Hello! {bot_name}\n\nI will notify you before your favorite artists' programs start.\n\nTo link your account:\n1. Go to My Upcoming Programs page\n2. Click 🔔 Link Telegram button\n3. Follow the instructions\n\n📖 Commands:\n/today — today's events + program count\n/tomorrow — tomorrow's events\n/week — next 7 days\n/upcoming [N] — next N programs (1–10, default 3)\n/next — next 1 program\n/artists — followed artists\n/lang th|en|ja — change language\n/mute N — mute for N hours\n/notify on|off — enable/disable notifications\n/status — account status\n/stop — unlink account",
            'ja' => "👋 こんにちは! {bot_name}\n\nフォロー中のアーティストのプログラムが始まる前に通知します。\n\nアカウントをリンクするには:\n1. My Upcoming Programs ページに移動\n2. 🔔 Link Telegram ボタンをクリック\n3. 指示に従ってください\n\n📖 コマンド:\n/today — 今日のイベント + プログラム数\n/tomorrow — 明日のイベント\n/week — 今後7日間\n/upcoming [N] — 次のNプログラム (1–10, デフォルト3)\n/next — 次の1プログラム\n/artists — フォロー中アーティスト\n/lang th|en|ja — 言語変更\n/mute N — N時間ミュート\n/notify on|off — 通知のオン/オフ\n/status — アカウント状態\n/stop — リンク解除"
        ],
        'linked' => [
            'th' => "✅ เชื่อมต่อสำเร็จ!\n\nคุณจะได้รับการแจ้งเตือนก่อนเริ่มโปรแกรมของศิลปินที่คุณติดตาม\n\n📖 คำสั่ง:\n/today — events วันนี้\n/tomorrow — events พรุ่งนี้\n/week — 7 วันข้างหน้า\n/upcoming [N] — N โปรแกรมถัดไป\n/next — โปรแกรมถัดไป 1 รายการ\n/artists — ศิลปินที่ติดตาม\n/status — สถานะบัญชี\n/stop — ยกเลิกการเชื่อมต่อ",
            'en' => "✅ Linked successfully!\n\nYou will receive notifications before your favorite artists' programs start.\n\n📖 Commands:\n/today — today's events\n/tomorrow — tomorrow's events\n/week — next 7 days\n/upcoming [N] — next N programs\n/next — next 1 program\n/artists — followed artists\n/status — account status\n/stop — unlink account",
            'ja' => "✅ リンク完了しました！\n\nフォロー中のアーティストのプログラムが始まる前に通知を受け取ります。\n\n📖 コマンド:\n/today — 今日のイベント\n/tomorrow — 明日のイベント\n/week — 今後7日間\n/upcoming [N] — 次のNプログラム\n/next — 次の1プログラム\n/artists — フォロー中アーティスト\n/status — アカウント状態\n/stop — リンク解除"
        ],
        'link_error' => [
            'th' => "❌ เชื่อมต่อไม่สำเร็จ\n\nข้อผิดพลาด: {error}\n\nโปรดลองอีกครั้งจากหน้า My Upcoming Programs",
            'en' => "❌ Failed to link account\n\nError: {error}\n\nPlease try again from the My Upcoming Programs page",
            'ja' => "❌ リンク失敗しました\n\nエラー: {error}\n\nMy Upcoming Programs ページからもう一度試してください"
        ],
        'no_account' => [
            'th' => "❌ ไม่พบบัญชี หรือยังไม่มีศิลปินที่ติดตาม\n\nโปรดเข้าไปที่ หน้า My Upcoming Programs เพื่อเพิ่มศิลปิน",
            'en' => "❌ Account not found or no artists followed\n\nPlease go to the My Upcoming Programs page to add artists",
            'ja' => "❌ アカウントが見つかりません、またはフォロー中のアーティストがいません\n\nMy Upcoming Programs ページからアーティストを追加してください"
        ],
        'no_programs' => [
            'th' => "📭 ไม่มีโปรแกรมที่จะมาถึง\n\nโปรดอัปเดตศิลปินที่คุณติดตามจากหน้า My Upcoming Programs",
            'en' => "📭 No upcoming programs\n\nPlease update your favorite artists from the My Upcoming Programs page",
            'ja' => "📭 予定されたプログラムがありません\n\nMy Upcoming Programs ページからフォロー中のアーティストを更新してください"
        ],
        'no_today' => [
            'th' => "📭 ไม่มีโปรแกรมในวันนี้",
            'en' => "📭 No programs today",
            'ja' => "📭 本日のプログラムがありません"
        ],
        'stop' => [
            'th' => "👋 เพื่อยกเลิกการเชื่อมต่อ:\n\n1. เข้าไปที่ หน้า My Upcoming Programs\n2. กดปุ่ม ❌ ยกเลิกการเชื่อมต่อ\n\nหรือเพียงแค่ไม่สนใจข้อความนี้ก็ได้",
            'en' => "👋 To unlink your account:\n\n1. Go to the My Upcoming Programs page\n2. Click the ❌ Unlink button\n\nOr you can just ignore this message",
            'ja' => "👋 アカウントのリンクを解除するには:\n\n1. My Upcoming Programs ページに移動\n2. ❌ Unlink ボタンをクリック\n\nまたは、このメッセージを無視することもできます"
        ],
        'help' => [
            'th' => "📖 {bot_name}\n\nคำสั่ง:\n/today — events วันนี้ + จำนวน program\n/tomorrow — events พรุ่งนี้\n/week — 7 วันข้างหน้า\n/upcoming [N] — N โปรแกรมถัดไป (1–10, default 3)\n/next — โปรแกรมถัดไป 1 รายการ\n/artists — ศิลปินที่ติดตาม\n/lang th|en|ja — เปลี่ยนภาษา\n/mute N — ปิดเสียง N ชั่วโมง (1–72)\n/notify on|off — เปิด/ปิดแจ้งเตือน\n/status — สถานะบัญชี\n/start — เชื่อมต่อบัญชี\n/stop — ยกเลิกการเชื่อมต่อ",
            'en' => "📖 {bot_name}\n\nCommands:\n/today — today's events + program count\n/tomorrow — tomorrow's events\n/week — next 7 days overview\n/upcoming [N] — next N programs (1–10, default 3)\n/next — next 1 program (fastest)\n/artists — list followed artists\n/lang th|en|ja — change language\n/mute N — mute notifications for N hours (1–72)\n/notify on|off — enable or disable notifications\n/status — account status\n/start — link account\n/stop — unlink account",
            'ja' => "📖 {bot_name}\n\nコマンド:\n/today — 今日のイベント + プログラム数\n/tomorrow — 明日のイベント\n/week — 今後7日間\n/upcoming [N] — 次のNプログラム (1–10, デフォルト3)\n/next — 次の1プログラム\n/artists — フォロー中アーティスト一覧\n/lang th|en|ja — 言語変更\n/mute N — N時間ミュート (1–72)\n/notify on|off — 通知のオン/オフ\n/status — アカウント状態\n/start — アカウントリンク\n/stop — リンク解除"
        ],
        'upcoming_title' => [
            'th' => "📅 โปรแกรมที่จะมาถึง ({count} รายการ)",
            'en' => "📅 Upcoming Programs ({count})",
            'ja' => "📅 次のプログラム（{count}件）"
        ],
        'upcoming_invalid' => [
            'th' => "❌ ระบุตัวเลข 1–10 เท่านั้น\nใช้ค่าเริ่มต้น 3 รายการ",
            'en' => "❌ Please enter a number between 1–10\nUsing default of 3",
            'ja' => "❌ 1〜10の数字を入力してください\nデフォルトの3件を使用します"
        ],
        'no_tomorrow' => [
            'th' => "📭 ไม่มีโปรแกรมในวันพรุ่งนี้",
            'en' => "📭 No programs tomorrow",
            'ja' => "📭 明日のプログラムがありません"
        ],
        'week_title' => [
            'th' => "📅 7 วันข้างหน้า",
            'en' => "📅 Next 7 Days",
            'ja' => "📅 今後7日間"
        ],
        'week_no_programs' => [
            'th' => "📭 ไม่มีโปรแกรมในสัปดาห์นี้",
            'en' => "📭 No programs this week",
            'ja' => "📭 今週のプログラムがありません"
        ],
        'artists_title' => [
            'th' => "⭐ ศิลปินที่ติดตาม ({count} คน)",
            'en' => "⭐ Followed Artists ({count})",
            'ja' => "⭐ フォロー中のアーティスト（{count}人）"
        ],
        'artists_none' => [
            'th' => "📭 ยังไม่มีศิลปินที่ติดตาม",
            'en' => "📭 No artists followed yet",
            'ja' => "📭 フォロー中のアーティストがいません"
        ],
        'lang_changed' => [
            'th' => "✅ เปลี่ยนภาษาเป็น: {lang}",
            'en' => "✅ Language changed to: {lang}",
            'ja' => "✅ 言語を変更しました: {lang}"
        ],
        'lang_invalid' => [
            'th' => "❌ ภาษาที่รองรับ: th, en, ja\nตัวอย่าง: /lang th",
            'en' => "❌ Supported languages: th, en, ja\nExample: /lang th",
            'ja' => "❌ 対応言語: th, en, ja\n例: /lang th"
        ],
        'mute_set' => [
            'th' => "🔕 ปิดเสียงแจ้งเตือนจนถึง {time} น.",
            'en' => "🔕 Notifications muted until {time}",
            'ja' => "🔕 通知を{time}までミュートしました"
        ],
        'mute_invalid' => [
            'th' => "❌ กรุณาระบุชั่วโมง 1–72\nตัวอย่าง: /mute 8",
            'en' => "❌ Please enter hours between 1–72\nExample: /mute 8",
            'ja' => "❌ 1〜72時間で入力してください\n例: /mute 8"
        ],
        'notify_enabled' => [
            'th' => "🔔 เปิดการแจ้งเตือนแล้ว",
            'en' => "🔔 Notifications enabled",
            'ja' => "🔔 通知をオンにしました"
        ],
        'notify_disabled' => [
            'th' => "🔕 ปิดการแจ้งเตือนแล้ว\nพิมพ์ /notify on เพื่อเปิดอีกครั้ง",
            'en' => "🔕 Notifications disabled\nType /notify on to re-enable",
            'ja' => "🔕 通知をオフにしました\n/notify on で再度オンにできます"
        ],
        'notify_invalid' => [
            'th' => "❌ ใช้: /notify on หรือ /notify off",
            'en' => "❌ Use: /notify on or /notify off",
            'ja' => "❌ 使い方: /notify on または /notify off"
        ],
        'status' => [
            'th' => "📊 สถานะบัญชี\n\n⭐ ติดตาม: {count} ศิลปิน\n🌐 ภาษา: {lang}\n🔔 การแจ้งเตือน: {notify}\n🔕 Mute: {mute}",
            'en' => "📊 Account Status\n\n⭐ Following: {count} artists\n🌐 Language: {lang}\n🔔 Notifications: {notify}\n🔕 Mute: {mute}",
            'ja' => "📊 アカウント状態\n\n⭐ フォロー中: {count}人\n🌐 言語: {lang}\n🔔 通知: {notify}\n🔕 ミュート: {mute}"
        ],
        'select_language' => [
            'th' => "🌐 โปรดเลือกภาษาของคุณ:\n\nคุณจะได้รับการแจ้งเตือนในภาษาที่เลือก",
            'en' => "🌐 Please select your language:\n\nYou will receive notifications in the selected language",
            'ja' => "🌐 言語を選択してください:\n\n選択した言語で通知を受け取ります"
        ]
    ];

    // Get message in the specified language, fallback to Thai
    $language = in_array($language, ['th', 'en', 'ja']) ? $language : 'th';
    $message = $messages[$key][$language] ?? $messages[$key]['th'] ?? '';

    // Auto-inject {bot_name} using site title (Admin › Settings › Site) if not provided
    if (!isset($params['bot_name'])) {
        $params['bot_name'] = function_exists('get_site_title') ? get_site_title() : (defined('APP_NAME') ? APP_NAME : 'Idol Stage Timetable');
    }

    // Replace parameters
    foreach ($params as $param_key => $param_value) {
        $message = str_replace('{' . $param_key . '}', $param_value, $message);
    }

    return $message;
}

/**
 * Get Telegram language from language code
 *
 * Maps Telegram language codes to our supported languages (th, en, ja)
 *
 * @param string $telegramLang Telegram language code (e.g., 'th', 'en', 'ja')
 * @return string Normalized language code (th, en, ja) - defaults to 'th'
 */
function telegram_normalize_language($telegramLang) {
    $lang = strtolower(trim($telegramLang ?? ''));

    // Map Telegram language codes to our languages
    $mapping = [
        'th' => 'th',
        'en' => 'en',
        'en-us' => 'en',
        'en-gb' => 'en',
        'ja' => 'ja',
        'ja-jp' => 'ja',
    ];

    return $mapping[$lang] ?? 'th';  // Default to Thai
}

/**
 * Find a favorites file by Telegram chat ID (scans all shard directories).
 *
 * @param int $chat_id Telegram chat/user ID
 * @return array|null ['data' => array, 'path' => string] or null if not found
 */
function find_favorites_by_chat_id(int $chat_id): ?array {
    $favDir = defined('FAVORITES_DIR') ? FAVORITES_DIR : '';
    if (!$favDir || !is_dir($favDir)) return null;
    foreach (scandir($favDir) as $shard) {
        if ($shard === '.' || $shard === '..' || !is_dir("$favDir/$shard")) continue;
        foreach (scandir("$favDir/$shard") as $filename) {
            if (substr($filename, -5) !== '.json') continue;
            $path = "$favDir/$shard/$filename";
            $data = json_decode(file_get_contents($path), true);
            if (is_array($data) && (int)($data['telegram_chat_id'] ?? 0) === $chat_id) {
                return ['data' => $data, 'path' => $path];
            }
        }
    }
    return null;
}

/**
 * Check if a user's Telegram notifications are currently muted.
 *
 * @param array $favData Favorites data array
 * @return bool True if muted (mute_until is in the future)
 */
function telegram_is_muted(array $favData): bool {
    $until = (int)($favData['telegram_mute_until'] ?? 0);
    return $until > 0 && time() < $until;
}

/**
 * Check if a user has Telegram notifications enabled.
 * Absent or null means enabled (opt-out model).
 *
 * @param array $favData Favorites data array
 * @return bool True if notifications are enabled
 */
function telegram_notify_is_enabled(array $favData): bool {
    if (!array_key_exists('telegram_notify_enabled', $favData)) return true;
    if ($favData['telegram_notify_enabled'] === null) return true;
    return (bool)$favData['telegram_notify_enabled'];
}

/**
 * Format programs as a condensed event list (event name + program count).
 * Used by /today, /tomorrow, and each day block in /week.
 *
 * @param array  $programs  Array of program rows with 'event_name' key
 * @param string $dateStr   Date string Y-m-d
 * @param string $language  th|en|ja
 * @param string $context   today|tomorrow|week_day
 * @return string HTML-formatted message
 */
function telegram_format_events_list(array $programs, string $dateStr, string $language, string $context = 'today'): string {
    $dt = DateTime::createFromFormat('Y-m-d', $dateStr);
    if (!$dt) $dt = new DateTime($dateStr);

    $dayOfWeek = (int)$dt->format('N'); // 1=Mon ... 7=Sun
    $d  = (int)$dt->format('j');
    $m  = (int)$dt->format('n');
    $y  = $dt->format('Y');

    $dayNamesTH      = ['', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์', 'อาทิตย์'];
    $dayNamesEN      = ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    $dayNamesJA      = ['', '月曜日', '火曜日', '水曜日', '木曜日', '金曜日', '土曜日', '日曜日'];
    $dayShortTH      = ['', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.', 'อา.'];
    $dayShortEN      = ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $dayShortJA      = ['', '月', '火', '水', '木', '金', '土', '日'];
    $monthNamesTH    = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $monthFullTH     = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
    $monthNamesEN    = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $monthFullEN     = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    // Build date header
    if ($context === 'week_day') {
        if ($language === 'en') {
            $header = "📆 <b>{$dayShortEN[$dayOfWeek]} {$d} {$monthNamesEN[$m]}</b>";
        } elseif ($language === 'ja') {
            $header = "📆 <b>{$m}/{$d}（{$dayShortJA[$dayOfWeek]}）</b>";
        } else {
            $header = "📆 <b>{$dayShortTH[$dayOfWeek]} {$d} {$monthNamesTH[$m]}</b>";
        }
    } else {
        $prefix = ($context === 'tomorrow')
            ? ['th' => '📅 พรุ่งนี้', 'en' => '📅 Tomorrow', 'ja' => '📅 明日']
            : ['th' => '📅 วันนี้',   'en' => '📅 Today',    'ja' => '📅 今日'];
        $p = $prefix[$language] ?? $prefix['th'];
        if ($language === 'en') {
            $header = "{$p} — {$dayNamesEN[$dayOfWeek]}, {$monthFullEN[$m]} {$d}, {$y}";
        } elseif ($language === 'ja') {
            $header = "{$p} — {$y}年{$m}月{$d}日（{$dayNamesJA[$dayOfWeek]}）";
        } else {
            $header = "{$p} — {$dayNamesTH[$dayOfWeek]}ที่ {$d} {$monthFullTH[$m]} {$y}";
        }
    }

    // Group by event_name
    $byEvent = [];
    foreach ($programs as $prog) {
        $ev = $prog['event_name'] ?? '—';
        $byEvent[$ev] = ($byEvent[$ev] ?? 0) + 1;
    }

    $lines = $header . "\n\n";
    foreach ($byEvent as $eventName => $count) {
        if ($language === 'en') {
            $countLabel = $count === 1 ? '1 program' : "{$count} programs";
        } elseif ($language === 'ja') {
            $countLabel = "{$count}プログラム";
        } else {
            $countLabel = "{$count} โปรแกรม";
        }
        $lines .= "🎪 <b>" . htmlspecialchars($eventName, ENT_QUOTES, 'UTF-8') . "</b>\n";
        $lines .= "📊 {$countLabel}\n\n";
    }

    return rtrim($lines);
}
