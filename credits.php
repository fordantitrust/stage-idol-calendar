<?php
/**
 * Credits/References Page
 * แสดงแหล่งข้อมูลที่ใช้อ้างอิง
 */
require_once 'config.php';
send_security_headers();

// Multi-event support
$eventSlug = get_current_event_slug();
$eventMeta = get_event_by_slug($eventSlug);

// If a specific slug was requested but the event doesn't exist or is inactive,
// return 404 instead of silently showing credits from all events.
if ($eventSlug !== DEFAULT_EVENT_SLUG && $eventMeta === null) {
    http_response_code(404);
    $siteTitle = get_site_title();
    $theme = get_site_theme();
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><title>ไม่พบ Event - ' . htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8') . '</title>'
        . '<link rel="stylesheet" href="styles/common.css?v=' . APP_VERSION . '">'
        . '</head><body class="theme-' . htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') . '">'
        . '<div style="text-align:center;padding:80px 20px">'
        . '<h1>🌸 404 – ไม่พบ Event</h1>'
        . '<p>Event นี้ไม่มีอยู่ หรือถูกปิดใช้งานแล้ว</p>'
        . '<a href="/" style="color:var(--sakura-dark)">← กลับหน้าหลัก</a>'
        . '</div></body></html>';
    exit;
}

$eventId = $eventMeta ? intval($eventMeta['id']) : null;
$activeEvents = MULTI_EVENT_MODE ? get_all_active_events() : [];
$today = date('Y-m-d');

