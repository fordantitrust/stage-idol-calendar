<?php
/**
 * My Upcoming Programs (/my/{slug})
 */
require_once 'config.php';
require_once 'functions/favorites.php';
send_security_headers();

if (FAVORITES_HMAC_SECRET === 'REPLACE_WITH_GENERATED_SECRET') {
    http_response_code(503);
    exit('Favorites not configured.');
}

$siteTitle     = get_site_title();
$theme         = get_site_theme();
$headerCoverBg = get_header_cover_bg();

$rawSlug = $_GET['slug'] ?? '';
$parsed  = $rawSlug ? fav_parse_slug($rawSlug) : null;

if ($rawSlug && !$parsed) {
    http_response_code(404);
}

$token   = $parsed ? $parsed['token'] : null;
$favData = $token ? fav_read($token) : null;
$expired = ($token !== null && $favData === null);

if ($favData) {
    fav_touch($favData);
    fav_maybe_cleanup(200);
}

$slug = $parsed ? fav_build_slug($parsed['token']) : '';

$scheme       = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host         = get_safe_host();
$basePath     = get_base_path();
$myFavUrl     = $slug ? $scheme . '://' . $host . $basePath . '/my-favorites/' . $slug : '';
$dashboardUrl = $slug ? $scheme . '://' . $host . $basePath . '/my/' . $slug : '';
$feedUrl      = $slug ? $scheme . '://' . $host . $basePath . '/my/' . $slug . '/feed' : '';

// Telegram linking status
$telegramChatId = null;
$telegramLinked = false;
if ($favData && telegram_is_enabled()) {
    $telegramChatId = $favData['telegram_chat_id'] ?? null;
    $telegramLinked = !empty($telegramChatId);
}

// Web Push status
$webpushEnabled = defined('WEBPUSH_ENABLED') && WEBPUSH_ENABLED;
$vapidPublicKey = defined('WEBPUSH_VAPID_PUBLIC_KEY') ? WEBPUSH_VAPID_PUBLIC_KEY : '';

// Viewer timezone (used for notification time display across Telegram + Web Push)
$userTimezone = $favData ? ($favData['user_timezone'] ?? '') : '';
$userTzManual = $favData ? !empty($favData['user_timezone_manual']) : false;

$artistIds     = $favData ? ($favData['artists'] ?? []) : [];
$artistsMap    = [];
$byDate        = [];
$totalPrograms = 0;

