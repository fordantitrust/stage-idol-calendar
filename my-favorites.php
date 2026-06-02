<?php
/**
 * My Favorites — followed artist list (/my-favorites/{slug})
 * Slug from URL. Auto-saves to localStorage as shortcut helper.
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

$scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host      = get_safe_host();
$base      = get_base_path();
$myFavUrl  = $slug ? $scheme . '://' . $host . $base . '/my-favorites/' . $slug : '';
$dashUrl   = $slug ? $scheme . '://' . $host . $base . '/my/' . $slug : '';

$artistIds  = $favData ? ($favData['artists'] ?? []) : [];
$artistsMap = [];

if (!empty($artistIds)) {
    try {
        $db   = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pls  = implode(',', array_fill(0, count($artistIds), '?'));
        $stmt = $db->prepare("
            SELECT a.id, a.name, a.is_group, a.group_id, g.name AS group_name
            FROM artists a LEFT JOIN artists g ON g.id = a.group_id
            WHERE a.id IN ($pls) ORDER BY a.name
        ");
        $stmt->execute($artistIds);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $artistsMap[(int)$row['id']] = $row;
        }
        $stmt->closeCursor(); $stmt = null;
        $db = null;
    } catch (PDOException $e) { /* continue with empty */ }
}

// Split into solos and groups
$solos  = [];
$groups = [];
foreach ($artistIds as $aid) {
    $a = $artistsMap[$aid] ?? null;
    if (!$a) continue;
    if ($a['is_group']) {
        $groups[(int)$aid] = $a;
    } else {
        $solos[(int)$aid] = $a;
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
    <title>My Favorites - <?= htmlspecialchars($siteTitle) ?></title>
    <?php seo_render_meta(['noindex' => true]); ?>
    <link rel="stylesheet" href="<?= asset_url('styles/common.css') ?>">
    <?php if ($theme !== 'sakura'): ?>
    <link rel="stylesheet" href="<?= asset_url('styles/themes/' . $theme . '.css') ?>">
    <?php endif; ?>
    <style>
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
        .fav-section { margin-bottom:28px; }
        .fav-section-header {
            display:flex; align-items:center; gap:8px;
            margin-bottom:10px; flex-wrap:wrap;
        }
        .fav-section-header h2 {
            font-size:1rem; font-weight:700;
            color:var(--sakura-dark,#e91e63); margin:0; flex:1;
        }
        .fav-sort-bar { display:flex; gap:4px; align-items:center; }
        .fav-sort-bar span { font-size:.75rem; color:#999; margin-right:2px; }
        .btn-sort {
            background:none; border:1px solid #e0e0e0; border-radius:12px;
            padding:2px 10px; font-size:.75rem; cursor:pointer; color:#888;
            white-space:nowrap;
        }
        .btn-sort:hover { border-color:var(--sakura-dark,#e91e63); color:var(--sakura-dark,#e91e63); }
        .btn-sort.active { background:var(--sakura-dark,#e91e63); border-color:var(--sakura-dark,#e91e63); color:#fff; }
        .fav-artist-list { display:flex; flex-direction:column; gap:8px; }
        .fav-artist-card {
            display:flex; align-items:center; gap:12px;
            background:#fff; border:1px solid #f0f0f0;
            border-radius:8px; padding:10px 14px;
        }
        .fav-artist-card:hover { border-color:#f8bbd0; }
        .fac-icon { font-size:1.2rem; flex-shrink:0; }
        .fac-name { flex:1; font-weight:600; font-size:.9rem; color:#333; }
        .fac-name a { color:inherit; text-decoration:none; }
        .fac-name a:hover { color:var(--sakura-dark,#e91e63); }
        .fac-actions { display:flex; gap:6px; align-items:center; }
        .btn-unfollow {
            background:none; border:1px solid #ddd; border-radius:14px;
            padding:3px 10px; font-size:.78rem; cursor:pointer; color:#999;
        }
        .btn-unfollow:hover { border-color:#e91e63; color:#e91e63; }
        .fav-dashboard-btn {
            display:block; text-align:center; margin-top:20px;
            padding:12px 20px; border-radius:8px;
            background:linear-gradient(135deg,var(--sakura-medium,#f48fb1),var(--sakura-dark,#e91e63));
            color:#fff; font-weight:700; font-size:.95rem; text-decoration:none;
        }
        .fav-dashboard-btn:hover { opacity:.92; }
        .fav-empty { color:#999; font-size:.9rem; padding:16px 0; text-align:center; }
        .fav-error-box {
            background:#fff3e0; border:1px solid #ffe0b2; border-radius:8px;
            padding:24px; text-align:center; margin-top:24px;
        }
        .fav-error-box h2 { color:#e65100; margin:0 0 8px; }
        .fav-error-box p { color:#666; margin:0 0 16px; }
        .fav-no-slug { text-align:center; padding:60px 20px; color:#999; }
        .fav-no-slug .empty-icon { font-size:3rem; margin-bottom:12px; }
        .fav-no-slug p { margin:0 0 8px; font-size:.9rem; }
        @media (max-width:480px) {
            .fav-url-row { flex-direction:column; }
            .fav-url-input { width:100%; }
        }

        /* ── Action Chip Bar (Transfer) ─────────────────────────────────── */
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
        @media (max-width:480px) {
            .fav-action-chip { padding:9px 8px; font-size:.82rem; }
        }
        @media (max-width:360px) {
            .fav-action-chip .chip-label { display:none; }
            .fav-action-chip { padding:11px 8px; }
            .fav-action-chip .chip-icon { font-size:1.15em; }
        }

        /* ── Modal (shared pattern) ─────────────────────────────────────── */
        .req-modal-overlay {
            position: fixed; inset: 0;
            background: rgba(0,0,0,.5);
            display: none;
            justify-content: center; align-items: center;
            z-index: 2000; padding: 20px; box-sizing: border-box;
        }
        .req-modal {
            background: #fff; border-radius: 12px;
            width: 100%; max-width: 600px; max-height: 90vh;
            overflow: hidden;
            display: flex; flex-direction: column;
            box-shadow: 0 15px 50px rgba(0,0,0,.3);
        }
        .req-modal-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 15px 20px;
            background: var(--sakura-gradient, linear-gradient(135deg,#FFB7C5,#E91E63));
            color: #fff; flex-shrink: 0;
        }
        .req-modal-header h2 { margin: 0; font-size: 1.1rem; }
        .req-close {
            background: none; border: none; color: #fff;
            font-size: 1.5rem; cursor: pointer; line-height: 1; padding: 0 4px;
        }
        .req-modal-body { padding: 20px; overflow-y: auto; flex: 1; min-height: 0; }

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
            <a href="<?= get_base_path() ?>/my-favorites/<?= htmlspecialchars($slug) ?>" class="home-icon-btn" title="My Favorites" style="background:var(--sakura-medium,#f48fb1);color:#fff" aria-current="page">⭐</a>
            <a href="<?= get_base_path() ?>/my/<?= htmlspecialchars($slug) ?>" class="home-icon-btn" title="My Upcoming Programs">📅</a>
            <?php endif; ?>
        </div>
        <div class="language-switcher">
            <button class="lang-btn active" data-lang="th" onclick="changeLanguage('th')">TH</button>
            <button class="lang-btn" data-lang="en" onclick="changeLanguage('en')">EN</button>
            <button class="lang-btn" data-lang="ja" onclick="changeLanguage('ja')">日本</button>
        </div>
        <h1 data-i18n="my.h1">⭐ My Favorites</h1>
    </header>

    <div class="content">

    <?php if (!$rawSlug): ?>
        <!-- No slug — access denied -->
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
                <input type="text" readonly id="favMyFavUrl" class="fav-url-input"
                       value="<?= htmlspecialchars($myFavUrl) ?>" onclick="this.select()">
                <button class="btn" onclick="copyMyFavUrl()" id="copyMyFavBtn" data-i18n="fav.copyUrl">📋 Copy URL</button>
            </div>
        </div>

        <!-- Action Chip Bar -->
        <div class="fav-actions-bar">
            <button class="fav-action-chip" id="qrChip" type="button" onclick="openQrTransferModal()">
                <span class="chip-icon">🔗</span>
                <span class="chip-label" data-i18n="actions.qrTransfer">QR Transfer</span>
            </button>
        </div>

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

        <?php if (empty($artistIds)): ?>
        <div class="fav-section">
            <p class="fav-empty" data-i18n="fav.noArtists">ยังไม่มีศิลปินที่ติดตาม — ไปที่หน้าโปรไฟล์ศิลปินแล้วกด ☆ ติดตาม</p>
        </div>

        <?php else: ?>

        <!-- Solo Artists -->
        <?php if (!empty($solos)): ?>
        <div class="fav-section">
            <div class="fav-section-header">
                <h2>
                    <span data-i18n="fav.soloArtists">🎤 ศิลปิน</span>
                    <span id="soloCount">(<?= count($solos) ?>)</span>
                </h2>
                <div class="fav-sort-bar">
                    <span data-i18n="fav.sort">เรียง:</span>
                    <button class="btn-sort" id="soloSortAZ" onclick="sortSection('soloList','asc','solo')" data-i18n="fav.sortAZ">A→Z</button>
                    <button class="btn-sort" id="soloSortZA" onclick="sortSection('soloList','desc','solo')" data-i18n="fav.sortZA">Z→A</button>
                </div>
            </div>
            <div class="fav-artist-list" id="soloList">
                <?php foreach ($solos as $aid => $a): ?>
                <div class="fav-artist-card" id="card-<?= $aid ?>" data-name="<?= htmlspecialchars($a['name']) ?>">
                    <span class="fac-icon">🎤</span>
                    <span class="fac-name">
                        <a href="<?= get_base_path() ?>/artist/<?= $aid ?>"><?= htmlspecialchars($a['name']) ?></a>
                    </span>
                    <div class="fac-actions">
                        <a href="<?= get_base_path() ?>/artist/<?= $aid ?>" class="btn" style="font-size:.78rem;padding:3px 10px;" data-i18n="my.profile">↗ โปรไฟล์</a>
                        <button class="btn-unfollow" onclick="unfollowArtist(<?= $aid ?>,'soloList','soloCount')" data-i18n="fav.unfollow">× เลิกติดตาม</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Groups -->
        <?php if (!empty($groups)): ?>
        <div class="fav-section">
            <div class="fav-section-header">
                <h2>
                    <span data-i18n="fav.groups">🎵 วง/กลุ่ม</span>
                    <span id="groupCount">(<?= count($groups) ?>)</span>
                </h2>
                <div class="fav-sort-bar">
                    <span data-i18n="fav.sort">เรียง:</span>
                    <button class="btn-sort" id="groupSortAZ" onclick="sortSection('groupList','asc','group')" data-i18n="fav.sortAZ">A→Z</button>
                    <button class="btn-sort" id="groupSortZA" onclick="sortSection('groupList','desc','group')" data-i18n="fav.sortZA">Z→A</button>
                </div>
            </div>
            <div class="fav-artist-list" id="groupList">
                <?php foreach ($groups as $aid => $a): ?>
                <div class="fav-artist-card" id="card-<?= $aid ?>" data-name="<?= htmlspecialchars($a['name']) ?>">
                    <span class="fac-icon">🎵</span>
                    <span class="fac-name">
                        <a href="<?= get_base_path() ?>/artist/<?= $aid ?>"><?= htmlspecialchars($a['name']) ?></a>
                    </span>
                    <div class="fac-actions">
                        <a href="<?= get_base_path() ?>/artist/<?= $aid ?>" class="btn" style="font-size:.78rem;padding:3px 10px;" data-i18n="my.profile">↗ โปรไฟล์</a>
                        <button class="btn-unfollow" onclick="unfollowArtist(<?= $aid ?>,'groupList','groupCount')" data-i18n="fav.unfollow">× เลิกติดตาม</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; ?>

        <!-- Link to Upcoming Programs dashboard -->
        <a href="<?= htmlspecialchars($dashUrl) ?>" class="fav-dashboard-btn" data-i18n="my.dashBtn">📅 ดูตาราง Upcoming Programs →</a>

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

<script>
const BASE_PATH   = <?= json_encode($base) ?>;
window.SITE_TITLE = <?= json_encode($siteTitle) ?>;
window.FAV_SLUG   = <?= json_encode($slug) ?>;
</script>
<script src="<?= asset_url('js/translations.js') ?>"></script>
<script src="<?= asset_url('js/common.js') ?>"></script>
<script>
// Auto-save slug to localStorage
if (window.FAV_SLUG) {
    const stored = localStorage.getItem('fav_slug');
    if (stored !== window.FAV_SLUG) localStorage.setItem('fav_slug', window.FAV_SLUG);
}

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

// ── Sort ─────────────────────────────────────────────────────────────────────
function sortSection(listId, dir, key) {
    const list = document.getElementById(listId);
    if (!list) return;
    const cards = Array.from(list.querySelectorAll('.fav-artist-card'));
    cards.sort(function(a, b) {
        const na = (a.dataset.name || '').toLowerCase();
        const nb = (b.dataset.name || '').toLowerCase();
        return dir === 'asc' ? na.localeCompare(nb, undefined, {sensitivity:'base'})
                             : nb.localeCompare(na, undefined, {sensitivity:'base'});
    });
    cards.forEach(function(c) { list.appendChild(c); });
    // Update button active states
    const azId = (key === 'solo' ? 'soloSortAZ' : 'groupSortAZ');
    const zaId = (key === 'solo' ? 'soloSortZA' : 'groupSortZA');
    const azBtn = document.getElementById(azId);
    const zaBtn = document.getElementById(zaId);
    if (azBtn) azBtn.classList.toggle('active', dir === 'asc');
    if (zaBtn) zaBtn.classList.toggle('active', dir === 'desc');
    // Persist
    localStorage.setItem('fav_sort_' + key, dir);
}

// Restore sort preference on load
document.addEventListener('DOMContentLoaded', function() {
    ['solo', 'group'].forEach(function(key) {
        const dir = localStorage.getItem('fav_sort_' + key);
        if (dir) {
            const listId = key + 'List';
            sortSection(listId, dir, key);
        }
    });
});

// ── Copy URL ─────────────────────────────────────────────────────────────────
function copyMyFavUrl() {
    const input = document.getElementById('favMyFavUrl');
    if (!input) return;
    navigator.clipboard.writeText(input.value).then(function() {
        const btn = document.getElementById('copyMyFavBtn');
        if (btn) {
            const orig = btn.textContent;
            btn.textContent = (translations[currentLang] && translations[currentLang]['fav.copied']) || '✅ Copied!';
            setTimeout(function() { btn.textContent = orig; }, 2000);
        }
    }).catch(function() { input.select(); document.execCommand('copy'); });
}

// ── QR Transfer ───────────────────────────────────────────────────────────────
const QR_DASHBOARD_URL = <?= json_encode($dashUrl) ?>;

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
async function unfollowArtist(artistId, listId, countId) {
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
        const card = document.getElementById('card-' + artistId);
        if (card) card.remove();
        // Update count badge
        const list = document.getElementById(listId);
        const countEl = document.getElementById(countId);
        if (list && countEl) {
            countEl.textContent = '(' + list.querySelectorAll('.fav-artist-card').length + ')';
        }
    }
}
</script>
</body>
</html>