// Build event id → name/slug maps for labelling credits in global view
$eventNameMap = [];
$eventSlugMap = [];
foreach ($activeEvents as $ev) {
    $eventNameMap[intval($ev['id'])] = $ev['name'];
    $eventSlugMap[intval($ev['id'])] = $ev['slug'];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Credits - <?php echo htmlspecialchars(get_site_title()); ?></title>
    <?php if (defined('GOOGLE_ANALYTICS_ID') && GOOGLE_ANALYTICS_ID): ?>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo htmlspecialchars(GOOGLE_ANALYTICS_ID); ?>"></script>
    <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '<?php echo htmlspecialchars(GOOGLE_ANALYTICS_ID); ?>');
    </script>
    <?php endif; ?>
    <!-- Shared CSS -->
    <link rel="stylesheet" href="<?php echo asset_url('styles/common.css'); ?>">
    <!-- Credits page CSS -->
    <link rel="stylesheet" href="<?php echo asset_url('styles/credits.css'); ?>">
    <?php $siteTheme = get_site_theme($eventMeta); ?>
    <?php if ($siteTheme !== 'sakura'): ?>
    <link rel="stylesheet" href="<?php echo asset_url('styles/themes/' . $siteTheme . '.css'); ?>">
    <?php endif; ?>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-top-left">
                <a href="<?php echo get_base_path(); ?>/" class="home-icon-btn" data-i18n-title="nav.home" title="หน้าแรก">
                    <svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="M10 2L2 9h2v9h5v-5h2v5h5V9h2L10 2z" fill="currentColor"/>
                    </svg>
                </a>
                <?php if ($eventMeta): ?>
                <a href="<?php echo event_url('index.php'); ?>" class="home-icon-btn" data-i18n-title="nav.eventSchedule" title="ตารางงาน">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
                        <path d="M16 2v4M8 2v4M3 10h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </a>
                <?php endif; ?>
                <a href="<?php echo event_url('contact.php'); ?>" class="home-icon-btn" data-i18n-title="nav.contact" title="ติดต่อเรา">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
                <a href="<?php echo event_url('how-to-use.php'); ?>" class="home-icon-btn" data-i18n-title="nav.howToUse" title="วิธีการใช้งาน">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
                <?php if (MULTI_EVENT_MODE && count($activeEvents) > 1): ?>
                <button class="event-picker-btn" onclick="openEventPicker()" data-i18n-title="eventPicker.title" title="เลือก Event">
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                        <circle cx="3" cy="3" r="2" fill="currentColor"/>
                        <circle cx="9" cy="3" r="2" fill="currentColor"/>
                        <circle cx="15" cy="3" r="2" fill="currentColor"/>
                        <circle cx="3" cy="9" r="2" fill="currentColor"/>
                        <circle cx="9" cy="9" r="2" fill="currentColor"/>
                        <circle cx="15" cy="9" r="2" fill="currentColor"/>
                        <circle cx="3" cy="15" r="2" fill="currentColor"/>
                        <circle cx="9" cy="15" r="2" fill="currentColor"/>
                        <circle cx="15" cy="15" r="2" fill="currentColor"/>
                    </svg>
                </button>
                <?php endif; ?>
            </div>
            <div class="language-switcher">
                <button class="lang-btn active" data-lang="th" onclick="changeLanguage('th')">TH</button>
                <button class="lang-btn" data-lang="en" onclick="changeLanguage('en')">EN</button>
                <button class="lang-btn" data-lang="ja" onclick="changeLanguage('ja')">日本</button>
            </div>
            <h1 data-i18n="credits.title">📋 แหล่งข้อมูลอ้างอิง</h1>
            <p data-i18n="credits.subtitle">แหล่งข้อมูลที่ใช้ในการจัดทำปฏิทิน</p>
            <?php if ($eventMeta): ?>
            <div class="credits-event-banner">
                <span class="credits-event-banner-name"><?php echo htmlspecialchars($eventMeta['name']); ?></span>
            </div>
            <?php endif; ?>
        </header>

        <?php
        // Fetch credits from cache (or database if cache expired)
        $credits = get_cached_credits($eventId);

        // For global view: group credits by event_id
        // key 0 = global (event_id IS NULL), other keys = event_id
        $isGlobalView = ($eventId === null) && MULTI_EVENT_MODE;
        if ($isGlobalView && !empty($credits)) {
            $grouped = [];
            foreach ($credits as $c) {
                if ($c['event_id'] !== null && !isset($eventNameMap[intval($c['event_id'])])) {
                    continue; // skip credits from inactive/deleted events
                }
                $key = ($c['event_id'] === null) ? 0 : intval($c['event_id']);
                $grouped[$key][] = $c;
            }
            // Sort by event_id descending (newest first), global (key=0) goes last
            $globalGroup = isset($grouped[0]) ? [0 => $grouped[0]] : [];
            unset($grouped[0]);
            krsort($grouped);
            $grouped = $grouped + $globalGroup;
        }
        ?>

        <div class="content">
            <?php if (!empty($credits)): ?>
                <?php if ($isGlobalView): ?>
                    <?php foreach ($grouped as $gEventId => $groupCredits): ?>
                        <?php
                        if ($gEventId === 0) {
                            $groupName = 'ทั่วไป';
                            $groupUrl  = null;
                        } elseif (isset($eventNameMap[$gEventId])) {
                            $groupName = $eventNameMap[$gEventId];
                            $groupUrl  = event_url('index.php', $eventSlugMap[$gEventId]);
                        } else {
                            $groupName = 'Event #' . $gEventId;
                            $groupUrl  = null;
                        }
                        ?>
                        <div class="section">
                            <h2 class="credits-group-title">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true" style="flex-shrink:0">
                                    <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
                                    <path d="M16 2v4M8 2v4M3 10h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                                <?php if ($groupUrl): ?>
                                <a href="<?php echo htmlspecialchars($groupUrl); ?>" class="credits-group-link">
                                    <?php echo htmlspecialchars($groupName); ?>
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14L21 3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </a>
                                <?php else: ?>
                                <?php echo htmlspecialchars($groupName); ?>
                                <?php endif; ?>
                            </h2>
                            <ul class="reference-list">
                                <?php foreach ($groupCredits as $credit): ?>
                                    <li class="reference-item">
                                        <div class="reference-title"><?php echo htmlspecialchars($credit['title']); ?></div>
                                        <?php if (!empty($credit['description'])): ?>
                                            <p style="margin: 8px 0; color: #666; font-size: 0.9rem;">
                                                <?php echo nl2br(htmlspecialchars($credit['description'])); ?>
                                            </p>
                                        <?php endif; ?>
                                        <?php if (!empty($credit['link'])): ?>
                                            <a href="<?php echo htmlspecialchars($credit['link']); ?>"
                                               target="_blank" rel="noopener noreferrer"
                                               class="reference-link">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                                    <polyline points="15 3 21 3 21 9"></polyline>
                                                    <line x1="10" y1="14" x2="21" y2="3"></line>
                                                </svg>
                                                <?php echo htmlspecialchars($credit['link']); ?>
                                            </a>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="section">
                        <h2 data-i18n="credits.list.title">📋 แหล่งข้อมูลอ้างอิง</h2>
                        <ul class="reference-list">
                            <?php foreach ($credits as $credit): ?>
                                <li class="reference-item">
                                    <div class="reference-title"><?php echo htmlspecialchars($credit['title']); ?></div>
                                    <?php if (!empty($credit['description'])): ?>
                                        <p style="margin: 8px 0; color: #666; font-size: 0.9rem;">
                                            <?php echo nl2br(htmlspecialchars($credit['description'])); ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if (!empty($credit['link'])): ?>
                                        <a href="<?php echo htmlspecialchars($credit['link']); ?>"
                                           target="_blank" rel="noopener noreferrer"
                                           class="reference-link">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                                <polyline points="15 3 21 3 21 9"></polyline>
                                                <line x1="10" y1="14" x2="21" y2="3"></line>
                                            </svg>
                                            <?php echo htmlspecialchars($credit['link']); ?>
                                        </a>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="section">
                    <p style="text-align: center; padding: 40px; color: #999;" data-i18n="credits.noData">
                        ยังไม่มีข้อมูล credits
                    </p>
                </div>
            <?php endif; ?>

            <div class="disclaimer">
                <strong data-i18n="credits.disclaimer.title">Disclaimer:</strong>
                <span data-i18n="credits.disclaimer.text">ข้อมูลในปฏิทินนี้รวบรวมจากแหล่งข้อมูลสาธารณะเพื่อความสะดวกในการติดตามกำหนดการ กรุณาตรวจสอบข้อมูลอย่างเป็นทางการจากผู้จัดงานก่อนเข้าร่วมงาน ข้อมูลอาจมีการเปลี่ยนแปลงโดยไม่แจ้งให้ทราบล่วงหน้า</span>
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

    <!-- Shared JavaScript -->
    <script>window.SITE_TITLE = <?php echo json_encode(get_site_title()); ?>;
    const BASE_PATH = <?php echo json_encode(get_base_path()); ?>;
    <script src="<?php echo asset_url('js/translations.js'); ?>"></script>
    <script src="<?php echo asset_url('js/common.js'); ?>"></script>

