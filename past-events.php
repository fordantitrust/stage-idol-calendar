<?php
/**
 * Past Events Page
 * แสดง events ที่จบแล้วทั้งหมด
 */
require_once 'config.php';
send_security_headers();

$siteTitle = get_site_title();
$theme     = get_site_theme();

// Fetch past events (end_date < today, is_active = 1, not default slug)
$pastEvents = [];
try {
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $db->prepare("
        SELECT * FROM events
        WHERE is_active = 1
          AND slug != :slug
          AND end_date IS NOT NULL
          AND end_date < date('now', 'localtime')
        ORDER BY end_date DESC, start_date DESC
    ");
    $stmt->execute([':slug' => DEFAULT_EVENT_SLUG]);
    $pastEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    $stmt = null;
    $db   = null;
} catch (PDOException $e) {
    // silently return empty
}

$today      = date('Y-m-d');
$perPage    = 5;
$totalItems = count($pastEvents);
$totalPages = max(1, (int)ceil($totalItems / $perPage));
$currentPage = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$pagedEvents = array_slice($pastEvents, ($currentPage - 1) * $perPage, $perPage);
$baseUrl    = get_base_path() . '/past-events';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?php echo htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php seo_render_meta([
        'description' => 'อีเวนต์ที่ผ่านมาของ ' . $siteTitle,
        'canonical'   => seo_full_url('/past-events'),
        'og_type'     => 'website',
    ]); ?>
    <?php if (defined('GOOGLE_ANALYTICS_ID') && GOOGLE_ANALYTICS_ID): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo htmlspecialchars(GOOGLE_ANALYTICS_ID); ?>"></script>
    <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '<?php echo htmlspecialchars(GOOGLE_ANALYTICS_ID); ?>');
    </script>
    <?php endif; ?>
    <?php if (defined('GOOGLE_ADS_CLIENT') && GOOGLE_ADS_CLIENT): ?>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?php echo htmlspecialchars(GOOGLE_ADS_CLIENT, ENT_QUOTES, 'UTF-8'); ?>"
         crossorigin="anonymous"></script>
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo asset_url('styles/common.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('styles/index.css'); ?>">
    <?php if ($theme !== 'sakura'): ?>
    <link rel="stylesheet" href="<?php echo asset_url('styles/themes/' . $theme . '.css'); ?>">
    <?php endif; ?>
</head>
<body class="theme-<?php echo htmlspecialchars($theme, ENT_QUOTES, 'UTF-8'); ?>">
<div class="container">
    <header>
        <div class="header-top-left">
            <a href="<?php echo get_base_path(); ?>/" class="home-icon-btn" data-i18n-title="nav.home" title="หน้าแรก">
                <svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M10 2L2 9h2v9h5v-5h2v5h5V9h2L10 2z" fill="currentColor"/>
                </svg>
            </a>
            <a href="contact.php" class="home-icon-btn" data-i18n-title="nav.contact" title="ติดต่อเรา">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a>
            <a href="how-to-use.php" class="home-icon-btn" data-i18n-title="nav.howToUse" title="วิธีการใช้งาน">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a>
        </div>
        <div class="language-switcher">
            <button class="lang-btn active" data-lang="th" onclick="changeLanguage('th')">TH</button>
            <button class="lang-btn" data-lang="en" onclick="changeLanguage('en')">EN</button>
            <button class="lang-btn" data-lang="ja" onclick="changeLanguage('ja')">日本</button>
        </div>
        <h1 data-i18n="header.title"><?php echo htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
        <h2 data-i18n="header.subtitle">Idol stage event calendar</h2>
        <nav class="header-nav">
            <a href="credits.php" class="header-nav-link" data-i18n="footer.credits">📋 แหล่งข้อมูลอ้างอิง</a>
        </nav>
    </header>

    <div class="program-listing">
        <h3 class="program-listing-title" data-i18n="listing.pastEventsTitle">🗂️ งานที่จบแล้ว</h3>

        <?php if (empty($pastEvents)): ?>
            <div class="no-events-listing">
                <div style="font-size:4em;opacity:0.3;margin-bottom:20px;">🗂️</div>
                <h2 data-i18n="listing.noPastEvents">ยังไม่มีงานที่จบแล้ว</h2>
            </div>
        <?php else: ?>
            <div class="program-cards">
                <?php foreach ($pagedEvents as $ev): ?>
                <?php
                    $evStart      = $ev['start_date'] ?? null;
                    $evEnd        = $ev['end_date'] ?? $evStart;
                    $displayStart = $evStart ? date('d/m/Y', strtotime($evStart)) : '-';
                    $displayEnd   = $evEnd   ? date('d/m/Y', strtotime($evEnd))   : '-';
                    $evMetaId     = intval($ev['id']);
                    $evDataVersion = get_data_version($evMetaId);
                    $evCredits    = get_cached_credits($evMetaId);
                ?>
                <div class="program-card">
                    <div class="program-card-header">
                        <h4 class="program-card-name"><?php echo htmlspecialchars($ev['name']); ?></h4>
                        <div class="program-card-dates">
                            📅 <?php echo $displayStart; ?><?php if ($displayStart !== $displayEnd): ?> – <?php echo $displayEnd; ?><?php endif; ?>
                        </div>
                    </div>
                    <div class="program-card-body">
                        <div class="program-card-content">
                            <span class="program-card-badge past" data-i18n="listing.past">จบแล้ว</span>

                            <?php if (!empty($ev['description'])): ?>
                            <div class="program-card-description">
                                <?php echo nl2br(htmlspecialchars($ev['description'])); ?>
                            </div>
                            <?php endif; ?>

                            <div class="program-card-meta">
                                <span class="program-card-meta-item" title="Data Version">
                                    🔄 <?php echo $evDataVersion; ?>
                                </span>
                                <?php if (!empty($evCredits)): ?>
                                <a href="<?php echo event_url('credits.php', $ev['slug']); ?>" class="program-card-meta-item program-card-meta-link" data-i18n="listing.credits">
                                    📋 Credits (<?php echo count($evCredits); ?>)
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <a href="<?php echo event_url('index.php', $ev['slug']); ?>" class="program-card-link" data-i18n="listing.viewSchedule">
                            📋 ดูตารางเวลา
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
            <nav class="pagination" aria-label="Pagination">
                <?php if ($currentPage > 1): ?>
                    <a href="<?php echo $baseUrl . '?page=' . ($currentPage - 1); ?>" data-i18n="listing.pagePrev">←</a>
                <?php endif; ?>
                <?php for ($p = 1; $p <= $totalPages; $p++):
                    if ($p === 1 || $p === $totalPages || abs($p - $currentPage) <= 1):
                ?>
                    <?php if ($p === $currentPage): ?>
                        <span class="current"><?php echo $p; ?></span>
                    <?php else: ?>
                        <a href="<?php echo $baseUrl . '?page=' . $p; ?>"><?php echo $p; ?></a>
                    <?php endif; ?>
                <?php elseif (abs($p - $currentPage) === 2): ?>
                    <span class="ellipsis">…</span>
                <?php endif; endfor; ?>
                <?php if ($currentPage < $totalPages): ?>
                    <a href="<?php echo $baseUrl . '?page=' . ($currentPage + 1); ?>" data-i18n="listing.pageNext">→</a>
                <?php endif; ?>
            </nav>
            <?php endif; ?>
        <?php endif; ?>

        <div style="text-align:center;margin-top:20px;padding-bottom:8px">
            <a href="<?php echo get_base_path(); ?>/"
               class="past-events-btn"
               data-i18n="listing.backToHome">← กลับหน้าแรก</a>
        </div>
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
const BASE_PATH = <?php echo json_encode(get_base_path()); ?>;
window.SITE_TITLE = <?php echo json_encode(get_site_title()); ?>;
</script>
<script src="<?php echo asset_url('js/translations.js'); ?>"></script>
<script src="<?php echo asset_url('js/common.js'); ?>"></script>
<script>
(function () {
    var overlay = document.createElement('div');
    overlay.className = 'event-modal-overlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.style.display = 'none';
    overlay.innerHTML =
        '<div class="event-modal">' +
            '<div class="event-modal-header">' +
                '<button class="event-modal-close" aria-label="ปิด">&#x2715;</button>' +
                '<h3 class="event-modal-name"></h3>' +
                '<div class="event-modal-dates"></div>' +
                '<span class="event-modal-badge program-card-badge"></span>' +
            '</div>' +
            '<div class="event-modal-body">' +
                '<div class="event-modal-description"></div>' +
                '<div class="event-modal-meta"></div>' +
                '<a class="event-modal-link" href="#"></a>' +
            '</div>' +
        '</div>';
    document.body.appendChild(overlay);

    function openModal(card) {
        var nameEl  = card.querySelector('.program-card-name');
        var datesEl = card.querySelector('.program-card-dates');
        var badgeEl = card.querySelector('.program-card-badge');
        var descEl  = card.querySelector('.program-card-description');
        var metaEl  = card.querySelector('.program-card-meta');
        var linkEl  = card.querySelector('.program-card-link');

        overlay.querySelector('.event-modal-name').textContent  = nameEl  ? nameEl.textContent.trim()  : '';
        overlay.querySelector('.event-modal-dates').textContent = datesEl ? datesEl.textContent.trim() : '';

        var modalBadge = overlay.querySelector('.event-modal-badge');
        if (badgeEl) {
            modalBadge.textContent = badgeEl.textContent.trim();
            modalBadge.className   = 'event-modal-badge program-card-badge ' +
                (badgeEl.classList.contains('ongoing')  ? 'ongoing'  :
                 badgeEl.classList.contains('upcoming') ? 'upcoming' : 'past');
            modalBadge.style.display = '';
        } else {
            modalBadge.style.display = 'none';
        }

        var modalDesc = overlay.querySelector('.event-modal-description');
        if (descEl) {
            modalDesc.innerHTML     = descEl.innerHTML;
            modalDesc.style.webkitLineClamp = 'unset';
            modalDesc.style.display = 'block';
            modalDesc.style.overflow = 'visible';
        } else {
            modalDesc.style.display = 'none';
        }

        var modalMeta = overlay.querySelector('.event-modal-meta');
        if (metaEl && metaEl.children.length) {
            modalMeta.innerHTML     = metaEl.innerHTML;
            modalMeta.style.display = '';
        } else {
            modalMeta.style.display = 'none';
        }

        var modalLink = overlay.querySelector('.event-modal-link');
        if (linkEl) {
            modalLink.href        = linkEl.getAttribute('href');
            modalLink.textContent = linkEl.textContent.trim();
            modalLink.style.display = '';
        } else {
            modalLink.style.display = 'none';
        }

        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        overlay.style.display = 'none';
        document.body.style.overflow = '';
    }

    document.querySelectorAll('.program-card-description').forEach(function (desc) {
        var card = desc.closest('.program-card');
        if (!card) return;
        desc.addEventListener('click', function () { openModal(card); });
        if (desc.scrollHeight > desc.clientHeight + 2) {
            var btn = document.createElement('button');
            btn.type      = 'button';
            btn.className = 'program-card-readmore';
            btn.setAttribute('data-i18n', 'listing.readMore');
            btn.textContent = (window.translations && window.translations[window.currentLang || 'th'])
                ? (window.translations[window.currentLang || 'th']['listing.readMore'] || '▼ อ่านเพิ่มเติม')
                : '▼ อ่านเพิ่มเติม';
            btn.addEventListener('click', function (e) { e.stopPropagation(); openModal(card); });
            desc.insertAdjacentElement('afterend', btn);
        }
    });

    overlay.addEventListener('click', function (e) { if (e.target === overlay) closeModal(); });
    overlay.querySelector('.event-modal-close').addEventListener('click', closeModal);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.style.display !== 'none') closeModal();
    });
})();
</script>
</body>
</html>
