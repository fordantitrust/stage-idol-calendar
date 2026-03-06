<?php
require_once 'config.php';
require_once 'IcsParser.php';

// Security headers
send_security_headers();

// Multi-event support
$eventSlug = get_current_event_slug();
$eventMeta = get_event_by_slug($eventSlug);
$eventMetaId = $eventMeta ? intval($eventMeta['id']) : null;
$currentVenueMode = get_event_venue_mode($eventMeta);
$activeEvents = get_all_active_events();
$eventName = $eventMeta ? $eventMeta['name'] : 'Idol Stage Event';

// Check if we should show event listing (homepage) or calendar view
// The default slug event is intentionally hidden from the listing (it is a container for un-assigned programs).
// Only show the listing when there is at least one non-default active event.
$nonDefaultEvents = array_filter($activeEvents, fn($e) => $e['slug'] !== DEFAULT_EVENT_SLUG);
$showEventListing = MULTI_EVENT_MODE && $eventSlug === DEFAULT_EVENT_SLUG && count($nonDefaultEvents) > 0;

// Only load calendar data when showing calendar view
if (!$showEventListing) {
    $parser = new IcsParser('ics', true, 'data/calendar.db', $eventMetaId);

    // ดึงข้อมูลทั้งหมด
    $allEvents = $parser->getAllEvents();
    $artists = $parser->getAllOrganizers();
    $venues = $parser->getAllLocations();
    $types = $parser->getAllTypes();
} else {
    $allEvents = [];
    $artists = [];
    $venues = [];
    $types = [];
}

// รับค่า filter จาก GET parameters (รองรับหลายค่า) with sanitization
$filterArtists = get_sanitized_array_param('artist', 200, 50);
$filterVenues = get_sanitized_array_param('venue', 200, 50);
$filterTypes = get_sanitized_array_param('type', 200, 50);

// 🚀 Optimization: Pre-normalize categories + Pre-compute timestamps (avoid repeated strtotime calls)
$normalizedEvents = array_map(function($event) {
    $event['categoriesArray'] = !empty($event['categories'])
        ? array_map('trim', explode(',', $event['categories']))
        : [];
    $event['start_ts'] = !empty($event['start']) ? strtotime($event['start']) : 0;
    $event['end_ts']   = !empty($event['end'])   ? strtotime($event['end'])   : 0;
    return $event;
}, $allEvents);

// Create lookup arrays (O(1) search instead of O(n) with in_array)
$filterArtistsSet = array_flip($filterArtists);
$filterVenuesSet = array_flip($filterVenues);
$filterTypesSet = array_flip($filterTypes);

// กรองข้อมูล (ใช้ CATEGORIES สำหรับศิลปิน - รองรับหลายค่าแยกด้วย comma)
$filteredEvents = array_filter($normalizedEvents, function($event) use ($filterArtistsSet, $filterVenuesSet, $filterTypesSet) {
    // ตรวจสอบ artist/categories (รองรับหลายค่าแยกด้วย comma)
    $artistMatch = empty($filterArtistsSet);
    if (!$artistMatch) {
        // Use isset() instead of in_array() for O(1) lookup
        foreach ($event['categoriesArray'] as $category) {
            if (isset($filterArtistsSet[$category])) {
                $artistMatch = true;
                break;
            }
        }
    }

    // Check venue with O(1) lookup
    $venueMatch = empty($filterVenuesSet) || isset($filterVenuesSet[$event['location'] ?? null]);

    // Check program type with O(1) lookup
    $typeMatch = empty($filterTypesSet) || isset($filterTypesSet[$event['program_type'] ?? '']);

    return $artistMatch && $venueMatch && $typeMatch;
});

// จัดกลุ่มข้อมูลตามวัน
$eventsByDay = [];
foreach ($filteredEvents as $event) {
    $timestamp = $event['start_ts'];
    $dayKey = date('Y-m-d', $timestamp);
    if (!isset($eventsByDay[$dayKey])) {
        $eventsByDay[$dayKey] = [];
    }
    $eventsByDay[$dayKey][] = $event;
}

ksort($eventsByDay);

// เรียงลำดับ events ภายในแต่ละวันตามเวลา start
foreach ($eventsByDay as $dayKey => &$dayEvents) {
    usort($dayEvents, function($a, $b) {
        return $a['start_ts'] - $b['start_ts'];
    });
}
unset($dayEvents); // ยกเลิก reference

// แสดง column "ประเภท" เมื่อมี program ที่มี program_type อย่างน้อย 1 รายการ
$hasTypes = !empty($types);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?php echo htmlspecialchars(get_site_title()); ?> - Event Schedule</title>
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
    <!-- Index page CSS -->
    <link rel="stylesheet" href="<?php echo asset_url('styles/index.css'); ?>">
    <?php $siteTheme = get_site_theme($eventMeta); ?>
    <?php if ($siteTheme !== 'sakura'): ?>
    <link rel="stylesheet" href="<?php echo asset_url('styles/themes/' . $siteTheme . '.css'); ?>">
    <?php endif; ?>