<?php if (MULTI_EVENT_MODE && count($activeEvents) > 1): ?>
<!-- Event Picker Modal -->
<div id="eventPickerModal" class="event-picker-overlay" onclick="if(event.target===this)closeEventPicker()">
    <div class="event-picker-modal">
        <div class="event-picker-modal-header">
            <span data-i18n="eventPicker.title">เลือก Event</span>
            <button class="event-picker-close" onclick="closeEventPicker()">✕</button>
        </div>
        <div class="event-picker-controls">
            <input type="search" id="eventPickerSearch"
                   class="event-picker-search"
                   placeholder="ค้นหา event..."
                   data-i18n-placeholder="eventPicker.searchPlaceholder"
                   oninput="filterEventPicker()"
                   autocomplete="off">
            <div class="event-picker-filter-tabs" id="eventPickerTabs">
                <button class="ep-tab active" data-status="all"      onclick="setEventPickerTab(this)" data-i18n="eventPicker.all">ทั้งหมด</button>
                <button class="ep-tab"         data-status="ongoing"  onclick="setEventPickerTab(this)" data-i18n="listing.ongoing">กำลังจัดงาน</button>
                <button class="ep-tab"         data-status="upcoming" onclick="setEventPickerTab(this)" data-i18n="listing.upcoming">กำลังจะมาถึง</button>
                <button class="ep-tab"         data-status="past"     onclick="setEventPickerTab(this)" data-i18n="listing.past">จบแล้ว</button>
            </div>
        </div>
        <div class="event-picker-grid" id="eventPickerGrid">
            <?php
            $pickerEvents = $activeEvents;
            usort($pickerEvents, function($a, $b) use ($today, $eventSlug) {
                $aIsCurrent = ($a['slug'] === $eventSlug) ? 0 : 1;
                $bIsCurrent = ($b['slug'] === $eventSlug) ? 0 : 1;
                if ($aIsCurrent !== $bIsCurrent) return $aIsCurrent - $bIsCurrent;

                $aStart = $a['start_date'] ?? '9999-12-31';
                $aEnd   = $a['end_date']   ?? $aStart;
                $bStart = $b['start_date'] ?? '9999-12-31';
                $bEnd   = $b['end_date']   ?? $bStart;

                $aStatus = ($aStart <= $today && $aEnd >= $today) ? 0 : ($aStart > $today ? 1 : 2);
                $bStatus = ($bStart <= $today && $bEnd >= $today) ? 0 : ($bStart > $today ? 1 : 2);
                if ($aStatus !== $bStatus) return $aStatus - $bStatus;

                return $aStatus === 1
                    ? strcmp($aStart, $bStart)
                    : strcmp($bStart, $aStart);
            });
            foreach ($pickerEvents as $ev):
                $evStart = $ev['start_date'] ?? null;
                $evEnd   = $ev['end_date'] ?? $evStart;
                $evStatus = 'upcoming';
                if ($evStart && $evEnd) {
                    if ($evStart <= $today && $evEnd >= $today) $evStatus = 'ongoing';
                    elseif ($evEnd < $today) $evStatus = 'past';
                }
                $displayStart = $evStart ? date('d/m/Y', strtotime($evStart)) : null;
                $displayEnd   = $evEnd   ? date('d/m/Y', strtotime($evEnd))   : null;
                $isCurrent    = ($ev['slug'] === $eventSlug);
                $cardUrl      = event_url('credits.php', $ev['slug']);
                $statusLabel  = $evStatus === 'ongoing' ? 'กำลังจัดงาน' : ($evStatus === 'upcoming' ? 'กำลังจะมาถึง' : 'จบแล้ว');
                $statusI18n   = 'listing.' . $evStatus;
            ?>
            <a href="<?php echo htmlspecialchars($cardUrl); ?>"
               class="event-picker-card<?php echo $isCurrent ? ' current' : ''; ?>"
               data-name="<?php echo htmlspecialchars(mb_strtolower($ev['name'], 'UTF-8')); ?>"
               data-status="<?php echo $evStatus; ?>">
                <?php if ($isCurrent): ?>
                <span class="event-picker-current-badge">✓ ดูอยู่</span>
                <?php endif; ?>
                <div class="event-picker-card-name"><?php echo htmlspecialchars($ev['name']); ?></div>
                <?php if ($displayStart): ?>
                <div class="event-picker-card-dates">📅 <?php
                    echo $displayStart;
                    if ($displayEnd && $displayEnd !== $displayStart) echo ' – ' . $displayEnd;
                ?></div>
                <?php endif; ?>
                <span class="event-picker-card-badge <?php echo $evStatus; ?>"
                      data-i18n="<?php echo $statusI18n; ?>"><?php echo $statusLabel; ?></span>
            </a>
            <?php endforeach; ?>
            <div class="event-picker-empty" id="eventPickerEmpty" style="display:none" data-i18n="eventPicker.noResults">ไม่พบ event ที่ตรงกัน</div>
        </div>
    </div>
</div>
<?php endif; ?>
</body>
</html>
