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

$siteTitle = get_site_title();
$theme     = get_site_theme();

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
                   p.event_id, e.name AS event_name, e.slug AS event_slug
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
        $calPrograms[$date][] = [
            'time'        => $timeStr,
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

        /* ── Telegram Link ─────────────────────────────────────────────── */
        .fav-tg-banner {
            background: linear-gradient(135deg,#e0f2f1,#b2dfdb);
            border: 1px solid #80cbc4; border-left: 4px solid #00897b;
            border-radius: 8px; padding: 14px 16px; margin-bottom: 20px;
        }
        .fav-tg-banner .tg-label { font-size:.85rem; font-weight:600; color:#00695c; margin-bottom:8px; }
        .fav-tg-row { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .fav-tg-status {
            flex:1; display:flex; align-items:center; gap:8px;
            padding:8px 12px; background:#f0f9f8; border-radius:6px;
            border:1px solid #b2dfdb; font-size:.85rem; color:#00695c;
        }
        .fav-tg-status.linked { background:#e8f5e9; border-color:#81c784; color:#2e7d32; }
        .fav-tg-dot { width:8px; height:8px; border-radius:50%; background:#ff9800; animation:pulse-orange 2s ease-in-out infinite; }
        .fav-tg-status.linked .fav-tg-dot { background:#4caf50; animation:none; }
        @keyframes pulse-orange { 0%,100% { opacity:1; } 50% { opacity:.4; } }

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
    </style>
</head>
<body>
<div class="container">
    <header>
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

        <!-- Telegram Link Banner (if enabled) -->
        <?php if (telegram_is_enabled() && $slug): ?>
        <div class="fav-tg-banner">
            <div class="tg-label" data-i18n="tg.linkTitle">🔔 เชื่อมต่อ Telegram</div>
            <div class="fav-tg-row">
                <div class="fav-tg-status <?= $telegramLinked ? 'linked' : '' ?>">
                    <span class="fav-tg-dot"></span>
                    <span data-i18n="<?= $telegramLinked ? 'tg.linked' : 'tg.notLinked' ?>">
                        <?= $telegramLinked ? '✅ เชื่อมต่อแล้ว' : '⚫ ยังไม่เชื่อมต่อ' ?>
                    </span>
                </div>
                <?php if (!$telegramLinked): ?>
                <button class="btn" onclick="openTelegramLinkModal()" data-i18n="tg.linkButton">🔗 Link Telegram</button>
                <?php else: ?>
                <button class="btn btn-danger" onclick="unlinkTelegram()" data-i18n="tg.unlink" style="background:#ffebee;color:#c62828;border:1px solid #ef5350;">❌ ยกเลิก</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

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

        <!-- Followed Artists -->
        <div class="fav-section">
            <h2>
                <span data-i18n="fav.artists">⭐ ศิลปินที่ติดตาม</span> (<?= count($artistIds) ?>)
            </h2>
            <?php if (empty($artistIds)): ?>
            <p class="fav-empty" data-i18n="fav.noArtists">ยังไม่มีศิลปินที่ติดตาม — ไปที่หน้าโปรไฟล์ศิลปินแล้วกด ☆ ติดตาม</p>
            <?php else: ?>
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
            <h2 data-i18n="fav.upcoming">📅 Upcoming Programs</h2>
            <?php if (empty($byDate)): ?>
            <p class="fav-no-programs" data-i18n="fav.noPrograms">ไม่มี program ที่กำลังจะมาถึงจากศิลปินที่ติดตาม</p>
            <?php else: ?>
                <?php foreach ($byDate as $date => $progs): ?>
                <div class="fav-date-header" data-date="<?= htmlspecialchars($date) ?>"></div>
                <?php foreach ($progs as $p):
                    $tStart  = substr($p['start_date'], 11, 5);
                    $tEnd    = substr($p['end_date'],   11, 5);
                    $timeStr = ($tStart === $tEnd || !$tEnd || $tEnd === '00:00') ? $tStart : $tStart . '–' . $tEnd;
                ?>
                <div class="fav-program-row fav-ec-<?= $eventColorMap[(int)$p['event_id']] ?? 0 ?>"
                     data-start="<?= htmlspecialchars($p['start_date']) ?>"
                     data-end="<?= htmlspecialchars($p['end_date']) ?>">
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
const BASE_PATH    = <?= json_encode(get_base_path()) ?>;
window.SITE_TITLE  = <?= json_encode($siteTitle) ?>;
window.FAV_SLUG    = <?= json_encode($slug) ?>;
const FAV_FEED_URL = <?= json_encode($feedUrl) ?>;
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
            html += '<div class="fav-program-row' + ec + '">';
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
        // Parse "YYYY-MM-DD HH:MM:SS" → Date (local time)
        const start = new Date(row.dataset.start.replace(' ', 'T'));
        const endRaw = row.dataset.end || '';
        const end   = endRaw ? new Date(endRaw.replace(' ', 'T')) : null;

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

// ── Language / init ───────────────────────────────────────────────────────────
(function() {
    const _origCL = window.changeLanguage;
    window.changeLanguage = function(lang) {
        _origCL(lang);
        formatFavDates(lang);
        updateFavTitles(lang);
        renderFavCal(lang);
    };
})();

document.addEventListener('DOMContentLoaded', function() {
    formatFavDates(currentLang);
    updateFavTitles(currentLang);
    if (CAL_MONTHS.length) renderFavCal(currentLang);
    highlightNowPlaying();
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
</script>
</body>
</html>