if (!empty($artistIds)) {
    try {
        $db  = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pls = implode(',', array_fill(0, count($artistIds), '?'));

        $stmtA = $db->prepare("
            SELECT a.id, a.name, a.is_group, a.group_id, g.name AS group_name
            FROM artists a LEFT JOIN artists g ON g.id = a.group_id
            WHERE a.id IN ($pls) ORDER BY a.name
        ");
        $stmtA->execute($artistIds);
        foreach ($stmtA->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $artistsMap[(int)$row['id']] = $row;
        }
        $stmtA->closeCursor(); $stmtA = null;

        // Also include programs of groups that followed artists belong to
        $groupIds = [];
        foreach ($artistsMap as $a) {
            if (!empty($a['group_id'])) {
                $groupIds[] = (int)$a['group_id'];
            }
        }
        $allArtistIds = array_values(array_unique(array_merge($artistIds, $groupIds)));
        $plsAll = implode(',', array_fill(0, count($allArtistIds), '?'));

        $today = date('Y-m-d');
        $stmtP = $db->prepare("
            SELECT DISTINCT p.id, p.title, p.start AS start_date, p.end AS end_date,
                   p.location, p.categories, p.program_type, p.stream_url,
                   p.event_id, e.name AS event_name, e.slug AS event_slug,
                   e.timezone AS event_timezone
            FROM programs p
            JOIN events e ON e.id = p.event_id AND e.is_active = 1
            WHERE p.id IN (
                SELECT DISTINCT pa.program_id FROM program_artists pa WHERE pa.artist_id IN ($plsAll)
            )
            AND DATE(p.start) >= :today ORDER BY p.start ASC
        ");
        $stmtP->execute(array_merge($allArtistIds, ['today' => $today]));
        $programs = $stmtP->fetchAll(PDO::FETCH_ASSOC);
        $stmtP->closeCursor(); $stmtP = null;
        $db = null;

        foreach ($programs as $p) {
            $date = substr($p['start_date'], 0, 10);
            $byDate[$date][] = $p;
            $totalPrograms++;
        }
        ksort($byDate);

    } catch (PDOException $e) { /* continue with empty */ }
}

// Build event → color index map (for visual grouping by event)
$eventColorMap     = []; // event_id (int) → 0-5
$eventSlugColorMap = []; // event_slug     → 0-5
$_ci = 0;
foreach ($byDate as $_progs) {
    foreach ($_progs as $_p) {
        $eid = (int)$_p['event_id'];
        if (!isset($eventColorMap[$eid])) {
            $eventColorMap[$eid] = $_ci % 6;
            $eventSlugColorMap[$_p['event_slug']] = $_ci % 6;
            $_ci++;
        }
    }
}
unset($_ci, $_progs, $_p, $eid);

// Prepare calendar-safe program data for JS
$calPrograms = [];
foreach ($byDate as $date => $progs) {
    $calPrograms[$date] = [];
    foreach ($progs as $p) {
        $tStart  = substr($p['start_date'], 11, 5);
        $tEnd    = substr($p['end_date'],   11, 5);
        $timeStr = ($tStart === $tEnd || !$tEnd || $tEnd === '00:00') ? $tStart : $tStart . '–' . $tEnd;
        // Per-program ISO with explicit offset (for cross-TZ "local time" annotation in JS)
        $_pTzName = $p['event_timezone'] ?: DEFAULT_TIMEZONE;
        try { $_calTz = new DateTimeZone($_pTzName); }
        catch (Exception $e) { $_calTz = new DateTimeZone(DEFAULT_TIMEZONE); $_pTzName = DEFAULT_TIMEZONE; }
        $calPrograms[$date][] = [
            'time'        => $timeStr,
            'start_iso'   => $tStart,
            'end_iso'     => ($tEnd && $tEnd !== '00:00') ? $tEnd : $tStart,
            'start_full'  => (new DateTime($p['start_date'], $_calTz))->format('c'),
            'end_full'    => (new DateTime($p['end_date'],   $_calTz))->format('c'),
            'event_tz'    => $_pTzName,
            'title'       => $p['title'],
            'program_type'=> $p['program_type'] ?? '',
            'event_name'  => $p['event_name'],
            'event_slug'  => $p['event_slug'],
            'location'    => $p['location'] ?? '',
            'categories'  => $p['categories'] ?? '',
            'stream_url'  => $p['stream_url'] ?? '',
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="theme-color" content="#E91E63">
    <link rel="manifest" href="<?= get_base_path() ?>/manifest.json">
    <title>My Favorites Upcoming Programs - <?= htmlspecialchars($siteTitle) ?></title>
    <?php seo_render_meta(['noindex' => true]); ?>
    <link rel="stylesheet" href="<?= asset_url('styles/common.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('styles/artist.css') ?>">
    <?php if ($theme !== 'sakura'): ?>
    <link rel="stylesheet" href="<?= asset_url('styles/themes/' . $theme . '.css') ?>">
    <?php endif; ?>
    <style>
        /* ── Banner & URL ──────────────────────────────────────────────── */
        .fav-save-banner {
            background: linear-gradient(135deg,#fff8e1,#fff3cd);
            border: 1px solid #ffe082; border-left: 4px solid #f9a825;
            border-radius: 8px; padding: 14px 16px; margin-bottom: 20px;
        }
        .fav-save-banner .warn-label { font-size:.85rem; font-weight:600; color:#f57f17; margin-bottom:8px; }
        .fav-url-row { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .fav-url-input {
            flex:1; min-width:0; padding:7px 10px; border:1px solid #ddd;
            border-radius:6px; font-size:.82rem; background:#fff;
            color:#333; font-family:monospace; cursor:text;
        }
        /* ── Sections ──────────────────────────────────────────────────── */
        .fav-section { margin-bottom:28px; }
        .fav-section h2 { font-size:1rem; font-weight:700; color:var(--sakura-dark,#e91e63); margin:0 0 12px; }

        /* ── Followed Artists (stats + collapsible) ─────────────────────── */
        .fav-followed-section { margin-bottom:24px; }
        .fav-followed-toggle {
            width:100%; box-sizing:border-box;
            display:flex; align-items:center; justify-content:space-between;
            gap:10px;
            background:#fff; border:1px solid #f0e0ea;
            border-radius:10px;
            padding:12px 16px;
            cursor:pointer;
            font-size:.92rem; color:#333;
            font-family:inherit; text-align:left;
            transition:all .15s;
        }
        .fav-followed-toggle:not(:disabled):hover {
            border-color:var(--sakura-medium,#f48fb1);
            background:var(--sakura-bg-soft,#fff8fa);
        }
        .fav-followed-toggle:disabled { cursor:default; opacity:.85; }
        .fav-followed-stats {
            display:inline-flex; align-items:center;
            gap:5px; flex-wrap:wrap; line-height:1.5;
        }
        .fav-followed-stats .stats-icon { font-size:1.1em; }
        .fav-followed-stats strong {
            color:var(--sakura-deep,#C2185B);
            font-weight:700;
        }
        .fav-followed-caret {
            color:#999; font-size:.85em; flex-shrink:0;
            transition:transform .2s;
        }
        .fav-followed-section.is-open .fav-followed-caret { transform:rotate(180deg); }
        .fav-followed-content { padding:14px 4px 4px; }
        .fav-followed-section .fav-empty {
            padding:12px 4px 4px; text-align:left;
        }
        .fav-manage-link {
            display:inline-block; margin-top:12px;
            font-size:.85rem; font-weight:500;
            color:var(--sakura-deep,#C2185B);
            text-decoration:none;
        }
        .fav-manage-link:hover { text-decoration:underline; }

        .fav-artist-chips { display:flex; flex-wrap:wrap; gap:8px; }
        .fav-artist-chip {
            display:inline-flex; align-items:center; gap:4px;
            background:#fff; border:1px solid #f8bbd0;
            border-radius:20px; padding:5px 12px 5px 10px; font-size:.85rem; color:#333;
        }
        .fav-artist-chip a { color:inherit; text-decoration:none; }
        .fav-artist-chip a:hover { color:var(--sakura-dark,#e91e63); }
        .fav-unfollow-btn {
            background:none; border:none; cursor:pointer;
            color:#bbb; font-size:1rem; padding:0 0 0 4px; line-height:1;
        }
        .fav-unfollow-btn:hover { color:#e91e63; }
        .fav-empty { color:#999; font-size:.9rem; padding:20px 0; text-align:center; }
        /* ── Program list ──────────────────────────────────────────────── */
        .fav-date-header { font-size:.82rem; font-weight:600; color:#888; margin:10px 0 6px; padding-left:2px; }
        .fav-program-row {
            display:flex; align-items:flex-start; gap:10px;
            padding:8px 10px; border-radius:6px; background:#fff;
            border:1px solid #f0f0f0; margin-bottom:6px; font-size:.85rem;
        }
        .fav-program-row:hover { border-color:#f8bbd0; background:#fff9fb; }
        .fav-time { color:#888; white-space:nowrap; min-width:90px; padding-top:1px; }
        .fav-time-local {
            display:block; font-size:.72rem; color:#9ca3af; font-style:italic;
            margin-top:2px; font-weight:400;
        }
        .fav-tz-chip {
            display:inline-block; font-size:.72rem; color:#9ca3af; font-style:italic;
            margin-left:4px; white-space:nowrap;
        }
        .fav-prog-body { flex:1; min-width:0; }
        .fav-prog-title { font-weight:600; color:#333; margin-bottom:3px; }
        .fav-prog-meta { color:#999; font-size:.78rem; }
        .fav-type-badge {
            display:inline-block; padding:1px 7px; border-radius:10px;
            font-size:.75rem; font-weight:600; background:#f3e5f5; color:#7b1fa2; margin-left:4px;
        }
        .fav-stream-btn {
            display:inline-block; padding:2px 10px; border-radius:10px;
            font-size:.78rem; font-weight:600; text-decoration:none;
            background:#ffebee; color:#c62828;
        }
        .fav-no-programs { color:#999; font-size:.88rem; text-align:center; padding:24px; }
        /* ── Now Playing ───────────────────────────────────────────────── */
        .fav-program-row.fav-now-playing {
            border-color:var(--sakura-dark,#e91e63);
            border-left:3px solid var(--sakura-dark,#e91e63);
            background:linear-gradient(135deg,#fff0f5 0%,#fff 100%);
        }
        /* ── Event color coding (0–5 cycling) ─────────────────────── */
        .fav-ec-0 { background:#fff0f5; border-left:3px solid #f48fb1; }
        .fav-ec-1 { background:#f0f5ff; border-left:3px solid #90caf9; }
        .fav-ec-2 { background:#f0fff4; border-left:3px solid #a5d6a7; }
        .fav-ec-3 { background:#fffbf0; border-left:3px solid #ffcc80; }
        .fav-ec-4 { background:#f8f0ff; border-left:3px solid #ce93d8; }
        .fav-ec-5 { background:#f0fbff; border-left:3px solid #80cbc4; }
        .fav-ec-0:hover,.fav-ec-1:hover,.fav-ec-2:hover,
        .fav-ec-3:hover,.fav-ec-4:hover,.fav-ec-5:hover { filter:brightness(.96); background-color:inherit; }
        /* now-playing overrides event color */
        .fav-program-row.fav-now-playing { background:linear-gradient(135deg,#fff0f5 0%,#fff 100%) !important; }
        .fav-now-badge {
            display:inline-block; padding:1px 7px; border-radius:10px;
            font-size:.72rem; font-weight:700; background:var(--sakura-dark,#e91e63);
            color:#fff; margin-left:6px; vertical-align:middle;
            animation:fav-pulse 1.5s ease-in-out infinite;
        }
        @keyframes fav-pulse {
            0%,100% { opacity:1; } 50% { opacity:.6; }
        }
        /* ── Errors / access denied ────────────────────────────────────── */
        .fav-error-box {
            background:#fff3e0; border:1px solid #ffe0b2; border-radius:8px;
            padding:24px; text-align:center; margin-top:24px;
        }
        .fav-error-box h2 { color:#e65100; margin:0 0 8px; }
        .fav-error-box p { color:#666; margin:0 0 16px; }
        .fav-no-slug { text-align:center; padding:60px 20px; color:#999; }
        .fav-no-slug .empty-icon { font-size:3rem; margin-bottom:12px; }
        .fav-no-slug p { margin:0 0 8px; font-size:.9rem; }

        /* ── Action Chip Bar (Transfer / Telegram / Push) ──────────────── */
        .fav-actions-bar {
            display:flex; gap:8px; margin-bottom:20px;
        }
        .fav-action-chip {
            flex:1 1 0; min-width:0;
            display:flex; align-items:center; justify-content:center;
            gap:6px; padding:10px 12px;
            background:#fff;
            border:1px solid #f0e0ea;
            border-radius:10px;
            cursor:pointer;
            font-size:.88rem; font-weight:500;
            color:#666;
            transition:all .15s;
            position:relative;
        }
        .fav-action-chip:hover {
            border-color:var(--sakura-medium,#f48fb1);
            background:var(--sakura-bg-soft,#fff8fa);
            color:var(--sakura-deep,#C2185B);
        }
        .fav-action-chip .chip-icon { font-size:1.05em; flex-shrink:0; line-height:1; }
        .fav-action-chip .chip-label {
            flex:0 1 auto; min-width:0;
            overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
        }
        .fav-action-chip .chip-dot {
            width:8px; height:8px; border-radius:50%;
            background:#cfd8dc; flex-shrink:0;
        }
        .fav-action-chip.is-active {
            background:#e8f5e9;
            border-color:#81c784;
            color:#2e7d32;
        }
        .fav-action-chip.is-active .chip-dot { background:#4caf50; }
        .fav-action-chip:disabled { opacity:.55; cursor:default; }
        .fav-action-chip:disabled:hover {
            border-color:#f0e0ea; background:#fff; color:#666;
        }
        @media (max-width:480px) {
            .fav-actions-bar { gap:6px; }
            .fav-action-chip { padding:9px 8px; font-size:.82rem; }
        }
        @media (max-width:360px) {
            .fav-action-chip .chip-label { display:none; }
            .fav-action-chip { padding:11px 8px; }
            .fav-action-chip .chip-icon { font-size:1.15em; }
        }

        /* ── Timezone picker ───────────────────────────────────────────── */
        .fav-tz-row {
            display:flex; align-items:center; gap:8px; flex-wrap:wrap;
            margin:-8px 0 20px; padding:8px 12px;
            background:#fff; border:1px solid #f0e0ea; border-radius:10px;
        }
        .fav-tz-label { font-size:.85rem; font-weight:600; color:#555; white-space:nowrap; }
        .fav-tz-select {
            flex:1 1 200px; min-width:0; padding:7px 10px;
            font-size:.9rem; border:1px solid #e0c8d6; border-radius:8px;
            background:#fff; color:#333;
        }
        .fav-tz-hint { flex-basis:100%; font-size:.78rem; color:#888; }
        @media (max-width:480px) {
            .fav-tz-row { gap:6px; }
            .fav-tz-label { flex-basis:100%; }
        }

        /* ── Mini Calendar ─────────────────────────────────────────────── */
        .fav-cal-wrap {
            background:#fff; border:1px solid #f0f0f0;
            border-radius:12px; padding:14px 16px; margin-bottom:24px;
        }
        .fav-cal-header {
            display:flex; align-items:center; justify-content:space-between;
            margin-bottom:10px;
        }
        .fav-cal-title { font-size:.95rem; font-weight:700; color:#333; }
        .fav-cal-nav {
            background:none; border:1px solid #e0e0e0; border-radius:50%;
            width:28px; height:28px; cursor:pointer; font-size:.85rem;
            display:flex; align-items:center; justify-content:center;
            color:#888; flex-shrink:0;
        }
        .fav-cal-nav:hover:not(:disabled) { border-color:var(--sakura-dark,#e91e63); color:var(--sakura-dark,#e91e63); }
        .fav-cal-nav:disabled { opacity:.3; cursor:default; }
        .fav-cal-grid {
            display:grid; grid-template-columns:repeat(7,1fr); gap:2px;
        }
        .fav-cal-dow {
            text-align:center; font-size:.72rem; font-weight:600;
            color:#aaa; padding:4px 0 6px;
        }
        .fav-cal-day {
            text-align:center; padding:5px 2px; border-radius:6px;
            font-size:.82rem; color:#555; min-height:36px;
            display:flex; flex-direction:column; align-items:center; justify-content:flex-start;
            gap:3px; position:relative;
        }
        .fav-cal-day.other-month { color:#ccc; }
        .fav-cal-day.today .fav-cal-day-num {
            background:var(--sakura-medium,#f48fb1); color:#fff;
            border-radius:50%; width:22px; height:22px;
            display:flex; align-items:center; justify-content:center;
        }
        .fav-cal-day.has-programs { cursor:pointer; }
        .fav-cal-day.has-programs:hover { background:#fff0f5; }
        .fav-cal-day-num { width:22px; height:22px; display:flex; align-items:center; justify-content:center; }
        .fav-cal-dot {
            width:6px; height:6px; border-radius:50%;
            background:var(--sakura-dark,#e91e63);
        }
        /* ── Day Modal ─────────────────────────────────────────────────── */
        .fav-day-overlay {
            display:none; position:fixed; inset:0;
            background:rgba(0,0,0,.45); z-index:1000;
            align-items:center; justify-content:center; padding:16px;
        }
        .fav-day-overlay.open { display:flex; }
        .fav-day-modal {
            background:#fff; border-radius:12px; width:100%; max-width:480px;
            max-height:80vh; display:flex; flex-direction:column;
            box-shadow:0 8px 32px rgba(0,0,0,.2);
        }
        .fav-day-modal-header {
            display:flex; align-items:center; justify-content:space-between;
            padding:14px 16px 12px; border-bottom:1px solid #f0f0f0; flex-shrink:0;
        }
        .fav-day-modal-title { font-size:.95rem; font-weight:700; color:var(--sakura-dark,#e91e63); }
        .fav-day-modal-close {
            background:none; border:none; font-size:1.3rem; cursor:pointer;
            color:#aaa; line-height:1; padding:0 4px;
        }
        .fav-day-modal-close:hover { color:#e91e63; }
        .fav-day-modal-body { overflow-y:auto; padding:12px 16px 16px; flex:1; }

        @media (max-width:480px) {
            .fav-url-row { flex-direction:column; }
            .fav-url-input { width:100%; }
            .fav-cal-day { font-size:.75rem; }
        }

        /* ── QR Transfer (inside modal) ─────────────────────────────────── */
        .fav-qr-desc { margin: 0 0 10px; font-size: .88rem; color: #555; }
        .fav-qr-container {
            display: inline-block; padding: 8px;
            background: #fff; border-radius: 8px;
            border: 1px solid var(--sakura-light, #FFB7C5);
            margin-bottom: 10px;
        }
        .fav-transfer-steps {
            font-size: .85rem; color: #555;
            margin: 0; padding-left: 1.2rem; line-height: 1.9;
        }
        .fav-qr-loading, .fav-qr-error { font-size: .88rem; color: #888; padding: 8px 0; display: block; }

        /* ── View toggle (List / Timeline) ─────────────────────────────── */
        .fav-section-header {
            display:flex; align-items:center; justify-content:space-between;
            gap:10px; margin-bottom:12px; flex-wrap:wrap;
        }
        .fav-section-header h2 { margin:0; }
        .fav-view-toggle {
            display:inline-flex; gap:3px; padding:3px;
            background:#f3f3f3; border-radius:8px;
        }
        .fav-vt-btn {
            background:transparent; border:none;
            padding:6px 12px; font-size:.82rem; font-weight:500;
            color:#666; cursor:pointer; border-radius:6px;
            font-family:inherit; transition:all .15s;
        }
        .fav-vt-btn:hover { color:var(--sakura-deep,#C2185B); }
        .fav-vt-btn.is-active {
            background:#fff; color:var(--sakura-deep,#C2185B);
            box-shadow:0 1px 3px rgba(0,0,0,.08);
        }

        /* ── Timeline view ─────────────────────────────────────────────── */
        .fav-timeline-day {
            background:#fff; border:1px solid #f0f0f0;
            border-radius:10px; padding:12px; margin-bottom:16px;
        }
        .fav-tl-date-header {
            font-size:.92rem; font-weight:700;
            color:var(--sakura-dark,#e91e63); margin-bottom:10px;
            padding-left:2px;
            display:flex; align-items:center; gap:10px; flex-wrap:wrap;
        }
        .fav-tl-overlap-badge {
            display:inline-flex; align-items:center;
            padding:2px 9px; border-radius:10px;
            font-size:.72rem; font-weight:700;
            background:#ffebee; color:#c62828;
            border:1px solid #ffcdd2;
        }
        .fav-tl-chart {
            overflow-x:auto;
            border:1px solid #f0f0f0; border-radius:8px;
            background:#fafafa;
        }
        .fav-tl-header {
            display:flex; position:sticky; top:0; z-index:3;
            background:#fff; border-bottom:1px solid #e0e0e0;
        }
        .fav-tl-time-label {
            flex:0 0 60px; padding:8px 6px;
            font-size:.75rem; font-weight:600; color:#666; text-align:center;
            background:#f8f8f8; border-right:1px solid #e0e0e0;
            box-sizing:border-box;
        }
        .fav-tl-event-header {
            flex:0 0 180px; padding:8px 10px;
            font-size:.82rem; font-weight:600;
            text-align:center; border-right:1px solid #e8e8e8;
            overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
            color:#333; box-sizing:border-box;
        }
        .fav-tl-body { display:flex; position:relative; }
        .fav-tl-time-axis {
            flex:0 0 60px;
            background:#f8f8f8; border-right:1px solid #e0e0e0;
            box-sizing:border-box;
        }
        .fav-tl-time-slot {
            height:70px; padding:4px 6px 0;
            font-size:.72rem; color:#888;
            border-bottom:1px dashed #e8e8e8;
            box-sizing:border-box;
        }
        .fav-tl-event-col {
            flex:0 0 180px; position:relative;
            border-right:1px solid #e8e8e8;
            box-sizing:border-box;
        }
        .fav-tl-grid { position:absolute; inset:0; }
        .fav-tl-grid-slot {
            height:70px;
            border-bottom:1px dashed #e8e8e8;
            box-sizing:border-box;
        }
        .fav-tl-overlap-zone {
            position:absolute; left:0; right:0;
            background:rgba(229,57,53,.10);
            border-top:1px dashed rgba(229,57,53,.45);
            border-bottom:1px dashed rgba(229,57,53,.45);
            z-index:1; pointer-events:none;
        }
        .fav-tl-bar {
            position:absolute; left:4px; right:4px;
            padding:4px 6px;
            border-radius:4px;
            overflow:hidden; cursor:pointer;
            z-index:2;
            background:#fff; border:1px solid;
            box-shadow:0 1px 2px rgba(0,0,0,.06);
            transition:all .15s; min-height:24px;
            font-size:.75rem; line-height:1.25;
            box-sizing:border-box;
        }
        .fav-tl-bar:hover {
            box-shadow:0 2px 6px rgba(0,0,0,.12);
            transform:translateY(-1px);
        }
        .fav-tl-bar.has-overlap { border-width:2px; }
        .fav-tl-bar-time { font-weight:700; color:#333; font-size:.72rem; }
        .fav-tl-bar-time-sub {
            font-size:.62rem; color:#888; font-style:italic; font-weight:400;
            margin-top:-1px;
        }
        .fav-tl-event-tz {
            font-weight:400; font-size:.85em; color:#777; margin-left:4px; font-style:italic;
        }
        .fav-tl-bar-title {
            color:#333; font-weight:500;
            overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
        }
        .fav-tl-bar-type { font-size:.68rem; color:#777; margin-top:1px; }
        /* Event color overrides */
        .fav-tl-event-header.fav-ec-0, .fav-tl-bar.fav-ec-0 { border-color:#f48fb1; }
        .fav-tl-event-header.fav-ec-0 { background:#fff0f5; color:#ad1457; }
        .fav-tl-event-header.fav-ec-1, .fav-tl-bar.fav-ec-1 { border-color:#90caf9; }
        .fav-tl-event-header.fav-ec-1 { background:#f0f5ff; color:#0d47a1; }
        .fav-tl-event-header.fav-ec-2, .fav-tl-bar.fav-ec-2 { border-color:#a5d6a7; }
        .fav-tl-event-header.fav-ec-2 { background:#f0fff4; color:#1b5e20; }
        .fav-tl-event-header.fav-ec-3, .fav-tl-bar.fav-ec-3 { border-color:#ffcc80; }
        .fav-tl-event-header.fav-ec-3 { background:#fffbf0; color:#e65100; }
        .fav-tl-event-header.fav-ec-4, .fav-tl-bar.fav-ec-4 { border-color:#ce93d8; }
        .fav-tl-event-header.fav-ec-4 { background:#f8f0ff; color:#4a148c; }
        .fav-tl-event-header.fav-ec-5, .fav-tl-bar.fav-ec-5 { border-color:#80cbc4; }
        .fav-tl-event-header.fav-ec-5 { background:#f0fbff; color:#004d40; }
        @media (max-width:768px) {
            .fav-tl-time-label, .fav-tl-time-axis { flex-basis:50px; }
            .fav-tl-event-header, .fav-tl-event-col { flex-basis:140px; }
            .fav-tl-time-slot, .fav-tl-grid-slot { height:60px; }
            .fav-tl-bar-title { font-size:.72rem; }
            .fav-tl-bar-time  { font-size:.68rem; }
        }
    </style>
</head>
<body>
<div class="container">
    <header<?php if ($headerCoverBg): ?> class="has-site-cover" style="--header-cover-url: url('<?php echo htmlspecialchars(get_base_path() . '/' . $headerCoverBg, ENT_QUOTES, 'UTF-8'); ?>')"<?php endif; ?>>
        <div class="header-top-left">
            <a href="<?= get_base_path() ?>/" class="home-icon-btn" title="หน้าแรก">
                <svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M10 2L2 9h2v9h5v-5h2v5h5V9h2L10 2z" fill="currentColor"/>
                </svg>
            </a>
            <?php if ($slug): ?>
            <a href="<?= get_base_path() ?>/my-favorites/<?= htmlspecialchars($slug) ?>" class="home-icon-btn" title="My Favorites">⭐</a>
            <a href="<?= get_base_path() ?>/my/<?= htmlspecialchars($slug) ?>" class="home-icon-btn" title="My Upcoming Programs" style="background:var(--sakura-medium,#f48fb1);color:#fff" aria-current="page">📅</a>
            <?php endif; ?>
        </div>
        <div class="language-switcher">
            <button class="lang-btn active" data-lang="th" onclick="changeLanguage('th')">TH</button>
            <button class="lang-btn" data-lang="en" onclick="changeLanguage('en')">EN</button>
            <button class="lang-btn" data-lang="ja" onclick="changeLanguage('ja')">日本</button>
        </div>
        <h1 data-i18n="fav.h1">⭐ My Favorites Upcoming Programs</h1>
        <?php if ($favData): ?>
        <p style="margin:0;color:#888;font-size:.85rem;">
            <span><?= count($artistIds) ?></span> <span data-i18n="fav.statsArtists">ศิลปิน</span>
            · <span><?= $totalPrograms ?></span> <span data-i18n="fav.statsPrograms">upcoming programs</span>
        </p>
        <?php endif; ?>
    </header>

    <div class="content">

    <?php if (!$rawSlug): ?>
        <div class="fav-no-slug">
            <div class="empty-icon">🔒</div>
            <p style="font-size:1.1rem;font-weight:700;color:#333;margin-bottom:8px" data-i18n="fav.noAccess">ไม่มีสิทธิ์เข้าใช้งาน</p>
            <p data-i18n="fav.noAccessDesc">หน้านี้ต้องการ URL เฉพาะตัว กรุณาใช้ลิงก์ที่บันทึกไว้</p>
            <a href="<?= get_base_path() ?>/" class="btn btn-primary" style="margin-top:12px" data-i18n="nav.home">กลับหน้าแรก</a>
        </div>

    <?php elseif (!$parsed || $expired): ?>
        <div class="fav-error-box">
            <h2 data-i18n="<?= $expired ? 'fav.expired.title' : 'fav.badUrl.title' ?>">
                <?= $expired ? '⏱️ Favorites หมดอายุหรือไม่พบ' : '❌ URL ไม่ถูกต้อง' ?>
            </h2>
            <p data-i18n="<?= $expired ? 'fav.expired.text' : 'fav.badUrl.text' ?>">
                <?= $expired
                    ? 'Favorites นี้ถูกลบเนื่องจากไม่มีการใช้งานเกิน 365 วัน'
                    : 'URL ไม่ถูกต้องหรือ HMAC ไม่ตรงกัน' ?>
            </p>
            <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:4px">
                <button class="btn" onclick="clearFavSlug()" data-i18n="fav.clearStorage">🗑️ ล้างออกจาก Browser</button>
                <button class="btn btn-primary" onclick="createNewFav('my-favorites')" data-i18n="fav.newFav">✨ สร้าง Favorites ใหม่</button>
            </div>
        </div>

    <?php else: ?>

        <!-- Save URL Banner -->
        <div class="fav-save-banner">
            <div class="warn-label" data-i18n="fav.saveBanner">⚠️ บันทึก URL นี้ไว้ หากหายไม่สามารถกู้คืนได้</div>
            <div class="fav-url-row">
                <input type="text" readonly id="favDashUrl" class="fav-url-input"
                       value="<?= htmlspecialchars($dashboardUrl) ?>" onclick="this.select()">
                <button class="btn" onclick="copyFavUrl()" id="copyUrlBtn" data-i18n="fav.copyUrl">📋 Copy URL</button>
                <?php if ($feedUrl): ?>
                <button class="btn btn-subscribe" onclick="openFavSubscribeModal()" data-i18n="button.subscribe">🔔 Subscribe</button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Action Chip Bar: QR Transfer + Telegram + Web Push -->
        <div class="fav-actions-bar">
            <button class="fav-action-chip" id="qrChip" type="button" onclick="openQrTransferModal()">
                <span class="chip-icon">🔗</span>
                <span class="chip-label" data-i18n="actions.qrTransfer">QR Transfer</span>
            </button>
            <?php if (telegram_is_enabled() && $slug): ?>
            <button class="fav-action-chip <?= $telegramLinked ? 'is-active' : '' ?>" id="telegramChip" type="button" onclick="handleTelegramChip()">
                <span class="chip-icon">✈️</span>
                <span class="chip-label" data-i18n="actions.telegram">Telegram</span>
                <span class="chip-dot" aria-hidden="true"></span>
            </button>
            <?php endif; ?>
            <?php if ($webpushEnabled && !empty($vapidPublicKey) && $slug): ?>
            <button class="fav-action-chip" id="pushChip" type="button" onclick="handlePushChip()">
                <span class="chip-icon">🔔</span>
                <span class="chip-label" data-i18n="actions.push">Push</span>
                <span class="chip-dot" aria-hidden="true"></span>
            </button>
            <?php endif; ?>
        </div>

        <?php if ($slug && (telegram_is_enabled() || ($webpushEnabled && !empty($vapidPublicKey)))): ?>
        <!-- Timezone picker: controls the local time shown in Telegram + Web Push notifications -->
        <div class="fav-tz-row">
            <label for="favTzSelect" class="fav-tz-label">🕐 <span data-i18n="tz.settingLabel">เขตเวลาแจ้งเตือน</span></label>
            <select id="favTzSelect" class="fav-tz-select" onchange="handleTzChange()">
                <option value="auto" data-i18n="tz.auto"<?= $userTzManual ? '' : ' selected' ?>>อัตโนมัติ</option>
                <optgroup label="🌏 Asia">
                    <option value="Asia/Bangkok">Asia/Bangkok (UTC+7)</option>
                    <option value="Asia/Tokyo">Asia/Tokyo (UTC+9)</option>
                    <option value="Asia/Seoul">Asia/Seoul (UTC+9)</option>
                    <option value="Asia/Singapore">Asia/Singapore (UTC+8)</option>
                    <option value="Asia/Shanghai">Asia/Shanghai (UTC+8)</option>
                    <option value="Asia/Taipei">Asia/Taipei (UTC+8)</option>
                    <option value="Asia/Kolkata">Asia/Kolkata (UTC+5:30)</option>
                    <option value="Asia/Dubai">Asia/Dubai (UTC+4)</option>
                </optgroup>
                <optgroup label="🌍 Europe / Africa">
                    <option value="UTC">UTC (UTC+0)</option>
                    <option value="Europe/London">Europe/London (UTC+0/+1)</option>
                    <option value="Europe/Paris">Europe/Paris (UTC+1/+2)</option>
                    <option value="Europe/Berlin">Europe/Berlin (UTC+1/+2)</option>
                </optgroup>
                <optgroup label="🌎 Americas">
                    <option value="America/Los_Angeles">America/Los_Angeles (UTC-8/-7)</option>
                    <option value="America/Chicago">America/Chicago (UTC-6/-5)</option>
                    <option value="America/New_York">America/New_York (UTC-5/-4)</option>
                    <option value="America/Sao_Paulo">America/Sao_Paulo (UTC-3/-2)</option>
                </optgroup>
                <optgroup label="🌊 Pacific">
                    <option value="Pacific/Honolulu">Pacific/Honolulu (UTC-10)</option>
                    <option value="Pacific/Auckland">Pacific/Auckland (UTC+12/+13)</option>
                </optgroup>
            </select>
            <span id="favTzHint" class="fav-tz-hint"></span>
        </div>
        <?php endif; ?>

        <!-- QR Transfer Modal -->
        <div id="qrTransferModal" class="req-modal-overlay" style="display:none;">
            <div class="req-modal" style="max-width:380px;">
                <div class="req-modal-header">
                    <h2 data-i18n="transfer.modalTitle">🔗 ย้าย Favorites ไปยังอุปกรณ์อื่น</h2>
                    <button onclick="closeQrTransferModal()" class="req-close">&times;</button>
                </div>
                <div class="req-modal-body" style="text-align:center;">
                    <p class="fav-qr-desc" data-i18n="transfer.desc">แสดง QR Code นี้บนหน้าจอนี้ แล้วสแกนจากอุปกรณ์ปลายทาง (เช่น PWA บนมือถือ)</p>
                    <div id="qrCodeContainer" class="fav-qr-container"></div>
                    <ol class="fav-transfer-steps" style="text-align:left;">
                        <li data-i18n="transfer.step1">บนอุปกรณ์ปลายทาง: เปิด PWA หรือ browser</li>
                        <li data-i18n="transfer.step2">กดปุ่ม 🔗 ในหัวเว็บ → เปิดกล้องสแกน QR Code นี้</li>
                        <li data-i18n="transfer.step3">Favorites ของคุณจะถูกบันทึกในอุปกรณ์ปลายทางทันที</li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- Telegram Link Modal -->
        <?php if (telegram_is_enabled() && $slug): ?>
        <div id="telegramLinkModal" class="req-modal-overlay" style="display:none;">
            <div class="req-modal" style="max-width:480px;">
                <div class="req-modal-header">
                    <h2 data-i18n="tg.linkTitle">🔔 เชื่อมต่อ Telegram</h2>
                    <button onclick="closeTelegramLinkModal()" class="req-close">&times;</button>
                </div>
                <div class="req-modal-body">
                    <p style="margin:0 0 16px;color:#555;font-size:0.95em;" data-i18n="tg.desc">เชื่อมต่อ Telegram เพื่อรับการแจ้งเตือนก่อนเริ่มโปรแกรมของศิลปินที่ติดตาม</p>

                    <!-- OPTION 1: Open Telegram Button (Primary) -->
                    <div style="margin-bottom:16px;">
                        <p style="font-size:0.85em;color:#666;margin:0 0 8px;font-weight:500;">✅ <span data-i18n="tg.option1">วิธีที่ 1: เปิด Telegram</span></p>
                        <a id="telegramBotLink" href="https://t.me/<?= htmlspecialchars(TELEGRAM_BOT_USERNAME) ?>?start=<?= urlencode($slug) ?>" class="btn btn-primary"
                           style="width:100%;text-align:center;text-decoration:none;display:flex;align-items:center;justify-content:center;font-size:1em;"
                           target="_blank" rel="noopener">
                           🔗 <span data-i18n="tg.openTelegram">เปิด Telegram</span>
                        </a>
                        <div style="padding:8px 10px;background:#e8f5e9;border-radius:6px;border-left:3px solid #4caf50;margin-top:8px;">
                            <p style="margin:0;font-size:0.78em;color:#2e7d32;line-height:1.5;">
                                ℹ️ <span data-i18n="tg.info1">หลังจากเปิด Telegram ให้เลือกภาษาแล้วส่งคำสั่ง ระบบจะยืนยันการเชื่อมต่ออัตโนมัติ</span>
                            </p>
                        </div>
                    </div>

                    <!-- DIVIDER -->
                    <div style="display:flex;align-items:center;margin:20px 0;gap:10px;">
                        <div style="flex:1;height:1px;background:#ddd;"></div>
                        <span style="color:#999;font-size:0.85em;font-weight:500;" data-i18n="tg.or">หรือ</span>
                        <div style="flex:1;height:1px;background:#ddd;"></div>
                    </div>

                    <!-- OPTION 2: Manual Fallback -->
                    <div>
                        <p style="font-size:0.85em;color:#666;margin:0 0 10px;font-weight:500;">📋 <span data-i18n="tg.option2">วิธีที่ 2: ค้นหาและส่งคำสั่งด้วยมือ</span></p>
                        <ol style="margin:0 0 12px;padding-left:20px;color:#666;font-size:0.85em;line-height:1.7;">
                            <li><span data-i18n="tg.step1">เปิด Telegram</span></li>
                            <li><span data-i18n="tg.step2">ค้นหาบอท</span> <strong>@<?= htmlspecialchars(TELEGRAM_BOT_USERNAME) ?></strong></li>
                            <li style="margin-bottom:6px;"><span data-i18n="tg.step3">คัดลอกและส่งคำสั่ง:</span>
                                <div style="display:flex;gap:8px;margin-top:6px;align-items:stretch;">
                                    <input type="text" readonly id="telegramStartCmd" class="fav-url-input"
                                           value="/start <?= htmlspecialchars($slug) ?>" onclick="this.select();"
                                           style="flex:1;padding:8px 12px;border:1px solid #ddd;border-radius:6px;font-family:monospace;font-size:0.85em;background:#fff;">
                                    <button class="btn" onclick="copyTelegramCommand()" style="white-space:nowrap;" data-i18n="tg.copyCommand">📋 Copy</button>
                                </div>
                            </li>
                            <li><span data-i18n="tg.step4">เลือกภาษา แล้วรับการแจ้งเตือนอัตโนมัติ</span></li>
                        </ol>
                        <div style="padding:8px 10px;background:#fff3e0;border-radius:6px;border-left:3px solid #ff9800;">
                            <p style="margin:0;font-size:0.78em;color:#e65100;line-height:1.5;">
                                ⏱️ <span data-i18n="tg.info2">ให้ระบบประมวลผล 30 วินาที หลังจากส่งคำสั่ง</span>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="req-modal-footer">
                    <button onclick="closeTelegramLinkModal()" class="btn btn-secondary" data-i18n="modal.cancel">ปิด</button>
                    <button onclick="verifyTelegramLink()" class="btn btn-primary" data-i18n="tg.verifyButton">✅ ยืนยันแล้ว</button>
                </div>
            </div>
        </div>
        <?php endif; ?>


        <!-- Followed Artists (Stats line + Collapsible chips) -->
        <div class="fav-followed-section">
            <button class="fav-followed-toggle" type="button"
                    <?php if (empty($artistIds)): ?>disabled<?php endif; ?>
                    <?php if (!empty($artistIds)): ?>onclick="toggleFollowedSection(this)"<?php endif; ?>>
                <span class="fav-followed-stats">
                    <span class="stats-icon">👥</span>
                    <span data-i18n="fav.statsFollowing">ติดตาม</span>
                    <strong><?= count($artistIds) ?></strong>
                    <span data-i18n="fav.statsArtists">ศิลปิน</span>
                    <?php if (!empty($artistIds)): ?>
                    · <strong><?= $totalPrograms ?></strong>
                    <span data-i18n="fav.statsPrograms">upcoming programs</span>
                    <?php endif; ?>
                </span>
                <?php if (!empty($artistIds)): ?>
                <span class="fav-followed-caret" aria-hidden="true">▼</span>
                <?php endif; ?>
            </button>

            <?php if (empty($artistIds)): ?>
            <p class="fav-empty" data-i18n="fav.noArtists">ยังไม่มีศิลปินที่ติดตาม — ไปที่หน้าโปรไฟล์ศิลปินแล้วกด ☆ ติดตาม</p>
            <?php else: ?>
            <div class="fav-followed-content" id="followedContent" style="display:none">
                <div class="fav-artist-chips" id="artistChips">
                    <?php foreach ($artistIds as $aid):
                        $a = $artistsMap[$aid] ?? null; if (!$a) continue; ?>
                    <span class="fav-artist-chip" id="chip-<?= (int)$aid ?>">
                        <?= $a['is_group'] ? '🎵' : '🎤' ?>
                        <a href="<?= get_base_path() ?>/artist/<?= (int)$aid ?>"><?= htmlspecialchars($a['name']) ?></a>
                        <button class="fav-unfollow-btn" onclick="unfollowArtist(<?= (int)$aid ?>)"
                                data-i18n-title="fav.unfollow" title="เลิกติดตาม">×</button>
                    </span>
                    <?php endforeach; ?>
                </div>
                <a href="<?= get_base_path() ?>/my-favorites/<?= htmlspecialchars($slug) ?>"
                   class="fav-manage-link" data-i18n="fav.manageAll">→ จัดการทั้งหมด</a>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($byDate)): ?>
        <!-- Mini Calendar -->
        <div class="fav-cal-wrap">
            <div class="fav-cal-header">
                <button class="fav-cal-nav" id="calPrevBtn" onclick="favCalNav(-1)" aria-label="Previous month">◀</button>
                <span class="fav-cal-title" id="calTitle"></span>
                <button class="fav-cal-nav" id="calNextBtn" onclick="favCalNav(1)" aria-label="Next month">▶</button>
            </div>
            <div class="fav-cal-grid" id="favCalGrid"></div>
        </div>
        <?php endif; ?>

        <!-- Upcoming Programs -->
        <div class="fav-section">
            <div class="fav-section-header">
                <h2 data-i18n="fav.upcoming">📅 Upcoming Programs</h2>
                <?php if (!empty($byDate)): ?>
                <div class="fav-view-toggle" role="tablist" aria-label="View mode">
                    <button class="fav-vt-btn is-active" type="button" data-mode="list" onclick="setFavView('list')" data-i18n="fav.view.list">📋 List</button>
                    <button class="fav-vt-btn" type="button" data-mode="timeline" onclick="setFavView('timeline')" data-i18n="fav.view.timeline">📊 Timeline</button>
                </div>
                <?php endif; ?>
            </div>
            <?php if (empty($byDate)): ?>
            <p class="fav-no-programs" data-i18n="fav.noPrograms">ไม่มี program ที่กำลังจะมาถึงจากศิลปินที่ติดตาม</p>
            <?php else: ?>
            <div id="favListView">
                <?php foreach ($byDate as $date => $progs): ?>
                <div class="fav-date-header" data-date="<?= htmlspecialchars($date) ?>"></div>
                <?php foreach ($progs as $p):
                    $tStart  = substr($p['start_date'], 11, 5);
                    $tEnd    = substr($p['end_date'],   11, 5);
                    $timeStr = ($tStart === $tEnd || !$tEnd || $tEnd === '00:00') ? $tStart : $tStart . '–' . $tEnd;
                    // ISO-8601 with explicit offset so JS Date parses correctly across event timezones
                    $_pTzName = $p['event_timezone'] ?: DEFAULT_TIMEZONE;
                    try { $_pTz = new DateTimeZone($_pTzName); }
                    catch (Exception $e) { $_pTz = new DateTimeZone(DEFAULT_TIMEZONE); $_pTzName = DEFAULT_TIMEZONE; }
                    $_pStartIso = (new DateTime($p['start_date'], $_pTz))->format('c');
                    $_pEndIso   = (new DateTime($p['end_date'],   $_pTz))->format('c');
                ?>
                <div class="fav-program-row fav-ec-<?= $eventColorMap[(int)$p['event_id']] ?? 0 ?>"
                     data-start="<?= htmlspecialchars($_pStartIso) ?>"
                     data-end="<?= htmlspecialchars($_pEndIso) ?>"
                     data-event-tz="<?= htmlspecialchars($_pTzName) ?>">
                    <div class="fav-time"><?= htmlspecialchars($timeStr) ?></div>
                    <div class="fav-prog-body">
                        <div class="fav-prog-title">
                            <?= htmlspecialchars($p['title']) ?>
                            <?php if ($p['program_type']): ?>
                            <span class="fav-type-badge"><?= htmlspecialchars($p['program_type']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="fav-prog-meta">
                            🎪 <a href="<?= get_base_path() ?>/event/<?= htmlspecialchars($p['event_slug']) ?>" style="color:inherit;text-decoration:none;"><?= htmlspecialchars($p['event_name']) ?></a>
                            <?php if ($p['location']): ?>&nbsp;· 📍 <?= htmlspecialchars($p['location']) ?><?php endif; ?>
                            <?php if ($p['categories']): ?>&nbsp;· 🎤 <?= htmlspecialchars($p['categories']) ?><?php endif; ?>
                            <?php if ($p['stream_url']): ?>
                            &nbsp;<a href="<?= htmlspecialchars($p['stream_url']) ?>" target="_blank" rel="noopener" class="fav-stream-btn">🔴 Live</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
            <div id="favTimelineView" style="display:none"></div>
            <?php endif; ?>
        </div>

    <?php endif; ?>
    </div>

    <footer>
        <div class="footer-text">
            <p data-i18n="footer.madeWith">สร้างด้วย ❤️ เพื่อแฟนไอดอล</p>
            <p data-i18n="footer.copyright">© 2026 Idol Stage Timetable. All rights reserved.</p>
            <p>Powered by <a href="https://github.com/fordantitrust/stage-idol-calendar" target="_blank">Stage Idol Calendar</a> <span class="footer-version">v<?php echo APP_VERSION; ?></span></p>
        </div>
    </footer>
</div>

<!-- Personal Feed Subscribe Modal -->
<div id="subscribeModal" class="req-modal-overlay">
    <div class="req-modal" style="max-width:480px;">
        <div class="req-modal-header">
            <h2 data-i18n="subscribe.title">🔔 Subscribe to Calendar</h2>
            <button onclick="closeSubscribeModal()" class="req-close">&times;</button>
        </div>
        <div class="req-modal-body">
            <p style="margin:0 0 12px;color:#555;" data-i18n="subscribe.desc">Subscribe ครั้งเดียว ปฏิทินของคุณจะอัปเดตอัตโนมัติเมื่อมีการเพิ่ม/แก้ไข program</p>

            <a id="subscribeWebcalLink" href="#" class="btn btn-subscribe"
               style="display:block;text-align:center;text-decoration:none;margin-bottom:4px;"
               data-i18n="subscribe.openApp">🔗 เปิดใน Calendar App (webcal://)</a>
            <p style="font-size:0.75em;color:#999;margin:0 0 14px;text-align:center;"
               data-i18n="subscribe.webcalHint">🍎 Apple Calendar · 📱 iOS · 🦅 Thunderbird</p>

            <p style="font-size:0.85em;color:#666;margin:0 0 6px;font-weight:500;"
               data-i18n="subscribe.orCopy">หรือ copy URL สำหรับ Google Calendar / Outlook:</p>
            <div style="display:flex;gap:8px;align-items:center;min-width:0;">
                <input id="subscribeFeedUrl" type="text" readonly
                    style="flex:1;min-width:0;font-size:1rem;padding:7px 10px;border:1px solid #ddd;border-radius:6px;background:#f9f9f9;color:#333;overflow:hidden;text-overflow:ellipsis;">
                <button onclick="copyFeedUrl()" class="btn btn-secondary"
                    style="white-space:nowrap;flex-shrink:0;width:auto;padding:8px 14px;"
                    data-i18n="subscribe.copy">📋 Copy</button>
            </div>
            <p id="subscribeCopied" style="display:none;color:#388e3c;font-size:0.85em;margin:6px 0 0;"
               data-i18n="subscribe.copied">✅ Copy แล้ว!</p>

            <div style="margin-top:14px;padding:10px 12px;background:#f0f4ff;border-radius:8px;border-left:3px solid #4a6cf7;">
                <p style="margin:0 0 4px;font-size:0.82em;font-weight:600;color:#4a6cf7;" data-i18n="subscribe.outlookTitle">📧 Microsoft Outlook</p>
                <p style="margin:0;font-size:0.78em;color:#555;line-height:1.5;" data-i18n="subscribe.outlookHint">Copy URL ด้านบน → เปิด Outlook → Calendar → Add calendar → Subscribe from web → วาง URL</p>
            </div>

            <div style="margin-top:12px;padding:10px 12px;background:#fffbf0;border-radius:8px;border-left:3px solid #f59e0b;">
                <p style="margin:0 0 6px;font-size:0.82em;font-weight:600;color:#92400e;" data-i18n="subscribe.syncTitle">⏱ รอบการอัปเดตของแต่ละบริการ</p>
                <ul style="margin:0;padding-left:16px;font-size:0.76em;color:#555;line-height:1.7;">
                    <li data-i18n="subscribe.syncApple">🍎 Apple Calendar / iOS — ~1 ชั่วโมง</li>
                    <li data-i18n="subscribe.syncGoogle">🌐 Google Calendar — ~24 ชั่วโมง</li>
                    <li data-i18n="subscribe.syncOutlookDesktop">📧 Outlook Desktop — ~24 ชั่วโมง</li>
                    <li data-i18n="subscribe.syncThunderbird">🦅 Thunderbird — ~1 ชั่วโมง</li>
                </ul>
            </div>
        </div>
        <div class="req-modal-footer">
            <button onclick="closeSubscribeModal()" class="btn btn-secondary" data-i18n="modal.cancel">ยกเลิก</button>
        </div>
    </div>
</div>

<!-- Day Programs Modal -->
<div class="fav-day-overlay" id="favDayOverlay" onclick="closeDayModal(event)">
    <div class="fav-day-modal" role="dialog" aria-modal="true">
        <div class="fav-day-modal-header">
            <span class="fav-day-modal-title" id="favDayModalTitle"></span>
            <button class="fav-day-modal-close" onclick="closeDayModal()" aria-label="Close">×</button>
        </div>
        <div class="fav-day-modal-body" id="favDayModalBody"></div>
    </div>
</div>

<script>
const BASE_PATH      = <?= json_encode(get_base_path()) ?>;
window.SITE_TITLE    = <?= json_encode($siteTitle) ?>;
window.FAV_SLUG      = <?= json_encode($slug) ?>;
window.VAPID_PUBLIC_KEY  = <?= json_encode($vapidPublicKey) ?>;
window.WEBPUSH_ENABLED   = <?= json_encode($webpushEnabled) ?>;
window.USER_TZ           = <?= json_encode($userTimezone) ?>;
window.USER_TZ_MANUAL    = <?= json_encode($userTzManual) ?>;
const FAV_FEED_URL   = <?= json_encode($feedUrl) ?>;
const MY_PROGRAMS       = <?= json_encode($calPrograms, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
const EVENT_COLOR_MAP   = <?= json_encode($eventSlugColorMap) ?>;
</script>
<script src="<?= asset_url('js/translations.js') ?>"></script>
<script src="<?= asset_url('js/common.js') ?>"></script>
<script>
const FAV_MONTHS = {
    th: ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'],
    en: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
    ja: ['1月','2月','3月','4月','5月','6月','7月','8月','9月','10月','11月','12月']
};
const FAV_MONTHS_LONG = {
    th: ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'],
    en: ['January','February','March','April','May','June','July','August','September','October','November','December'],
    ja: ['1月','2月','3月','4月','5月','6月','7月','8月','9月','10月','11月','12月']
};
const FAV_DAYS = {
    th: ['อา','จ','อ','พ','พฤ','ศ','ส'],
    en: ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'],
    ja: ['日','月','火','水','木','金','土']
};

// ── Personal Feed Subscribe Modal ─────────────────────────────────────────────
function openFavSubscribeModal() {
    if (!FAV_FEED_URL) return;
    var webcalUrl = FAV_FEED_URL.replace(/^https?:\/\//, 'webcal://');
    document.getElementById('subscribeWebcalLink').href = webcalUrl;
    document.getElementById('subscribeFeedUrl').value = FAV_FEED_URL;
    document.getElementById('subscribeCopied').style.display = 'none';
    document.getElementById('subscribeModal').classList.add('active');
}

// closeSubscribeModal and copyFeedUrl are defined in common.js

// ── Date formatting ───────────────────────────────────────────────────────────
function formatFavDates(lang) {
    const months = FAV_MONTHS[lang] || FAV_MONTHS.th;
    const days   = FAV_DAYS[lang]   || FAV_DAYS.th;
    document.querySelectorAll('.fav-date-header[data-date]').forEach(function(el) {
        const d = new Date(el.dataset.date + 'T00:00:00');
        el.textContent = d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear() + ' (' + days[d.getDay()] + ')';
    });
}

function updateFavTitles(lang) {
    document.querySelectorAll('[data-i18n-title]').forEach(function(el) {
        const key = el.getAttribute('data-i18n-title');
        if (translations[lang] && translations[lang][key]) el.title = translations[lang][key];
    });
}

// ── Mini Calendar ─────────────────────────────────────────────────────────────
// Build sorted list of months that have programs
const _calDates = Object.keys(MY_PROGRAMS).sort();
const _calMonthSet = {};
_calDates.forEach(function(d) { _calMonthSet[d.slice(0,7)] = true; });
const CAL_MONTHS = Object.keys(_calMonthSet).sort(); // ['YYYY-MM', ...]

let _calIdx = 0; // index into CAL_MONTHS

function _calYM() {
    const parts = (CAL_MONTHS[_calIdx] || '').split('-');
    return { year: parseInt(parts[0]), month: parseInt(parts[1]) - 1 };
}

function renderFavCal(lang) {
    const grid  = document.getElementById('favCalGrid');
    const title = document.getElementById('calTitle');
    const prevBtn = document.getElementById('calPrevBtn');
    const nextBtn = document.getElementById('calNextBtn');
    if (!grid) return;

    const { year, month } = _calYM();
    const months = FAV_MONTHS_LONG[lang] || FAV_MONTHS_LONG.th;
    const days   = FAV_DAYS[lang] || FAV_DAYS.th;
    const todayStr = new Date().toISOString().slice(0,10);

    if (title) title.textContent = months[month] + ' ' + year;
    if (prevBtn) prevBtn.disabled = (_calIdx <= 0);
    if (nextBtn) nextBtn.disabled = (_calIdx >= CAL_MONTHS.length - 1);

    // Day-of-week headers
    let html = '';
    for (let i = 0; i < 7; i++) {
        html += '<div class="fav-cal-dow">' + days[i] + '</div>';
    }

    // First cell offset (0=Sun)
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    // Leading blanks
    for (let i = 0; i < firstDay; i++) {
        html += '<div class="fav-cal-day other-month"></div>';
    }

    // Day cells
    for (let d = 1; d <= daysInMonth; d++) {
        const mm    = String(month + 1).padStart(2, '0');
        const dd    = String(d).padStart(2, '0');
        const dateStr = year + '-' + mm + '-' + dd;
        const hasP  = !!MY_PROGRAMS[dateStr];
        const isToday = dateStr === todayStr;

        let cls = 'fav-cal-day';
        if (hasP)    cls += ' has-programs';
        if (isToday) cls += ' today';

        const onclick = hasP ? ' onclick="openDayModal(\'' + dateStr + '\')"' : '';
        html += '<div class="' + cls + '"' + onclick + '>';
        html += '<span class="fav-cal-day-num">' + d + '</span>';
        if (hasP) html += '<span class="fav-cal-dot"></span>';
        html += '</div>';
    }

    // Trailing blanks to complete last row
    const total = firstDay + daysInMonth;
    const trailing = (7 - (total % 7)) % 7;
    for (let i = 0; i < trailing; i++) {
        html += '<div class="fav-cal-day other-month"></div>';
    }

    grid.innerHTML = html;
}

function favCalNav(dir) {
    _calIdx = Math.max(0, Math.min(CAL_MONTHS.length - 1, _calIdx + dir));
    renderFavCal(currentLang);
}

// ── Day Modal ─────────────────────────────────────────────────────────────────
function openDayModal(dateStr) {
    const progs = MY_PROGRAMS[dateStr];
    if (!progs || !progs.length) return;

    const d = new Date(dateStr + 'T00:00:00');
    const months = FAV_MONTHS[currentLang] || FAV_MONTHS.th;
    const days   = FAV_DAYS[currentLang]   || FAV_DAYS.th;
    const dateLabel = d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear() + ' (' + days[d.getDay()] + ')';

    const titleEl = document.getElementById('favDayModalTitle');
    const bodyEl  = document.getElementById('favDayModalBody');
    if (titleEl) titleEl.textContent = dateLabel;
    if (bodyEl) {
        let html = '';
        progs.forEach(function(p) {
            var ec = (EVENT_COLOR_MAP && EVENT_COLOR_MAP[p.event_slug] !== undefined) ? ' fav-ec-' + EVENT_COLOR_MAP[p.event_slug] : '';
            var dsAttrs = '';
            if (p.start_full) dsAttrs += ' data-start="' + _esc(p.start_full) + '"';
            if (p.end_full)   dsAttrs += ' data-end="'   + _esc(p.end_full)   + '"';
            if (p.event_tz)   dsAttrs += ' data-event-tz="' + _esc(p.event_tz) + '"';
            html += '<div class="fav-program-row' + ec + '"' + dsAttrs + '>';
            html += '<div class="fav-time">' + _esc(p.time) + '</div>';
            html += '<div class="fav-prog-body">';
            html += '<div class="fav-prog-title">' + _esc(p.title);
            if (p.program_type) html += '<span class="fav-type-badge">' + _esc(p.program_type) + '</span>';
            html += '</div>';
            html += '<div class="fav-prog-meta">';
            html += '🎪 <a href="' + BASE_PATH + '/event/' + _esc(p.event_slug) + '" style="color:inherit;text-decoration:none;">' + _esc(p.event_name) + '</a>';
            if (p.location)   html += '&nbsp;· 📍 ' + _esc(p.location);
            if (p.categories) html += '&nbsp;· 🎤 ' + _esc(p.categories);
            if (p.stream_url) html += '&nbsp;<a href="' + _esc(p.stream_url) + '" target="_blank" rel="noopener" class="fav-stream-btn">🔴 Live</a>';
            html += '</div></div></div>';
        });
        bodyEl.innerHTML = html;
    }

    const overlay = document.getElementById('favDayOverlay');
    if (overlay) overlay.classList.add('open');
    // Annotate cross-TZ rows just added to the modal
    _favAnnotateTimezones();
}

function closeDayModal(e) {
    if (e && e.target !== document.getElementById('favDayOverlay')) return;
    const overlay = document.getElementById('favDayOverlay');
    if (overlay) overlay.classList.remove('open');
}

function _esc(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const overlay = document.getElementById('favDayOverlay');
        if (overlay && overlay.classList.contains('open')) overlay.classList.remove('open');
    }
});

// ── Now Playing Highlight ─────────────────────────────────────────────────────
function highlightNowPlaying() {
    const now = new Date();
    document.querySelectorAll('.fav-program-row[data-start]').forEach(function(row) {
        // data-start / data-end are ISO-8601 with explicit timezone offset
        // (e.g. "2026-05-29T18:00:00+08:00") — JS Date parses these to the correct UTC instant
        // regardless of the browser's timezone, so the "now playing" comparison works for
        // followed events across multiple timezones (v16.0.3+).
        const start = new Date(row.dataset.start);
        const endRaw = row.dataset.end || '';
        const end   = endRaw ? new Date(endRaw) : null;

        // "now playing" = started AND (no end, or end is in the future)
        const started  = !isNaN(start) && start <= now;
        const notEnded = !end || isNaN(end) || end > now;
        const isNow    = started && notEnded;

        row.classList.toggle('fav-now-playing', isNow);

        // Add/remove pulsing badge next to time
        const timeEl = row.querySelector('.fav-time');
        if (timeEl) {
            const existing = timeEl.querySelector('.fav-now-badge');
            if (isNow && !existing) {
                const badge = document.createElement('span');
                badge.className = 'fav-now-badge';
                badge.textContent = 'NOW';
                timeEl.appendChild(badge);
            } else if (!isNow && existing) {
                existing.remove();
            }
        }
    });
}

// ── Cross-Timezone Annotation (v16.0.4+) ─────────────────────────────────────
// Followed events may span multiple timezones (e.g. Taipei + Bangkok + Tokyo). Each row
// displays the event-local time (e.g. "18:20" for Taipei); if the user's browser is in
// a different TZ we append "(local HH:MM)" + a small TZ chip so the displayed event-local
// time can't be mistaken for the user's wall-clock time.
function _favAnnotateTimezones() {
    if (typeof Intl === 'undefined') return;
    let userTz;
    try { userTz = Intl.DateTimeFormat().resolvedOptions().timeZone; } catch (e) { return; }
    if (!userTz) return;

    const t = (typeof translations !== 'undefined' && translations[currentLang || 'th']) || {};
    const localLabel = t['tz.localTime'] || 'local';

    document.querySelectorAll('.fav-program-row[data-event-tz]').forEach(function (row) {
        const evTz = row.dataset.eventTz;
        if (!evTz || evTz === userTz) return;

        // Time annotation: "(17:20 local)" inside .fav-time
        const dStart = row.dataset.start;
        const dEnd   = row.dataset.end || '';
        if (dStart) {
            const startD = new Date(dStart);
            const endD   = dEnd ? new Date(dEnd) : null;
            if (!isNaN(startD)) {
                const fmt = { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: userTz };
                let localStart, localEnd;
                try {
                    localStart = startD.toLocaleTimeString([], fmt);
                    localEnd   = (endD && !isNaN(endD)) ? endD.toLocaleTimeString([], fmt) : null;
                } catch (e) { localStart = null; }
                if (localStart) {
                    const localRange = (localEnd && localEnd !== localStart) ? (localStart + '–' + localEnd) : localStart;
                    const timeEl = row.querySelector('.fav-time');
                    if (timeEl) {
                        let span = timeEl.querySelector('.fav-time-local');
                        if (!span) {
                            span = document.createElement('span');
                            span.className = 'fav-time-local';
                            timeEl.appendChild(span);
                        }
                        span.textContent = '(' + localRange + ' ' + localLabel + ')';
                    }
                }
            }
        }

        // TZ chip in meta row: "🕐 Asia/Taipei"
        const metaEl = row.querySelector('.fav-prog-meta');
        if (metaEl && !metaEl.querySelector('.fav-tz-chip')) {
            const chip = document.createElement('span');
            chip.className = 'fav-tz-chip';
            chip.textContent = '· 🕐 ' + evTz;
            metaEl.appendChild(chip);
        }
    });
}

// ── Timeline View ─────────────────────────────────────────────────────────────
const FAV_VIEW_KEY = 'fav_view_mode';
let _favTimelineRendered = false;

function _favTimeToMin(t) {
    const parts = String(t || '').split(':');
    return (parseInt(parts[0], 10) || 0) * 60 + (parseInt(parts[1], 10) || 0);
}

// Inverse of _favTimeToMin: minutes-since-midnight → "HH:MM"
function _favFormatMin(min) {
    if (min == null || isNaN(min)) return '';
    const h = Math.floor(min / 60) % 24;
    const m = Math.abs(min) % 60;
    return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
}

// Cached browser timezone (re-resolved each call is cheap, but keep one accessor)
let _favUserTzCache = null;
function _favUserTz() {
    if (_favUserTzCache !== null) return _favUserTzCache;
    try { _favUserTzCache = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; }
    catch (e) { _favUserTzCache = ''; }
    return _favUserTzCache;
}

// Convert an ISO-8601-with-offset string ("2026-05-29T18:00:00+08:00") to user-local
// minutes-since-midnight (HH*60+MM in the browser's timezone). Returns null on failure
// so the caller can fall back to event-local minutes. v16.0.8+ — used to align Timeline
// bars by real-time across mixed-TZ events (so a Taipei 18:00 program and a Bangkok
// 17:00 program — both UTC 10:00 — sit on the same row).
function _favUserLocalMin(isoStr) {
    if (!isoStr) return null;
    const userTz = _favUserTz();
    if (!userTz || typeof Intl === 'undefined') return null;
    try {
        const d = new Date(isoStr);
        if (isNaN(d)) return null;
        const parts = new Intl.DateTimeFormat('en-US', {
            timeZone: userTz, hour: '2-digit', minute: '2-digit', hourCycle: 'h23'
        }).formatToParts(d);
        let h = 0, m = 0;
        for (let i = 0; i < parts.length; i++) {
            if (parts[i].type === 'hour')   h = parseInt(parts[i].value, 10) || 0;
            else if (parts[i].type === 'minute') m = parseInt(parts[i].value, 10) || 0;
        }
        if (h === 24) h = 0;  // some browsers return "24:00" for midnight
        return h * 60 + m;
    } catch (e) { return null; }
}

// Resolve a program to (start, end) minutes-since-midnight in the user's local timezone.
// Caches the result on the program object so overlap-detection + positioning + sort all
// use the same value without recomputing. Falls back to event-local minutes from
// p.start_iso / p.end_iso if the user-local conversion fails (e.g., no Intl, no offset).
function _favProgMins(p) {
    if (p._uStart == null) {
        const uS = _favUserLocalMin(p.start_full);
        p._uStart = (uS != null) ? uS : _favTimeToMin(p.start_iso);
    }
    if (p._uEnd == null) {
        const uE = _favUserLocalMin(p.end_full);
        p._uEnd = (uE != null) ? uE : _favTimeToMin(p.end_iso);
    }
    return { start: p._uStart, end: p._uEnd };
}

function setFavView(mode) {
    const listView = document.getElementById('favListView');
    const tlView   = document.getElementById('favTimelineView');
    if (!listView || !tlView) return;

    if (mode === 'timeline') {
        if (!_favTimelineRendered) {
            renderFavTimeline();
            _favTimelineRendered = true;
        }
        listView.style.display = 'none';
        tlView.style.display   = 'block';
    } else {
        mode = 'list';
        listView.style.display = '';
        tlView.style.display   = 'none';
    }

    document.querySelectorAll('.fav-vt-btn').forEach(function(btn) {
        btn.classList.toggle('is-active', btn.dataset.mode === mode);
    });

    try { localStorage.setItem(FAV_VIEW_KEY, mode); } catch (e) {}
}

function renderFavTimeline() {
    const container = document.getElementById('favTimelineView');
    if (!container) return;
    const dates = Object.keys(MY_PROGRAMS).sort();
    if (!dates.length) { container.innerHTML = ''; return; }

    let html = '';
    dates.forEach(function(date) {
        html += renderFavTimelineDay(date, MY_PROGRAMS[date]);
    });
    container.innerHTML = html;
    formatFavTimelineDates(currentLang);
}

function formatFavTimelineDates(lang) {
    const months = FAV_MONTHS[lang] || FAV_MONTHS.th;
    const days   = FAV_DAYS[lang]   || FAV_DAYS.th;
    const overlapMsg = (translations[lang] && translations[lang]['fav.timeline.overlapHint']) || '🔴 Overlap';
    document.querySelectorAll('.fav-tl-date-header[data-date]').forEach(function(el) {
        const d = new Date(el.dataset.date + 'T00:00:00');
        const label = d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear() + ' (' + days[d.getDay()] + ')';
        const hasOverlap = el.dataset.hasOverlap === '1';
        let html = _esc(label);
        if (hasOverlap) html += ' <span class="fav-tl-overlap-badge">' + _esc(overlapMsg) + '</span>';
        el.innerHTML = html;
    });
}

function _favDetectWithinEventOverlaps(progs) {
    const result = {};
    progs.forEach(function(_, i) {
        result[i] = { hasOverlap: false, stackIndex: 0, stackTotal: 1, overlapsWith: [] };
    });
    for (let i = 0; i < progs.length; i++) {
        for (let j = i + 1; j < progs.length; j++) {
            const mA = _favProgMins(progs[i]); const sA = mA.start, eA = mA.end;
            const mB = _favProgMins(progs[j]); const sB = mB.start, eB = mB.end;
            if (sA < eB && eA > sB) {
                result[i].hasOverlap = true; result[j].hasOverlap = true;
                result[i].overlapsWith.push(j); result[j].overlapsWith.push(i);
            }
        }
    }
    const assigned = new Set();
    progs.forEach(function(_, i) {
        if (result[i].hasOverlap && !assigned.has(i)) {
            const group  = [i].concat(result[i].overlapsWith);
            const unique = Array.from(new Set(group)).sort(function(a, b) { return a - b; });
            unique.forEach(function(idx, k) {
                result[idx].stackIndex = k;
                result[idx].stackTotal = unique.length;
                assigned.add(idx);
            });
        }
    });
    return result;
}

function _favFindCrossEventOverlaps(programs) {
    const sorted = programs.slice().sort(function(a, b) {
        return _favProgMins(a).start - _favProgMins(b).start;
    });
    const zones = [];
    for (let i = 0; i < sorted.length; i++) {
        const aM = _favProgMins(sorted[i]); const aS = aM.start;
        let   aE = aM.end;
        if (aE <= aS) aE = aS + 30;
        for (let j = i + 1; j < sorted.length; j++) {
            const bM = _favProgMins(sorted[j]); const bS = bM.start;
            let   bE = bM.end;
            if (bE <= bS) bE = bS + 30;
            if (bS >= aE) break;
            if (sorted[i].event_slug === sorted[j].event_slug) continue;
            const zS = Math.max(aS, bS), zE = Math.min(aE, bE);
            if (zE > zS) zones.push({ startMin: zS, endMin: zE, slugs: [sorted[i].event_slug, sorted[j].event_slug] });
        }
    }
    return zones;
}

function renderFavTimelineDay(date, programs) {
    if (!programs || !programs.length) return '';

    // Group programs by event_slug; order events by first start time (in USER's local minutes)
    // — so the time-axis is user-local across all events. v16.0.8+.
    const byEvent = {};
    const evFirstStart = {};
    programs.forEach(function(p) {
        const slug = p.event_slug || '_';
        const mins = _favProgMins(p);  // also caches p._uStart / p._uEnd
        if (!byEvent[slug]) {
            byEvent[slug] = { name: p.event_name || slug, slug: slug, tz: p.event_tz || '', programs: [] };
            evFirstStart[slug] = mins.start;
        }
        byEvent[slug].programs.push(p);
    });
    const eventList = Object.keys(byEvent).sort(function(a, b) {
        return evFirstStart[a] - evFirstStart[b];
    });

    // Time range — in user-local minutes
    let minHour = 24, maxHour = 0;
    programs.forEach(function(p) {
        const m = _favProgMins(p);
        const sM = m.start;
        let   eM = m.end;
        if (eM <= sM) eM = sM + 30;
        minHour = Math.min(minHour, Math.floor(sM / 60));
        maxHour = Math.max(maxHour, Math.ceil(eM / 60));
    });
    if (minHour >= 24) minHour = 0;
    if (maxHour <= minHour) maxHour = minHour + 1;
    const startHour  = Math.max(0, minHour);
    const endHour    = Math.min(24, maxHour);
    const totalHours = endHour - startHour;
    const totalMins  = totalHours * 60;

    // Cross-event overlaps
    const crossOverlaps = _favFindCrossEventOverlaps(programs);
    const hasOverlap    = crossOverlaps.length > 0;

    const isMobile      = window.innerWidth <= 768;
    const slotHeight    = isMobile ? 60 : 70;
    const totalHeight   = totalHours * slotHeight;
    const timeAxisWidth = isMobile ? 50 : 60;
    const colWidth      = isMobile ? 140 : 180;
    const minWidth      = timeAxisWidth + eventList.length * colWidth;

    let html = '<div class="fav-timeline-day">';
    html += '<div class="fav-tl-date-header" data-date="' + _esc(date) + '" data-has-overlap="' + (hasOverlap ? '1' : '0') + '"></div>';
    // .fav-tl-chart is the scroll container — must NOT take min-width itself, otherwise the
    // whole container expands past the viewport (the entire timeline card overflows the page,
    // body's overflow-x: hidden then crops it — and the user can't scroll right to see more
    // than 2 events on mobile). The min-width belongs on the INNER content (.fav-tl-header
    // and .fav-tl-body) so the content overflows .fav-tl-chart, triggering its overflow-x:auto.
    html += '<div class="fav-tl-chart">';

    // Header row
    html += '<div class="fav-tl-header" style="min-width:' + minWidth + 'px">';
    const timeLabel = (translations[currentLang] && translations[currentLang]['table.time']) || 'Time';
    html += '<div class="fav-tl-time-label">' + _esc(timeLabel) + '</div>';
    eventList.forEach(function(slug) {
        const ev = byEvent[slug];
        const ec = (EVENT_COLOR_MAP && EVENT_COLOR_MAP[slug] !== undefined) ? EVENT_COLOR_MAP[slug] : 0;
        let tzHint = '';
        if (ev.tz && ev.tz !== _favUserTz()) {
            // Show event TZ when it differs from user's local TZ — header label "Event Name · 🕐 TZ"
            tzHint = '<span class="fav-tl-event-tz">· 🕐 ' + _esc(ev.tz) + '</span>';
        }
        html += '<div class="fav-tl-event-header fav-ec-' + ec + '" title="' + _esc(ev.name + (ev.tz ? ' (' + ev.tz + ')' : '')) + '">' + _esc(ev.name) + tzHint + '</div>';
    });
    html += '</div>';

    // Body
    html += '<div class="fav-tl-body" style="height:' + totalHeight + 'px;min-width:' + minWidth + 'px">';
    html += '<div class="fav-tl-time-axis">';
    for (let h = startHour; h < endHour; h++) {
        html += '<div class="fav-tl-time-slot">' + String(h).padStart(2, '0') + ':00</div>';
    }
    html += '</div>';

    eventList.forEach(function(slug) {
        const ev = byEvent[slug];
        const ec = (EVENT_COLOR_MAP && EVENT_COLOR_MAP[slug] !== undefined) ? EVENT_COLOR_MAP[slug] : 0;
        html += '<div class="fav-tl-event-col" style="height:' + totalHeight + 'px">';
        html += '<div class="fav-tl-grid">';
        for (let h = startHour; h < endHour; h++) html += '<div class="fav-tl-grid-slot"></div>';
        html += '</div>';

        // Overlap zone strips that involve this event
        crossOverlaps.forEach(function(zone) {
            if (zone.slugs.indexOf(slug) === -1) return;
            const topPct = ((zone.startMin - startHour * 60) / totalMins) * 100;
            const hPct   = ((zone.endMin   - zone.startMin) / totalMins) * 100;
            html += '<div class="fav-tl-overlap-zone" style="top:' + topPct.toFixed(2) + '%;height:' + hPct.toFixed(2) + '%"></div>';
        });

        // Bars
        const withinOverlaps = _favDetectWithinEventOverlaps(ev.programs);
        ev.programs.forEach(function(p, idx) {
            const mins = _favProgMins(p);
            const sM = mins.start;
            let   eM = mins.end;
            if (eM <= sM) eM = sM + 30;
            const top    = ((sM - startHour * 60) / totalMins) * 100;
            let   height = ((eM - sM) / totalMins) * 100;
            height = Math.max(height, 3.5);

            const ov = withinOverlaps[idx] || { hasOverlap: false, stackIndex: 0, stackTotal: 1 };
            let stackCls = '';
            let style    = 'top:' + top.toFixed(2) + '%;height:' + height.toFixed(2) + '%;';
            if (ov.hasOverlap) {
                stackCls = ' has-overlap';
                const n = ov.stackTotal, i = ov.stackIndex;
                const leftPct  = (i / n * 100).toFixed(2);
                const rightPct = ((n - i - 1) / n * 100).toFixed(2);
                style += 'left:calc(' + leftPct + '% + 2px);right:calc(' + rightPct + '% + 2px);';
            }

            // Build the primary + secondary time labels (v16.0.9+):
            //   primary  = USER-local time (matches the user-local axis on the left)
            //   secondary (parens, only when event TZ ≠ user TZ) = EVENT-local time
            // (the lane header already shows the event's full timezone name as a chip,
            //  so the parens stays compact — just the alternative HH:MM)
            const userTz   = _favUserTz();
            const isCross  = p.event_tz && userTz && p.event_tz !== userTz;
            const userStartLbl = _favFormatMin(mins.start);
            const userEndLbl   = _favFormatMin(mins.end);
            const userRange    = (userStartLbl === userEndLbl) ? userStartLbl : userStartLbl + '–' + userEndLbl;
            const primaryTime  = isCross ? userRange : (p.time || userRange);
            let   timeSubHtml  = '';
            if (isCross) {
                timeSubHtml = '<div class="fav-tl-bar-time-sub">(' + _esc(p.time) + ')</div>';
            }

            html += '<div class="fav-tl-bar fav-ec-' + ec + stackCls + '" style="' + style + '" onclick="openFavTimelineBar(this)"';
            html += ' data-date="' + _esc(date) + '" data-title="' + _esc(p.title) + '" title="' + _esc(primaryTime + ' ' + p.title + (isCross ? ' [' + p.time + ' ' + p.event_tz + ']' : '')) + '">';
            html += '<div class="fav-tl-bar-time">' + _esc(primaryTime) + '</div>';
            html += timeSubHtml;
            html += '<div class="fav-tl-bar-title">' + _esc(p.title) + '</div>';
            if (p.program_type) html += '<div class="fav-tl-bar-type">' + _esc(p.program_type) + '</div>';
            html += '</div>';
        });
        html += '</div>';
    });

    html += '</div></div></div>';
    return html;
}

function openFavTimelineBar(barEl) {
    const date = barEl && barEl.dataset ? barEl.dataset.date : null;
    if (date) openDayModal(date);
}

// ── Language / init ───────────────────────────────────────────────────────────
(function() {
    const _origCL = window.changeLanguage;
    window.changeLanguage = function(lang) {
        _origCL(lang);
        formatFavDates(lang);
        updateFavTitles(lang);
        renderFavCal(lang);
        if (_favTimelineRendered) renderFavTimeline();
        // Update the "local" label inside (HH:MM local) annotations
        document.querySelectorAll('.fav-time-local').forEach(function(s) { s.remove(); });
        _favAnnotateTimezones();
    };
})();

document.addEventListener('DOMContentLoaded', function() {
    formatFavDates(currentLang);
    updateFavTitles(currentLang);
    if (CAL_MONTHS.length) renderFavCal(currentLang);
    highlightNowPlaying();
    _favAnnotateTimezones();
    // Restore preferred view mode
    try {
        const saved = localStorage.getItem(FAV_VIEW_KEY);
        if (saved === 'timeline' && document.getElementById('favTimelineView')) {
            setFavView('timeline');
        }
    } catch (e) {}
});

// ── Error recovery ────────────────────────────────────────────────────────────
function clearFavSlug() {
    localStorage.removeItem('fav_slug');
    window.location.href = BASE_PATH + '/';
}

async function createNewFav(redirectPage) {
    const btn = event.target;
    btn.disabled = true;
    try {
        const res = await fetch(BASE_PATH + '/api/favorites?action=create', {method:'POST'});
        if (!res.ok) throw new Error('create failed');
        const data = await res.json();
        localStorage.setItem('fav_slug', data.slug);
        window.location.href = BASE_PATH + '/' + redirectPage + '/' + encodeURIComponent(data.slug);
    } catch(e) {
        btn.disabled = false;
        alert((translations[currentLang] && translations[currentLang]['fav.createError']) || 'ไม่สามารถสร้าง Favorites ได้ กรุณาลองใหม่');
    }
}

// ── Auto-save slug ────────────────────────────────────────────────────────────
if (window.FAV_SLUG) {
    const stored = localStorage.getItem('fav_slug');
    if (stored !== window.FAV_SLUG) localStorage.setItem('fav_slug', window.FAV_SLUG);
}

// ── Copy URL ──────────────────────────────────────────────────────────────────
function copyFavUrl() {
    const input = document.getElementById('favDashUrl');
    if (!input) return;
    navigator.clipboard.writeText(input.value).then(function() {
        const btn = document.getElementById('copyUrlBtn');
        if (btn) {
            const orig = btn.textContent;
            btn.textContent = (translations[currentLang] && translations[currentLang]['fav.copied']) || '✅ Copied!';
            setTimeout(function() { btn.textContent = orig; }, 2000);
        }
    }).catch(function() { input.select(); document.execCommand('copy'); });
}

// ── QR Transfer ───────────────────────────────────────────────────────────────
const QR_DASHBOARD_URL = <?= json_encode($dashboardUrl) ?>;

function openQrTransferModal() {
    const modal = document.getElementById('qrTransferModal');
    if (!modal) return;
    modal.style.display = 'flex';
    if (!document.getElementById('qrCodeContainer').innerHTML) {
        loadQrCode();
    }
}

function closeQrTransferModal() {
    const modal = document.getElementById('qrTransferModal');
    if (modal) modal.style.display = 'none';
}

// ── Followed Artists Section Toggle ───────────────────────────────────────────
function toggleFollowedSection(btn) {
    const section = btn.closest('.fav-followed-section');
    if (!section) return;
    const content = section.querySelector('.fav-followed-content');
    if (!content) return;
    const isOpen = section.classList.toggle('is-open');
    content.style.display = isOpen ? 'block' : 'none';
}

// ── Chip Click Handlers ───────────────────────────────────────────────────────
function handleTelegramChip() {
    const chip = document.getElementById('telegramChip');
    if (chip && chip.classList.contains('is-active')) {
        unlinkTelegram();
    } else {
        openTelegramLinkModal();
    }
}

function handlePushChip() {
    const chip = document.getElementById('pushChip');
    if (!chip || chip.disabled) return;
    if (chip.classList.contains('is-active')) {
        handlePushUnsubscribe();
    } else {
        handlePushSubscribe();
    }
}

function loadQrCode() {
    const container = document.getElementById('qrCodeContainer');
    container.innerHTML = '<span class="fav-qr-loading">⏳ กำลังสร้าง QR Code...</span>';
    if (window.QRCode) { renderQrCode(container); return; }
    const script = document.createElement('script');
    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
    script.onload = function () { renderQrCode(container); };
    script.onerror = function () {
        container.innerHTML = '<span class="fav-qr-error">❌ โหลด QR ไม่สำเร็จ กรุณาลองใหม่</span>';
    };
    document.head.appendChild(script);
}

function renderQrCode(container) {
    container.innerHTML = '';
    new QRCode(container, {
        text: QR_DASHBOARD_URL,
        width: 200, height: 200,
        colorDark: '#C2185B',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M,
    });
}

// ── Unfollow ──────────────────────────────────────────────────────────────────
async function unfollowArtist(artistId) {
    const slug = window.FAV_SLUG;
    if (!slug) return;
    const msg = (translations[currentLang] && translations[currentLang]['fav.unfollowConfirm']) || 'เลิกติดตามศิลปินนี้?';
    if (!confirm(msg)) return;
    const res = await fetch(BASE_PATH + '/api/favorites?action=remove&slug=' + encodeURIComponent(slug), {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({artist_id: artistId})
    });
    if (res.ok) {
        const chip = document.getElementById('chip-' + artistId);
        if (chip) chip.remove();
    }
}

// ── Telegram Link Modal ───────────────────────────────────────────────────────
function openTelegramLinkModal() {
    const modal = document.getElementById('telegramLinkModal');
    if (modal) modal.style.display = 'flex';
}

function copyTelegramCommand() {
    const cmd = document.getElementById('telegramStartCmd');
    if (cmd) {
        cmd.select();
        document.execCommand('copy');
        const btn = event.target;
        const originalText = btn.textContent;
        btn.textContent = '✅ Copied!';
        setTimeout(() => {
            btn.textContent = originalText;
        }, 2000);
    }
}

function closeTelegramLinkModal() {
    const modal = document.getElementById('telegramLinkModal');
    if (modal) modal.style.display = 'none';
}


async function verifyTelegramLink() {
    const slug = window.FAV_SLUG;
    if (!slug) {
        alert('No slug');
        return;
    }

    const res = await fetch(BASE_PATH + '/api/favorites?action=get&slug=' + encodeURIComponent(slug));
    if (!res.ok) {
        alert((translations[currentLang] && translations[currentLang]['tg.verifyFailed']) || 'Verification failed');
        return;
    }

    const data = await res.json();
    const hasChat = data.telegram_chat_id || false;

    if (hasChat) {
        closeTelegramLinkModal();
        location.reload();
    } else {
        alert((translations[currentLang] && translations[currentLang]['tg.notLinkedYet']) || 'Please complete the /start command in Telegram first');
    }
}

async function unlinkTelegram() {
    const slug = window.FAV_SLUG;
    if (!slug) return;

    const msg = (translations[currentLang] && translations[currentLang]['tg.unlinkConfirm']) || 'ยกเลิกการเชื่อมต่อ Telegram?';
    if (!confirm(msg)) return;

    const res = await fetch(BASE_PATH + '/api/favorites?action=unlink_telegram&slug=' + encodeURIComponent(slug), {
        method: 'POST'
    });

    if (res.ok) {
        location.reload();
    } else {
        alert((translations[currentLang] && translations[currentLang]['tg.unlinkFailed']) || 'Unlink failed');
    }
}

// ── Timezone picker ───────────────────────────────────────────────────────────
function _favTzMsg(key) {
    return (translations[currentLang] && translations[currentLang][key]) || '';
}

function updateTzHint() {
    const sel  = document.getElementById('favTzSelect');
    const hint = document.getElementById('favTzHint');
    if (!sel || !hint) return;
    const detected = _favUserTz();
    // Show the detected zone on the "auto" option label
    const autoOpt = sel.querySelector('option[value="auto"]');
    if (autoOpt) {
        const base = _favTzMsg('tz.auto') || 'Automatic';
        autoOpt.textContent = detected ? (base + ' (' + detected + ')') : base;
    }
    const eff = (sel.value === 'auto') ? detected : sel.value;
    const tmpl = _favTzMsg('tz.effective') || 'Notifications use: {tz}';
    hint.textContent = tmpl.replace('{tz}', eff || '—');
}

async function handleTzChange() {
    const sel  = document.getElementById('favTzSelect');
    const slug = window.FAV_SLUG;
    if (!sel || !slug) return;
    const val  = sel.value;
    const mode = (val === 'auto') ? 'auto' : 'manual';
    const tz   = (val === 'auto') ? _favUserTz() : val;
    if (!tz) { updateTzHint(); return; }
    try {
        await fetch(BASE_PATH + '/api/favorites?action=set_timezone&slug=' + encodeURIComponent(slug), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tz, mode })
        });
        // Auto mode is now synced; record it so the background sync won't re-post
        if (mode === 'auto') localStorage.setItem('fav_tz_synced', tz);
    } catch (e) { /* non-fatal */ }
    updateTzHint();
}

(function initFavTimezone() {
    const sel = document.getElementById('favTzSelect');
    if (!sel) return;
    // Reflect saved state: manual override selects its zone, else "auto"
    if (window.USER_TZ_MANUAL && window.USER_TZ) {
        sel.value = window.USER_TZ;
        if (sel.value !== window.USER_TZ) sel.value = 'auto'; // zone not in list → fall back
    } else {
        sel.value = 'auto';
    }
    updateTzHint();

    // Background auto-capture: keep user_timezone fresh when not manually overridden
    // and the browser timezone changed (e.g. the user travelled). Server ignores
    // "sync" writes while a manual override is active.
    if (window.FAV_SLUG && !window.USER_TZ_MANUAL) {
        const curTz = _favUserTz();
        if (curTz && localStorage.getItem('fav_tz_synced') !== curTz) {
            fetch(BASE_PATH + '/api/favorites?action=set_timezone&slug=' + encodeURIComponent(window.FAV_SLUG), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ tz: curTz, mode: 'sync' })
            }).then(() => localStorage.setItem('fav_tz_synced', curTz)).catch(() => {});
        }
    }

    // Refresh hint wording on language change
    window.addEventListener('appLangChange', updateTzHint);
})();

// ── Web Push ──────────────────────────────────────────────────────────────────
(function initWebPush() {
    if (!window.WEBPUSH_ENABLED || !window.VAPID_PUBLIC_KEY || !window.FAV_SLUG) return;
    const chip = document.getElementById('pushChip');
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        if (chip) {
            chip.disabled = true;
            chip.title = (translations[currentLang] && translations[currentLang]['webpush.notSupported']) || 'Browser ไม่รองรับ Web Push';
        }
        return;
    }
    checkPushStatus();
})();

async function checkPushStatus() {
    const chip = document.getElementById('pushChip');
    if (!chip) return;

    try {
        const reg = await navigator.serviceWorker.ready;
        const sub = await reg.pushManager.getSubscription();

        if (!sub) {
            chip.classList.remove('is-active');
            return;
        }

        const hash = sub.endpoint ? await sha256hex(sub.endpoint) : '';
        const endpointHash = hash.substring(0, 16);
        const res  = await fetch(BASE_PATH + '/api/push?action=status&slug=' + encodeURIComponent(FAV_SLUG) + '&endpoint_hash=' + encodeURIComponent(endpointHash));
        const data = await res.json();

        chip.classList.toggle('is-active', !!data.subscribed);

        // Refresh this device's timezone if the OS timezone changed since last sync
        // (e.g. the user travelled). Re-posting the same endpoint updates tz + lang.
        if (data.subscribed) {
            const curTz = _favUserTz();
            if (curTz && localStorage.getItem('push_tz') !== curTz) {
                try {
                    await fetch(BASE_PATH + '/api/push?action=subscribe', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            slug: window.FAV_SLUG,
                            subscription: sub.toJSON(),
                            lang: localStorage.getItem('lang') || 'th',
                            tz: curTz
                        })
                    });
                    localStorage.setItem('push_tz', curTz);
                } catch (e) { /* non-fatal */ }
            }
        }
    } catch (e) {
        // Leave chip in default (inactive) state on error
    }
}

async function handlePushSubscribe() {
    const chip = document.getElementById('pushChip');
    if (chip) chip.disabled = true;

    try {
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            alert((translations[currentLang] && translations[currentLang]['webpush.permDenied']) || 'Permission denied. Please allow notifications in your browser settings.');
            if (chip) chip.disabled = false;
            return;
        }

        const reg = await navigator.serviceWorker.ready;
        const sub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(window.VAPID_PUBLIC_KEY)
        });

        const res = await fetch(BASE_PATH + '/api/push?action=subscribe', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                slug: window.FAV_SLUG,
                subscription: sub.toJSON(),
                lang: localStorage.getItem('lang') || 'th',
                tz: _favUserTz()
            })
        });
        const data = await res.json();

        if (data.success) {
            try { localStorage.setItem('push_tz', _favUserTz()); } catch (e) {}
            checkPushStatus();
        } else {
            alert(data.error || 'Subscribe failed');
        }
    } catch (e) {
        alert('Subscribe failed: ' + e.message);
    }
    if (chip) chip.disabled = false;
}

async function handlePushUnsubscribe() {
    const chip = document.getElementById('pushChip');
    const msg = (translations[currentLang] && translations[currentLang]['webpush.unsubscribeConfirm']) || 'ยกเลิก Web Push notifications?';
    if (!confirm(msg)) return;
    if (chip) chip.disabled = true;

    try {
        const reg = await navigator.serviceWorker.ready;
        const sub = await reg.pushManager.getSubscription();
        if (!sub) { checkPushStatus(); return; }

        const endpoint = sub.endpoint;
        await sub.unsubscribe();

        await fetch(BASE_PATH + '/api/push?action=unsubscribe', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ slug: window.FAV_SLUG, endpoint })
        });

        checkPushStatus();
    } catch (e) {
        alert('Unsubscribe failed: ' + e.message);
    }
    if (chip) chip.disabled = false;
}

async function sha256hex(str) {
    const buf = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(str));
    return Array.from(new Uint8Array(buf)).map(b => b.toString(16).padStart(2,'0')).join('');
}
</script>
</body>
</html>