</head>
<body>
    <?php if (!$showEventListing && !empty($eventsByDay) && count($eventsByDay) > 1): ?>
    <div class="date-jump-bar" id="dateJumpBar">
        <span class="date-jump-label" data-i18n="dateJump.label">📅 ข้ามไปวันที่:</span>
        <button class="date-jump-arrow" id="jumpPrev" onclick="scrollJumpBar(-200)" aria-label="Previous">◀</button>
        <div class="date-jump-buttons" id="jumpButtons">
            <?php foreach ($eventsByDay as $djKey => $djEvents): ?>
            <?php
                $djTimestamp = $djEvents[0]['start_ts'];
                $djDay = date('d', $djTimestamp);
                $djMonth = date('m', $djTimestamp);
                $djDayOfWeek = date('w', $djTimestamp);
            ?>
            <a href="#day-<?php echo $djKey; ?>" class="date-jump-btn" data-day="<?php echo $djDay; ?>" data-month="<?php echo $djMonth; ?>" data-dayofweek="<?php echo $djDayOfWeek; ?>">
                <span class="date-jump-day"><?php echo $djDay . '/' . $djMonth; ?></span>
                <span class="date-jump-weekday" data-dayofweek="<?php echo $djDayOfWeek; ?>"></span>
            </a>
            <?php endforeach; ?>
        </div>
        <button class="date-jump-arrow" id="jumpNext" onclick="scrollJumpBar(200)" aria-label="Next">▶</button>
    </div>
    <?php endif; ?>

    <div class="container">
        <?php if ($showEventListing): ?>
        <!-- ========================================
             Program Listing (Homepage)
             ======================================== -->
        <header>
            <div class="version-display">
                <span>v</span>
                <span><?php echo APP_VERSION; ?></span>
            </div>
            <div class="language-switcher">
                <button class="lang-btn active" data-lang="th" onclick="changeLanguage('th')">TH</button>
                <button class="lang-btn" data-lang="en" onclick="changeLanguage('en')">EN</button>
                <button class="lang-btn" data-lang="ja" onclick="changeLanguage('ja')">日本</button>
            </div>
            <h1 data-i18n="header.title"><?php echo htmlspecialchars(get_site_title()); ?></h1>
            <h2 data-i18n="header.subtitle">Idol stage event calendar</h2>
            <nav class="header-nav">
                <a href="<?php echo event_url('how-to-use.php'); ?>" class="header-nav-link" data-i18n="footer.howToUse">📖 วิธีการใช้งาน</a>
                <a href="<?php echo event_url('contact.php'); ?>" class="header-nav-link" data-i18n="footer.contact">✉️ ติดต่อเรา</a>
                <a href="<?php echo event_url('credits.php'); ?>" class="header-nav-link" data-i18n="footer.credits">📋 Credits</a>
            </nav>
        </header>

        <div class="program-listing">
            <h3 class="program-listing-title" data-i18n="listing.title">Events</h3>
            <?php
            // Sort events: ongoing first, then upcoming, then past
            $today = date('Y-m-d');
            $sortedEvents = $activeEvents;
            usort($sortedEvents, function($a, $b) use ($today) {
                $aStart = $a['start_date'] ?? '9999-12-31';
                $aEnd = $a['end_date'] ?? $aStart;
                $bStart = $b['start_date'] ?? '9999-12-31';
                $bEnd = $b['end_date'] ?? $bStart;

                // Determine status: 0=ongoing, 1=upcoming, 2=past
                $aStatus = ($aStart <= $today && $aEnd >= $today) ? 0 : ($aStart > $today ? 1 : 2);
                $bStatus = ($bStart <= $today && $bEnd >= $today) ? 0 : ($bStart > $today ? 1 : 2);

                if ($aStatus !== $bStatus) {
                    return $aStatus - $bStatus;
                }
                // Within same status, sort by start_date ascending for upcoming, descending for past
                if ($aStatus === 2) {
                    return strcmp($bStart, $aStart);
                }
                return strcmp($aStart, $bStart);
            });
            ?>
            <?php if (empty($sortedEvents)): ?>
                <div class="no-events-listing">
                    <div class="no-events-icon" style="font-size:4em;opacity:0.3;margin-bottom:20px;">📅</div>
                    <h2 data-i18n="listing.noEvents">ยังไม่มี Event ในระบบ</h2>
                </div>
            <?php else: ?>
                <div class="program-cards">
                    <?php foreach ($sortedEvents as $ev): ?>
                    <?php
                        // Skip the 'default' event in listing
                        if ($ev['slug'] === DEFAULT_EVENT_SLUG) continue;

                        $evStart = $ev['start_date'] ?? null;
                        $evEnd = $ev['end_date'] ?? $evStart;
                        $evStatus = 'upcoming';
                        if ($evStart && $evEnd) {
                            if ($evStart <= $today && $evEnd >= $today) {
                                $evStatus = 'ongoing';
                            } elseif ($evEnd < $today) {
                                $evStatus = 'past';
                            }
                        }

                        // Format dates for display
                        $displayStart = $evStart ? date('d/m/Y', strtotime($evStart)) : '-';
                        $displayEnd = $evEnd ? date('d/m/Y', strtotime($evEnd)) : '-';

                        // Per-event data version and credits
                        $evMetaId = intval($ev['id']);
                        $evDataVersion = get_data_version($evMetaId);
                        $evCredits = get_cached_credits($evMetaId);
                    ?>
                    <div class="program-card">
                        <div class="program-card-header">
                            <h4 class="program-card-name"><?php echo htmlspecialchars($ev['name']); ?></h4>
                            <div class="program-card-dates">
                                📅 <?php echo $displayStart; ?><?php if ($displayStart !== $displayEnd): ?> - <?php echo $displayEnd; ?><?php endif; ?>
                            </div>
                        </div>
                        <div class="program-card-body">
                            <div class="program-card-content">
                                <?php if ($evStatus === 'ongoing'): ?>
                                    <span class="program-card-badge ongoing" data-i18n="listing.ongoing">กำลังจัดงาน</span>
                                <?php elseif ($evStatus === 'upcoming'): ?>
                                    <span class="program-card-badge upcoming" data-i18n="listing.upcoming">กำลังจะมาถึง</span>
                                <?php else: ?>
                                    <span class="program-card-badge past" data-i18n="listing.past">จบแล้ว</span>
                                <?php endif; ?>

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
            <?php endif; ?>
        </div>

        <?php else: ?>
        <!-- ========================================
             Calendar View (Event Detail)
             ======================================== -->
        <header>
            <div class="version-display">
                <span>v</span>
                <span><?php echo APP_VERSION; ?></span>
            </div>
            <div class="language-switcher">
                <button class="lang-btn active" data-lang="th" onclick="changeLanguage('th')">TH</button>
                <button class="lang-btn" data-lang="en" onclick="changeLanguage('en')">EN</button>
                <button class="lang-btn" data-lang="ja" onclick="changeLanguage('ja')">日本</button>
            </div>
            <?php if (MULTI_EVENT_MODE && count($activeEvents) > 1): ?>
            <div class="program-selector">
                <select id="eventSelector" onchange="switchEvent(this.value)">
                    <?php foreach ($activeEvents as $ev): ?>
                    <option value="<?php echo htmlspecialchars($ev['slug']); ?>"
                            <?php echo ($ev['slug'] === $eventSlug) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($ev['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <h1 data-i18n="header.title"><?php echo htmlspecialchars(get_site_title()); ?></h1>
            <?php if ($eventMeta): ?>
            <div class="event-subtitle"><?php echo htmlspecialchars($eventName); ?></div>
            <?php if ($currentVenueMode === 'single' && !empty($venues)): ?>
            <div class="event-venue">📍 <?php echo htmlspecialchars($venues[0]); ?></div>
            <?php endif; ?>
            <?php endif; ?>
            <h2 data-i18n="header.subtitle">Idol stage event calendar</h2>
            <p data-i18n="header.disclaimer">* Please check the latest information again. We are not responsible for any errors that may occur during the preparation of this document.</p>
            <nav class="header-nav">
                <?php if (MULTI_EVENT_MODE): ?>
                <a href="<?php echo get_base_path(); ?>/" class="header-nav-link">🏠 Events</a>
                <?php endif; ?>
                <a href="<?php echo event_url('how-to-use.php'); ?>" class="header-nav-link" data-i18n="footer.howToUse">📖 วิธีการใช้งาน</a>
                <a href="<?php echo event_url('contact.php'); ?>" class="header-nav-link" data-i18n="footer.contact">✉️ ติดต่อเรา</a>
                <a href="<?php echo event_url('credits.php'); ?>" class="header-nav-link" data-i18n="footer.credits">📋 Credits</a>
                <a href="#data-version" class="header-nav-link">🔄️ <?php echo get_data_version($eventMetaId); ?></a>
            </nav>
        </header>

        <div class="filters">
            <form method="GET" action="<?php echo event_url('index.php'); ?>"  >
                <div class="filter-group">
                    <div class="filter-item">
                        <label data-i18n="filter.artist">🎤 กรองตามวง/ศิลปิน:</label>
                        <div class="search-box-wrapper" id="artistSearchWrapper">
                            <input type="text" class="search-box" id="artistSearch" data-i18n-placeholder="filter.searchArtist" placeholder="🔍 ค้นหาชื่อวง..." oninput="handleSearchInput('artistSearch', 'artistCheckboxes', 'artistSearchWrapper')" onfocus="this.select()">
                            <button type="button" class="search-clear-btn" onclick="clearSearch('artistSearch', 'artistCheckboxes', 'artistSearchWrapper')" title="ล้างการค้นหา">✕</button>
                        </div>
                        <?php if (!empty($filterArtists)): ?>
                        <div class="selected-tags" id="selectedArtists">
                            <?php foreach ($filterArtists as $artist): ?>
                            <span class="selected-tag">
                                <?php echo htmlspecialchars($artist); ?>
                                <button type="button" class="tag-remove" onclick="removeFilter('artist', '<?php echo htmlspecialchars(addslashes($artist)); ?>')" title="ลบ">✕</button>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <div class="checkbox-group" id="artistCheckboxes">
                            <?php foreach ($artists as $artist): ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="artist[]" value="<?php echo htmlspecialchars($artist); ?>"
                                           <?php echo (in_array($artist, $filterArtists)) ? 'checked' : ''; ?>>
                                    <span><?php echo htmlspecialchars($artist); ?></span>
                                </label>
                            <?php endforeach; ?>
                            <?php if (empty($artists)): ?>
                                <p class="no-options">ไม่มีข้อมูลศิลปิน</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($types)): ?>
                    <div class="filter-item">
                        <label data-i18n="filter.type">🏷️ กรองตามประเภท:</label>
                        <?php if (!empty($filterTypes)): ?>
                        <div class="selected-tags" id="selectedTypes">
                            <?php foreach ($filterTypes as $type): ?>
                            <span class="selected-tag">
                                <?php echo htmlspecialchars($type); ?>
                                <button type="button" class="tag-remove" onclick="removeFilter('type', '<?php echo htmlspecialchars(addslashes($type)); ?>')" title="ลบ">✕</button>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <div class="checkbox-group" id="typeCheckboxes">
                            <?php foreach ($types as $type): ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="type[]" value="<?php echo htmlspecialchars($type); ?>"
                                           <?php echo (in_array($type, $filterTypes)) ? 'checked' : ''; ?>>
                                    <span><?php echo htmlspecialchars($type); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($currentVenueMode === 'multi'): ?>
                    <div class="filter-item">
                        <label data-i18n="filter.venue">🏛️ กรองตามเวที:</label>
                        <div class="search-box-wrapper" id="venueSearchWrapper">
                            <input type="text" class="search-box" id="venueSearch" data-i18n-placeholder="filter.searchVenue" placeholder="🔍 ค้นหาชื่อเวที..." oninput="handleSearchInput('venueSearch', 'venueCheckboxes', 'venueSearchWrapper')" onfocus="this.select()">
                            <button type="button" class="search-clear-btn" onclick="clearSearch('venueSearch', 'venueCheckboxes', 'venueSearchWrapper')" title="ล้างการค้นหา">✕</button>
                        </div>
                        <?php if (!empty($filterVenues)): ?>
                        <div class="selected-tags" id="selectedVenues">
                            <?php foreach ($filterVenues as $venue): ?>
                            <span class="selected-tag">
                                <?php echo htmlspecialchars($venue); ?>
                                <button type="button" class="tag-remove" onclick="removeFilter('venue', '<?php echo htmlspecialchars(addslashes($venue)); ?>')" title="ลบ">✕</button>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <div class="checkbox-group" id="venueCheckboxes">
                            <?php foreach ($venues as $venue): ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="venue[]" value="<?php echo htmlspecialchars($venue); ?>"
                                           <?php echo (in_array($venue, $filterVenues)) ? 'checked' : ''; ?>>
                                    <span><?php echo htmlspecialchars($venue); ?></span>
                                </label>
                            <?php endforeach; ?>
                            <?php if (empty($venues)): ?>
                                <p class="no-options">ไม่มีข้อมูลเวที</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="filter-buttons">
                    <button type="submit" class="btn btn-primary" data-i18n="button.search">🔍 ค้นหา</button>
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='<?php echo event_url('index.php'); ?>'" data-i18n="button.reset">🔄 รีเซ็ต</button>
                    <button type="button" class="btn btn-success" onclick="saveAsImage()" data-i18n="button.saveImage">📸 บันทึกเป็นรูปภาพ</button>
                    <button type="button" class="btn btn-primary" onclick="exportToIcs()" data-i18n="button.exportIcs">📅 Export to Calendar</button>
                    <button type="button" class="btn btn-subscribe" onclick="openSubscribeModal()" data-i18n="button.subscribe">🔔 Subscribe</button>
                    <button type="button" class="btn btn-warning" onclick="openRequestModal()" data-i18n="button.requestAdd">📝 แจ้งเพิ่ม Event</button>
                </div>

                <!-- View Toggle Switch -->
                <div class="view-toggle">
                    <label class="toggle-label">
                        <span class="toggle-text active" data-i18n="view.list">รายการ</span>
                        <div class="toggle-switch">
                            <input type="checkbox" id="viewToggle" onchange="toggleView(this.checked)">
                            <span class="toggle-slider"></span>
                        </div>
                        <span class="toggle-text" data-i18n="view.gantt">ไทม์ไลน์</span>
                    </label>
                </div>
            </form>
        </div>

        <div class="calendar-container">
            <?php if (empty($filteredEvents)): ?>
                <div class="no-events">
                    <div class="no-events-icon">📅</div>
                    <h2 data-i18n="message.noPrograms">ไม่พบกิจกรรม</h2>
                </div>
            <?php else: ?>
                <?php foreach ($eventsByDay as $dayKey => $events): ?>
                    <?php
                        $firstEventTimestamp = $events[0]['start_ts'];
                        $day = date('d', $firstEventTimestamp);
                        $month = date('m', $firstEventTimestamp);
                        $year = date('Y', $firstEventTimestamp);
                        $dayOfWeek = date('w', $firstEventTimestamp);
                    ?>
                    <div class="day-section" id="day-<?php echo $dayKey; ?>">
                        <div class="day-header" data-day="<?php echo $day; ?>" data-month="<?php echo $month; ?>" data-year="<?php echo $year; ?>" data-dayofweek="<?php echo $dayOfWeek; ?>">
                            📅 <span class="day-header-text"><?php echo $day . '/' . $month . '/' . $year; ?></span>
                            <span class="day-name-header" style="margin-left: 8px;"></span>
                        </div>

                        <div class="events-table-container">
                            <table class="events-table">
                                <thead>
                                    <tr>
                                        <th data-i18n="table.time">เวลา</th>
                                        <th data-i18n="table.program">การแสดง/ศิลปิน</th>
                                        <?php if ($currentVenueMode === 'multi'): ?>
                                        <th data-i18n="table.venue">เวที</th>
                                        <?php endif; ?>
                                        <?php if ($hasTypes): ?>
                                        <th data-i18n="table.type">ประเภท</th>
                                        <?php endif; ?>
                                        <th data-i18n="table.categories">ศิลปินที่เกี่ยวข้อง</th>
                                        <th class="col-edit-request" style="width:80px;text-align:center;" data-i18n="table.editRequest">แจ้งแก้ไข</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($events as $event): ?>
                                        <?php
                                            $streamUrl = $event['stream_url'] ?? '';
                                            $streamPlatform = '';
                                            if (!empty($streamUrl)) {
                                                if (str_contains($streamUrl, 'instagram.com')) $streamPlatform = '📷';
                                                elseif (str_contains($streamUrl, 'x.com') || str_contains($streamUrl, 'twitter.com')) $streamPlatform = '𝕏';
                                                elseif (str_contains($streamUrl, 'youtube.com') || str_contains($streamUrl, 'youtu.be')) $streamPlatform = '▶️';
                                                else $streamPlatform = '🔴';
                                            }
                                        ?>
                                        <tr<?php echo !empty($streamUrl) ? ' class="program-live"' : ''; ?>>
                                            <td class="program-datetime-cell">
                                                <span class="program-time"
                                                      data-start="<?php echo date('H:i', $event['start_ts']); ?>"
                                                      data-end="<?php echo date('H:i', $event['end_ts']); ?>"></span>
                                            </td>
                                            <td class="program-info-cell">
                                                <div class="program-title-name">
                                                    <?php if ($streamPlatform): ?><span class="program-live-icon"><?php echo $streamPlatform; ?></span><?php endif; ?>
                                                    <?php echo htmlspecialchars($event['title'] ?? ''); ?>
                                                    <?php if (!empty($streamUrl)): ?>
                                                        <a href="<?php echo htmlspecialchars($streamUrl); ?>" target="_blank" rel="noopener" class="program-join-btn" data-i18n="badge.joinLive">🔴 เข้าร่วม</a>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!empty($event['description'])): ?>
                                                    <div class="event-description">
                                                        <?php echo htmlspecialchars($event['description']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <?php if ($currentVenueMode === 'multi'): ?>
                                            <td class="program-venue-cell<?php echo empty($event['location']) ? ' cell-empty' : ''; ?>">
                                                <?php if (!empty($event['location'])): ?>
                                                    <span style="color: #212529; font-size: 0.95em;">
                                                        <?php echo htmlspecialchars($event['location']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <?php endif; ?>
                                            <?php if ($hasTypes): ?>
                                            <td class="program-type-cell<?php echo empty($event['program_type']) ? ' cell-empty' : ''; ?>">
                                                <?php if (!empty($event['program_type'])): ?>
                                                <button type="button" class="program-type-badge" onclick="appendFilter('type', <?php echo htmlspecialchars(json_encode($event['program_type']), ENT_QUOTES, 'UTF-8'); ?>)" title="กรองตามประเภท: <?php echo htmlspecialchars($event['program_type']); ?>">
                                                    <?php echo htmlspecialchars($event['program_type']); ?>
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                            <?php endif; ?>
                                            <?php $cats = array_filter(array_map('trim', $event['categoriesArray'])); ?>
                                            <td class="program-categories-cell<?php echo empty($cats) ? ' cell-empty' : ''; ?>">
                                                <?php foreach ($cats as $cat): ?>
                                                    <button type="button" class="program-categories-badge" onclick="appendFilter('artist', <?php echo htmlspecialchars(json_encode($cat), ENT_QUOTES, 'UTF-8'); ?>)" title="กรองตามศิลปิน: <?php echo htmlspecialchars($cat); ?>">
                                                        <?php echo htmlspecialchars($cat); ?>
                                                    </button>
                                                <?php endforeach; ?>
                                            </td>
                                            <td class="program-action-cell" style="text-align:center;">
                                                <button type="button" class="btn-edit-request"
                                                    data-event='<?php echo htmlspecialchars(json_encode([
                                                        'id' => $event['id'] ?? null,
                                                        'title' => $event['title'] ?? '',
                                                        'location' => $event['location'] ?? '',
                                                        'organizer' => $event['organizer'] ?? '',
                                                        'categories' => $event['categories'] ?? '',
                                                        'description' => $event['description'] ?? '',
                                                        'start' => $event['start'] ?? '',
                                                        'end' => $event['end'] ?? ''
                                                    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>'
                                                    onclick="openModifyModal(JSON.parse(this.dataset.event))">
                                                    ✏️
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Events data for Gantt chart -->
                        <script type="application/json" class="events-data">
                        <?php echo json_encode(array_values($events), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>
                        </script>

                        <!-- Gantt view container -->
                        <div class="gantt-view" style="display: none;"></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?> <!-- end showEventListing conditional -->

        <footer>
            <div class="footer-text">
                <p data-i18n="footer.madeWith">สร้างด้วย ❤️ เพื่อแฟนไอดอล</p>
                <p data-i18n="footer.copyright">© 2026 Idol Stage Timetable. All rights reserved.</p>
                <p>Powered by <a href="https://github.com/fordantitrust/stage-idol-calendar" target="_blank">Stage Idol Calendar</a></p>
            </div>
        </footer>
    </div>

    <!-- Shared JavaScript (includes translations and common functions) -->
    <script>window.SITE_TITLE = <?php echo json_encode(get_site_title()); ?>;</script>
    <script src="<?php echo asset_url('js/translations.js'); ?>"></script>
    <script src="<?php echo asset_url('js/common.js'); ?>"></script>

    <script>
    const DEFAULT_EVENT_SLUG = '<?php echo DEFAULT_EVENT_SLUG; ?>';
    const BASE_PATH = '<?php echo rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/\\"); ?>';
    </script>

    <?php if (!$showEventListing): ?>
    <!-- Venues data for Gantt chart -->
    <script>
        window.VENUES_DATA = <?php echo json_encode(array_values($venues), JSON_UNESCAPED_UNICODE); ?>;
    </script>

    <!-- Subscribe Modal -->
    <div id="subscribeModal" class="req-modal-overlay">
        <div class="req-modal" style="max-width:480px;">
            <div class="req-modal-header">
                <h2 data-i18n="subscribe.title">🔔 Subscribe to Calendar</h2>
                <button onclick="closeSubscribeModal()" class="req-close">&times;</button>
            </div>
            <div class="req-modal-body">
                <p style="margin:0 0 12px;color:#555;" data-i18n="subscribe.desc">Subscribe ครั้งเดียว ปฏิทินของคุณจะอัปเดตอัตโนมัติเมื่อมีการเพิ่ม/แก้ไข program</p>

                <!-- webcal:// — Apple Calendar / iOS / Thunderbird -->
                <a id="subscribeWebcalLink" href="#" class="btn btn-subscribe" style="display:block;text-align:center;text-decoration:none;margin-bottom:4px;" data-i18n="subscribe.openApp">🔗 เปิดใน Calendar App (webcal://)</a>
                <p style="font-size:0.75em;color:#999;margin:0 0 14px;text-align:center;" data-i18n="subscribe.webcalHint">🍎 Apple Calendar · 📱 iOS · 🦅 Thunderbird</p>

                <!-- https:// URL — Google Calendar, Outlook, manual -->
                <p style="font-size:0.85em;color:#666;margin:0 0 6px;font-weight:500;" data-i18n="subscribe.orCopy">หรือ copy URL สำหรับ Google Calendar / Outlook:</p>
                <div style="display:flex;gap:8px;align-items:center;min-width:0;">
                    <input id="subscribeFeedUrl" type="text" readonly
                        style="flex:1;min-width:0;font-size:1rem;padding:7px 10px;border:1px solid #ddd;border-radius:6px;background:#f9f9f9;color:#333;overflow:hidden;text-overflow:ellipsis;">
                    <button onclick="copyFeedUrl()" class="btn btn-secondary" style="white-space:nowrap;flex-shrink:0;width:auto;padding:8px 14px;" data-i18n="subscribe.copy">📋 Copy</button>
                </div>
                <p id="subscribeCopied" style="display:none;color:#388e3c;font-size:0.85em;margin:6px 0 0;" data-i18n="subscribe.copied">✅ Copy แล้ว!</p>

                <!-- Outlook-specific instructions -->
                <div style="margin-top:14px;padding:10px 12px;background:#f0f4ff;border-radius:8px;border-left:3px solid #4a6cf7;">
                    <p style="margin:0 0 4px;font-size:0.82em;font-weight:600;color:#4a6cf7;" data-i18n="subscribe.outlookTitle">📧 Microsoft Outlook</p>
                    <p style="margin:0;font-size:0.78em;color:#555;line-height:1.5;" data-i18n="subscribe.outlookHint">Copy URL ด้านบน → เปิด Outlook → Calendar → Add calendar → Subscribe from web → วาง URL</p>
                </div>

                <!-- Sync frequency notice -->
                <div style="margin-top:12px;padding:10px 12px;background:#fffbf0;border-radius:8px;border-left:3px solid #f59e0b;">
                    <p style="margin:0 0 6px;font-size:0.82em;font-weight:600;color:#92400e;" data-i18n="subscribe.syncTitle">⏱ รอบการอัปเดตของแต่ละบริการ</p>
                    <p style="margin:0 0 6px;font-size:0.76em;color:#78350f;line-height:1.4;" data-i18n="subscribe.syncNote">ปฏิทินแต่ละแอปมีรอบดึงข้อมูลไม่เท่ากัน ข้อมูลอาจไม่แสดงทันทีหลังอัปเดต</p>
                    <ul style="margin:0;padding-left:16px;font-size:0.76em;color:#555;line-height:1.7;">
                        <li data-i18n="subscribe.syncApple">🍎 Apple Calendar / iOS — ~1 ชั่วโมง</li>
                        <li data-i18n="subscribe.syncGoogle">🌐 Google Calendar — ~24 ชั่วโมง</li>
                        <li data-i18n="subscribe.syncOutlookDesktop">📧 Outlook Desktop — ~24 ชั่วโมง (กด Refresh เพื่อดึงทันที)</li>
                        <li data-i18n="subscribe.syncOutlookWeb">🌐 Outlook.com / New Outlook — ~3 วัน (remove แล้ว subscribe ใหม่เพื่อดึงทันที)</li>
                        <li data-i18n="subscribe.syncThunderbird">🦅 Thunderbird — ~1 ชั่วโมง</li>
                    </ul>
                </div>
            </div>
            <div class="req-modal-footer">
                <button onclick="closeSubscribeModal()" class="btn btn-secondary" data-i18n="modal.cancel">ยกเลิก</button>
            </div>
        </div>
    </div>

    <!-- Request Modal -->
    <div id="requestModal" class="req-modal-overlay">
        <div class="req-modal">
            <div class="req-modal-header">
                <h2 id="modalTitle" data-i18n="modal.addTitle">📝 แจ้งเพิ่ม Event</h2>
                <button onclick="closeRequestModal()" class="req-close">&times;</button>
            </div>
            <form id="requestForm" onsubmit="submitRequest(event)">
                <input type="hidden" id="reqType" value="add">
                <input type="hidden" id="reqEventId" value="">
                <div class="req-modal-body">
                    <div class="req-row">
                        <div class="req-group"><label data-i18n="modal.programName">ชื่อ Event *</label><input type="text" id="reqTitle" required maxlength="200"></div>
                        <div class="req-group"><label data-i18n="modal.organizer">Organizer</label><input type="text" id="reqOrganizer" maxlength="200"></div>
                    </div>
                    <div class="req-row">
                        <div class="req-group">
                            <label data-i18n="modal.venue">เวที</label>
                            <select id="reqLocation">
                                <option value="" data-i18n="modal.selectVenue">-- เลือก --</option>
                                <?php foreach ($venues as $v): ?><option value="<?php echo htmlspecialchars($v); ?>"><?php echo htmlspecialchars($v); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="req-group"><label data-i18n="modal.categories">Categories</label><input type="text" id="reqCategories" maxlength="500"></div>
                    </div>
                    <div class="req-row">
                        <div class="req-group"><label data-i18n="modal.date">วันที่ *</label><input type="date" id="reqDate" required></div>
                        <div class="req-group"><label data-i18n="modal.startTime">เริ่ม *</label><input type="time" id="reqStart" required></div>
                        <div class="req-group"><label data-i18n="modal.endTime">สิ้นสุด *</label><input type="time" id="reqEnd" required></div>
                    </div>
                    <div class="req-group"><label data-i18n="modal.description">รายละเอียด</label><textarea id="reqDesc" rows="2" maxlength="2000"></textarea></div>
                    <hr style="margin:15px 0;border:none;border-top:1px solid #ddd;">
                    <div class="req-row">
                        <div class="req-group"><label data-i18n="modal.requesterName">ชื่อผู้แจ้ง *</label><input type="text" id="reqName" required maxlength="100"></div>
                        <div class="req-group"><label>Email</label><input type="email" id="reqEmail" maxlength="200"></div>
                    </div>
                    <div class="req-group"><label data-i18n="modal.requesterNote">หมายเหตุ</label><textarea id="reqNote" rows="2" maxlength="1000" data-i18n-placeholder="modal.notePlaceholder" placeholder="แหล่งข้อมูล, เหตุผล"></textarea></div>
                </div>
                <div class="req-modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeRequestModal()" data-i18n="modal.cancel">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary" id="reqSubmitBtn" data-i18n="modal.submit">ส่งคำขอ</button>
                </div>
            </form>
        </div>
    </div>



    <script>
    const VENUE_MODE = '<?php echo $currentVenueMode; ?>';
    const EVENT_SLUG = '<?php echo htmlspecialchars($eventSlug); ?>';

    // Date jump bar: fixed position, show/hide on scroll, highlight active date
    (function() {
        const jumpBar = document.getElementById('dateJumpBar');
        if (!jumpBar) return;

        const jumpBtns = jumpBar.querySelectorAll('.date-jump-btn');
        const jumpButtons = document.getElementById('jumpButtons');
        const jumpPrev = document.getElementById('jumpPrev');
        const jumpNext = document.getElementById('jumpNext');
        const daySections = document.querySelectorAll('.day-section[id^="day-"]');
        const calendarContainer = document.querySelector('.calendar-container');
        if (daySections.length === 0 || !calendarContainer) return;

        const jumpBarHeight = 56; // approximate bar height for offset

        // Position the bar to match container width
        function positionBar() {
            const container = document.querySelector('.container');
            if (container) {
                const rect = container.getBoundingClientRect();
                jumpBar.style.left = rect.left + 'px';
                jumpBar.style.width = rect.width + 'px';
                jumpBar.style.maxWidth = rect.width + 'px';
            }
        }

        // Update arrow button visibility based on scroll position of button strip
        function updateArrows() {
            if (!jumpButtons || !jumpPrev || !jumpNext) return;
            const atStart = jumpButtons.scrollLeft <= 2;
            const atEnd = jumpButtons.scrollLeft >= jumpButtons.scrollWidth - jumpButtons.clientWidth - 2;
            jumpPrev.disabled = atStart;
            jumpNext.disabled = atEnd;
        }

        // Show/hide based on scroll position
        function updateVisibility() {
            const calRect = calendarContainer.getBoundingClientRect();
            // Show when calendar top is above viewport
            if (calRect.top < 0 && calRect.bottom > jumpBarHeight) {
                jumpBar.classList.add('visible');
                positionBar();
                updateArrows();
            } else {
                jumpBar.classList.remove('visible');
            }
        }

        window.addEventListener('scroll', updateVisibility, { passive: true });
        window.addEventListener('resize', function() {
            if (jumpBar.classList.contains('visible')) { positionBar(); updateArrows(); }
        }, { passive: true });

        // Arrow scroll function (called from HTML onclick)
        window.scrollJumpBar = function(delta) {
            if (jumpButtons) {
                jumpButtons.scrollBy({ left: delta, behavior: 'smooth' });
            }
        };

        // Sync arrow state when user scrolls the button strip (touch or mouse)
        if (jumpButtons) {
            jumpButtons.addEventListener('scroll', updateArrows, { passive: true });

            // Mousewheel → horizontal scroll on desktop
            jumpButtons.addEventListener('wheel', function(e) {
                e.preventDefault();
                jumpButtons.scrollLeft += e.deltaY || e.deltaX;
                updateArrows();
            }, { passive: false });
        }

        // Smooth scroll with offset for fixed bar
        jumpBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href').substring(1);
                const target = document.getElementById(targetId);
                if (target) {
                    const y = target.getBoundingClientRect().top + window.pageYOffset - jumpBarHeight;
                    window.scrollTo({ top: y, behavior: 'smooth' });
                    // Update active state
                    jumpBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                }
            });
        });

        // IntersectionObserver to highlight current visible date
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const id = entry.target.id;
                    jumpBtns.forEach(b => {
                        b.classList.toggle('active', b.getAttribute('href') === '#' + id);
                    });
                }
            });
        }, { rootMargin: '-60px 0px -60% 0px', threshold: 0 });

        daySections.forEach(section => observer.observe(section));
    })();

    function openRequestModal() {
        document.getElementById('requestForm').reset();
        document.getElementById('reqType').value = 'add';
        document.getElementById('reqEventId').value = '';
        document.getElementById('modalTitle').textContent = translations[currentLang]['modal.addTitle'] || '📝 แจ้งเพิ่ม Event';
        document.getElementById('reqDate').value = new Date().toISOString().split('T')[0];
        document.getElementById('requestModal').classList.add('active');
    }

    function openModifyModal(eventData) {
        document.getElementById('requestForm').reset();
        document.getElementById('reqType').value = 'modify';
        document.getElementById('reqEventId').value = eventData.id || '';
        document.getElementById('modalTitle').textContent = translations[currentLang]['modal.editTitle'] || '✏️ แจ้งแก้ไข Event';

        // Fill form with event data
        document.getElementById('reqTitle').value = eventData.title || '';
        document.getElementById('reqOrganizer').value = eventData.organizer || '';
        document.getElementById('reqLocation').value = eventData.location || '';
        document.getElementById('reqCategories').value = eventData.categories || '';
        document.getElementById('reqDesc').value = eventData.description || '';

        // Parse start datetime
        if (eventData.start) {
            const startDate = new Date(eventData.start);
            document.getElementById('reqDate').value = startDate.toISOString().split('T')[0];
            document.getElementById('reqStart').value = startDate.toTimeString().substring(0, 5);
        }

        // Parse end datetime
        if (eventData.end) {
            const endDate = new Date(eventData.end);
            document.getElementById('reqEnd').value = endDate.toTimeString().substring(0, 5);
        }

        document.getElementById('requestModal').classList.add('active');
    }

    function closeRequestModal() {
        document.getElementById('requestModal').classList.remove('active');
    }

    async function submitRequest(ev) {
        ev.preventDefault();
        const btn = document.getElementById('reqSubmitBtn');
        const type = document.getElementById('reqType').value;
        const date = document.getElementById('reqDate').value;

        const data = {
            type,
            program_id: type === 'modify' ? document.getElementById('reqEventId').value : null,
            title: document.getElementById('reqTitle').value,
            start: date + ' ' + document.getElementById('reqStart').value + ':00',
            end: date + ' ' + document.getElementById('reqEnd').value + ':00',
            location: document.getElementById('reqLocation').value,
            organizer: document.getElementById('reqOrganizer').value,
            description: document.getElementById('reqDesc').value,
            categories: document.getElementById('reqCategories').value,
            requester_name: document.getElementById('reqName').value,
            requester_email: document.getElementById('reqEmail').value,
            requester_note: document.getElementById('reqNote').value,
            event_slug: EVENT_SLUG
        };

        btn.disabled = true;
        btn.textContent = translations[currentLang]['modal.submitting'] || 'กำลังส่ง...';

        try {
            const res = await fetch(BASE_PATH + '/api/request?action=submit', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await res.json();
            if (result.success) {
                alert(translations[currentLang]['modal.submitSuccess'] || 'ส่งคำขอสำเร็จ!');
                closeRequestModal();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (e) {
            alert(translations[currentLang]['modal.submitError'] || 'ไม่สามารถส่งได้');
        } finally {
            btn.disabled = false;
            btn.textContent = translations[currentLang]['modal.submit'] || 'ส่งคำขอ';
        }
    }

    // เพิ่ม filter value และ reload หน้า (ไม่ duplicate, ใช้ได้ทั้งมีและไม่มี filter อยู่ก่อน)
    function appendFilter(type, value) {
        const url = new URL(window.location.href);
        const params = url.searchParams;
        const currentValues = params.getAll(type + '[]');
        if (!currentValues.includes(value)) {
            params.append(type + '[]', value);
        }
        window.location.href = url.toString();
    }

    function removeFilter(type, value) {
        const url = new URL(window.location.href);
        const params = url.searchParams;

        // ดึงค่าปัจจุบันของ filter type นั้น
        const currentValues = params.getAll(type + '[]');

        // ลบค่าที่ต้องการออก
        const newValues = currentValues.filter(v => v !== value);

        // ลบ parameter เดิมทั้งหมด
        params.delete(type + '[]');

        // เพิ่ม parameter ใหม่ (ยกเว้นค่าที่ลบ)
        newValues.forEach(v => params.append(type + '[]', v));

        // reload หน้าด้วย URL ใหม่
        window.location.href = url.toString();
    }

    // Switch event (multi-event selector) - uses clean URL /event/slug
    function switchEvent(slug) {
        if (slug && slug !== DEFAULT_EVENT_SLUG) {
            window.location.href = BASE_PATH + '/event/' + slug;
        } else {
            window.location.href = BASE_PATH + '/';
        }
    }

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            if (document.getElementById('subscribeModal').classList.contains('active')) {
                closeSubscribeModal();
            } else if (document.getElementById('requestModal').classList.contains('active')) {
                closeRequestModal();
            }
        }
    });
    </script>
    <?php endif; ?>

    <?php if ($showEventListing): ?>
    <script>
    (function () {
        // Build modal element (injected once)
        const overlay = document.createElement('div');
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
            var nameEl   = card.querySelector('.program-card-name');
            var datesEl  = card.querySelector('.program-card-dates');
            var badgeEl  = card.querySelector('.program-card-badge');
            var descEl   = card.querySelector('.program-card-description');
            var metaEl   = card.querySelector('.program-card-meta');
            var linkEl   = card.querySelector('.program-card-link');

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
                modalDesc.innerHTML      = descEl.innerHTML;
                modalDesc.style.display  = '';
                // Remove line-clamp inside modal
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
                modalLink.href           = linkEl.getAttribute('href');
                modalLink.textContent    = linkEl.textContent.trim();
                modalLink.style.display  = '';
            } else {
                modalLink.style.display  = 'none';
            }

            overlay.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            overlay.style.display = 'none';
            document.body.style.overflow = '';
        }

        // Wire up each description
        document.querySelectorAll('.program-card-description').forEach(function (desc) {
            var card = desc.closest('.program-card');
            if (!card) return;

            desc.addEventListener('click', function () { openModal(card); });

            // Show "read more" button only when text is actually clamped
            if (desc.scrollHeight > desc.clientHeight + 2) {
                var btn = document.createElement('button');
                btn.type      = 'button';
                btn.className = 'program-card-readmore';
                btn.setAttribute('data-i18n', 'listing.readMore');
                btn.textContent = (window.translations && window.translations[window.currentLang || 'th'])
                    ? (window.translations[window.currentLang || 'th']['listing.readMore'] || '▼ อ่านเพิ่มเติม')
                    : '▼ อ่านเพิ่มเติม';
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    openModal(card);
                });
                desc.insertAdjacentElement('afterend', btn);
            }
        });

        // Close on overlay backdrop click
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeModal();
        });

        overlay.querySelector('.event-modal-close').addEventListener('click', closeModal);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.style.display !== 'none') closeModal();
        });
    })();
    </script>
    <?php endif; ?>
</body>
</html>
