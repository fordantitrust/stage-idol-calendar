// Common JavaScript for Idol Stage Event Calendar

let currentLang = localStorage.getItem('language') || 'th';

// DOM cache to reduce repeated querySelectorAll calls
const domCache = {};
function populateDomCache() {
    domCache.langBtns = document.querySelectorAll('.lang-btn');
    domCache.i18n = document.querySelectorAll('[data-i18n]');
    domCache.placeholders = document.querySelectorAll('[data-i18n-placeholder]');
    domCache.dayHeaders = document.querySelectorAll('.day-header');
    domCache.dayNames = document.querySelectorAll('.day-name');
    domCache.eventTimes = document.querySelectorAll('.program-time');
}
document.addEventListener('DOMContentLoaded', populateDomCache);

// Change language
function changeLanguage(lang) {
    currentLang = lang;
    window.currentLang = lang;
    localStorage.setItem('language', lang);

    // Update active state of buttons (use cache when available)
    const langBtns = domCache.langBtns || document.querySelectorAll('.lang-btn');
    langBtns.forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.lang === lang) {
            btn.classList.add('active');
        }
    });

    // Update html lang attribute for accessibility
    const langCode = translations[lang].langCode || lang;
    document.documentElement.lang = langCode;

    // Update all text
    updateLanguage();

    // Re-render calendar if in calendar mode (month/day names are JS-rendered, not data-i18n)
    if (typeof VENUE_MODE !== 'undefined' && VENUE_MODE === 'calendar' && calendarYear !== null) {
        renderAndMountCalendar();
    }

    // Notify inline page scripts that language changed (e.g. to re-render dynamic modal content)
    document.dispatchEvent(new CustomEvent('appLangChange', { detail: { lang: lang } }));
}

// Update all language text
function updateLanguage() {
    const lang = translations[currentLang];

    // Update text content (use cached nodeLists)
    const i18nEls = domCache.i18n || document.querySelectorAll('[data-i18n]');
    i18nEls.forEach(el => {
        const key = el.dataset.i18n;
        if (lang[key]) {
            // ข้าม anchor ที่มี href เป็น mailto: หรือข้อความใน element = URL (เช่น link social media)
            if (el.tagName === 'A') {
                const href = el.getAttribute('href') || '';
                // ข้ามเฉพาะ mailto: links หรือ external links ที่แสดง URL เป็นข้อความ
                if (href.startsWith('mailto:')) {
                    return;
                }
            }
            el.textContent = lang[key];  // ใช้ textContent แทน innerHTML เพื่อป้องกัน XSS
        }
    });

    // Update placeholders
    const placeholderEls = domCache.placeholders || document.querySelectorAll('[data-i18n-placeholder]');
    placeholderEls.forEach(el => {
        const key = el.dataset.i18nPlaceholder;
        if (lang[key]) el.placeholder = lang[key];
    });

    // Update title attributes
    document.querySelectorAll('[data-i18n-title]').forEach(el => {
        const key = el.dataset.i18nTitle;
        if (lang[key]) el.title = lang[key];
    });

    // Update day headers (for index page)
    const dayHeaderEls = domCache.dayHeaders || document.querySelectorAll('.day-header');
    dayHeaderEls.forEach(el => {
        const day = el.dataset.day;
        const month = el.dataset.month;
        const year = parseInt(el.dataset.year) + lang.yearOffset;
        const dayOfWeek = parseInt(el.dataset.dayofweek);

        const dateText = day + '/' + month + '/' + year;
        const dayName = lang.days[dayOfWeek];

        const dayHeaderText = el.querySelector('.day-header-text');
        const dayNameHeader = el.querySelector('.day-name-header');

        if (dayHeaderText) dayHeaderText.textContent = dateText;
        if (dayNameHeader) dayNameHeader.textContent = dayName;
    });

    // Update date jump bar weekday names
    const dateJumpBtns = document.querySelectorAll('.date-jump-weekday[data-dayofweek]');
    dateJumpBtns.forEach(el => {
        const dayOfWeek = parseInt(el.dataset.dayofweek);
        el.textContent = lang.days[dayOfWeek];
    });

    // Update day names
    const dayNameEls = domCache.dayNames || document.querySelectorAll('.day-name');
    dayNameEls.forEach(el => {
        const dayNum = parseInt(el.dataset.day);
        el.textContent = lang.days[dayNum];
    });

    // Update time format (for index page)
    const eventTimeEls = domCache.eventTimes || document.querySelectorAll('.program-time');
    eventTimeEls.forEach(el => {
        const startTime = el.dataset.start;
        const endTime = el.dataset.end;
        const formattedStart = formatTime(startTime, lang.timeFormat, lang['time.unit']);
        const formattedEnd = formatTime(endTime, lang.timeFormat, lang['time.unit']);
        el.textContent = formattedStart === formattedEnd ? formattedStart : formattedStart + ' - ' + formattedEnd;
    });
}

// Format time based on language settings
function formatTime(time24, format, unit) {
    const [hours, minutes] = time24.split(':');
    const h = parseInt(hours);
    const m = minutes;

    if (format === '12h') {
        const period = h >= 12 ? 'PM' : 'AM';
        const h12 = h % 12 || 12;
        return `${h12}:${m} ${period}`;
    } else {
        return unit ? `${time24} ${unit}` : time24;
    }
}

// Show notification
function showNotification(message, isError = false) {
    const notification = document.createElement('div');
    notification.textContent = message;

    const isMobile = window.innerWidth <= 768;
    const notificationStyle = isMobile ? `
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        padding: 12px 20px;
        background: ${isError ? '#dc3545' : '#28a745'};
        color: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        z-index: 10000;
        font-weight: 600;
        font-size: 0.9em;
        max-width: 90%;
        text-align: center;
        animation: slideInBottom 0.3s ease-out;
    ` : `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 25px;
        background: ${isError ? '#dc3545' : '#28a745'};
        color: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        z-index: 10000;
        font-weight: 600;
        animation: slideIn 0.3s ease-out;
    `;

    notification.style.cssText = notificationStyle;
    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = isMobile ? 'slideOutBottom 0.3s ease-out' : 'slideOut 0.3s ease-out';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Save as image — server-side PHP GD rendering
async function saveAsImage() {
    const button = event.target;
    button.disabled = true;
    const originalText = button.textContent;
    button.textContent = translations[currentLang]['message.generating'] || '...';

    try {
        // Build query params from current page state
        const params = new URLSearchParams(window.location.search);
        params.set('lang', currentLang);
        if (typeof EVENT_SLUG !== 'undefined' && EVENT_SLUG &&
            (typeof DEFAULT_EVENT_SLUG === 'undefined' || EVENT_SLUG !== DEFAULT_EVENT_SLUG)) {
            params.set('event', EVENT_SLUG);
        }

        params.set('_t', Date.now());
        const url = (typeof BASE_PATH !== 'undefined' ? BASE_PATH : '') + '/image?' + params.toString();

        const response = await fetch(url);
        if (!response.ok) {
            throw new Error('Server returned ' + response.status);
        }
        const contentType = response.headers.get('Content-Type') || '';
        if (!contentType.startsWith('image/')) {
            throw new Error('Unexpected response type: ' + contentType);
        }

        const blob = await response.blob();
        const objectUrl = URL.createObjectURL(blob);
        const date = new Date();
        const dateStr = date.toISOString().split('T')[0];
        const filename = `stage-idol-${dateStr}.png`;

        const link = document.createElement('a');
        link.download = filename;
        link.href = objectUrl;
        link.click();
        URL.revokeObjectURL(objectUrl);

        button.disabled = false;
        button.textContent = originalText;
        showNotification(translations[currentLang]['message.success']);

    } catch (error) {
        console.error('Error generating image:', error);
        button.disabled = false;
        button.textContent = originalText;
        showNotification(translations[currentLang]['message.error'], true);
    }
}

// Filter checkboxes by search text
function filterCheckboxes(searchBoxId, checkboxGroupId) {
    const searchInput = document.getElementById(searchBoxId);
    const checkboxGroup = document.getElementById(checkboxGroupId);
    const searchText = searchInput.value.toLowerCase().trim();
    const labels = checkboxGroup.querySelectorAll('.checkbox-label');

    let visibleCount = 0;

    labels.forEach(label => {
        const text = label.querySelector('span').textContent.toLowerCase();
        if (text.includes(searchText)) {
            label.style.display = 'flex';
            visibleCount++;
        } else {
            label.style.display = 'none';
        }
    });

    // Show/hide "no results" message
    let noResultsMsg = checkboxGroup.querySelector('.no-results-msg');
    const noOptionsMsg = checkboxGroup.querySelector('.no-options');

    if (visibleCount === 0 && searchText !== '') {
        if (!noResultsMsg) {
            noResultsMsg = document.createElement('p');
            noResultsMsg.className = 'no-options no-results-msg';
            checkboxGroup.appendChild(noResultsMsg);
        }
        noResultsMsg.textContent = translations[currentLang]['message.noResults'];
        noResultsMsg.style.display = 'block';
        if (noOptionsMsg) noOptionsMsg.style.display = 'none';
    } else {
        if (noResultsMsg) {
            noResultsMsg.style.display = 'none';
        }
        if (noOptionsMsg && visibleCount === 0) {
            noOptionsMsg.style.display = 'block';
        }
    }
}

// Handle search input with clear button visibility
function handleSearchInput(searchBoxId, checkboxGroupId, wrapperId) {
    const searchInput = document.getElementById(searchBoxId);
    const wrapper = document.getElementById(wrapperId);

    // Toggle has-text class for showing/hiding clear button
    if (searchInput.value.length > 0) {
        wrapper.classList.add('has-text');
    } else {
        wrapper.classList.remove('has-text');
    }

    // Filter the checkboxes
    filterCheckboxes(searchBoxId, checkboxGroupId);
}

// Clear search and reset checkbox filter
function clearSearch(searchBoxId, checkboxGroupId, wrapperId) {
    const searchInput = document.getElementById(searchBoxId);
    const wrapper = document.getElementById(wrapperId);

    // Clear the input
    searchInput.value = '';
    wrapper.classList.remove('has-text');

    // Reset the filter
    filterCheckboxes(searchBoxId, checkboxGroupId);

    // Focus back on input for convenience
    searchInput.focus();
}

// Export to ICS file
function exportToIcs() {
    const form = document.querySelector('form');
    const formData = new FormData(form);
    const params = new URLSearchParams();

    for (let [key, value] of formData.entries()) {
        if (key === 'artist[]' || key === 'venue[]' || key === 'type[]') {
            params.append(key, value);
        }
    }

    // Build export URL with clean event path
    var basePath = (typeof BASE_PATH !== 'undefined') ? BASE_PATH : '';
    var exportPath = basePath + '/export';
    if (typeof EVENT_SLUG !== 'undefined' && EVENT_SLUG && typeof DEFAULT_EVENT_SLUG !== 'undefined' && EVENT_SLUG !== DEFAULT_EVENT_SLUG) {
        exportPath = basePath + '/event/' + EVENT_SLUG + '/export';
    }

    window.location.href = exportPath + '?' + params.toString();
}

// ========================================
// Event Picker Modal
// ========================================

function openEventPicker() {
    var modal = document.getElementById('eventPickerModal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeEventPicker() {
    var modal = document.getElementById('eventPickerModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

var _epActiveStatus = 'all';

function setEventPickerTab(btn) {
    _epActiveStatus = btn.dataset.status;
    document.querySelectorAll('.ep-tab').forEach(function(t) { t.classList.remove('active'); });
    btn.classList.add('active');
    filterEventPicker();
}

function filterEventPicker() {
    var query = (document.getElementById('eventPickerSearch').value || '').toLowerCase().trim();
    var cards = document.querySelectorAll('#eventPickerGrid .event-picker-card');
    var visible = 0;
    cards.forEach(function(card) {
        var nameMatch   = !query || (card.dataset.name || '').indexOf(query) !== -1;
        var statusMatch = _epActiveStatus === 'all' || card.dataset.status === _epActiveStatus;
        var show = nameMatch && statusMatch;
        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    var empty = document.getElementById('eventPickerEmpty');
    if (empty) empty.style.display = visible === 0 ? 'block' : 'none';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeEventPicker();
});

// ========================================
// ICS Subscription Feed
// ========================================

function openSubscribeModal(isGroup, feedName) {
    var basePath = (typeof BASE_PATH !== 'undefined') ? BASE_PATH : '';
    var feedUrl;

    if (typeof ARTIST_ID !== 'undefined' && ARTIST_ID) {
        // Artist profile page: feed filtered to this artist (or their group) across all events
        feedUrl = window.location.protocol + '//' + window.location.host +
                  basePath + '/artist/' + ARTIST_ID + '/feed';
        if (isGroup) feedUrl += '?group=1';
    } else {
        // Event page: feed for this event with current filter selections
        var feedPath = basePath + '/feed';
        if (typeof EVENT_SLUG !== 'undefined' && EVENT_SLUG &&
            typeof DEFAULT_EVENT_SLUG !== 'undefined' && EVENT_SLUG !== DEFAULT_EVENT_SLUG) {
            feedPath = basePath + '/event/' + EVENT_SLUG + '/feed';
        }

        // Collect current filter selections from the form
        var params = new URLSearchParams();
        var form = document.querySelector('form');
        if (form) {
            var formData = new FormData(form);
            for (var pair of formData.entries()) {
                if (pair[0] === 'artist[]' || pair[0] === 'venue[]' || pair[0] === 'type[]') {
                    params.append(pair[0], pair[1]);
                }
            }
        }

        feedUrl = window.location.protocol + '//' + window.location.host + feedPath;
        var paramsStr = params.toString();
        if (paramsStr) feedUrl += '?' + paramsStr;
    }

    var webcalUrl = feedUrl.replace(/^https?:\/\//, 'webcal://');

    var nameEl = document.getElementById('subscribeFeedName');
    if (nameEl) nameEl.textContent = feedName ? '🎤 ' + feedName : '';

    document.getElementById('subscribeWebcalLink').href = webcalUrl;
    document.getElementById('subscribeFeedUrl').value = feedUrl;
    document.getElementById('subscribeCopied').style.display = 'none';
    document.getElementById('subscribeModal').classList.add('active');
}

function closeSubscribeModal() {
    document.getElementById('subscribeModal').classList.remove('active');
}

function copyFeedUrl() {
    var input = document.getElementById('subscribeFeedUrl');
    input.select();
    input.setSelectionRange(0, 99999);
    var copied = document.getElementById('subscribeCopied');
    var showCopied = function() {
        copied.style.display = 'block';
        setTimeout(function() { copied.style.display = 'none'; }, 2000);
    };
    if (navigator.clipboard) {
        navigator.clipboard.writeText(input.value).then(showCopied).catch(function() {
            document.execCommand('copy');
            showCopied();
        });
    } else {
        document.execCommand('copy');
        showCopied();
    }
}

// ========================================
// Gantt Chart View Functions
// ========================================

// Gantt configuration
const GANTT_CONFIG = {
    startHour: 9,
    endHour: 21
};

// Get venues from PHP data or use empty array
function getVenues() {
    return window.VENUES_DATA || [];
}

// View state
let isGanttView = localStorage.getItem('viewMode') === 'gantt';

// Toggle between List and Gantt view
function toggleView(isGantt) {
    isGanttView = isGantt;
    localStorage.setItem('viewMode', isGantt ? 'gantt' : 'list');

    // Update toggle text styles
    const toggleTexts = document.querySelectorAll('.toggle-text');
    if (toggleTexts.length >= 2) {
        toggleTexts[0].classList.toggle('active', !isGantt);
        toggleTexts[1].classList.toggle('active', isGantt);
    }

    // Toggle visibility of views
    document.querySelectorAll('.day-section').forEach(section => {
        const listView = section.querySelector('.events-table-container');
        const ganttView = section.querySelector('.gantt-view');
        const eventsData = section.querySelector('.events-data');

        if (listView) listView.style.display = isGantt ? 'none' : 'block';

        if (ganttView) {
            ganttView.style.display = isGantt ? 'block' : 'none';

            // Render Gantt chart if not already rendered
            if (isGantt && ganttView.innerHTML === '' && eventsData) {
                try {
                    const events = JSON.parse(eventsData.textContent);
                    ganttView.innerHTML = renderGanttChart(events);
                    setupGanttScrollIndicator(ganttView);
                } catch (e) {
                    console.error('Error parsing events data:', e);
                }
            }
        }
    });
}

// Initialize view on page load
function initializeView() {
    if (typeof VENUE_MODE !== 'undefined' && VENUE_MODE === 'calendar') {
        initCalendarView();
        return;
    }
    const toggle = document.getElementById('viewToggle');
    if (toggle) {
        toggle.checked = isGanttView;
        toggleView(isGanttView);
    }
}

// ─── Monthly Calendar View ───────────────────────────────────────────────────

let calendarYear = null;
let calendarMonth = null; // 0-based

function formatDuration(start, end) {
    if (!start || !end) return '';
    const diffMs = new Date(end.replace(' ', 'T')) - new Date(start.replace(' ', 'T'));
    if (diffMs <= 0) return '';
    const totalMin = Math.round(diffMs / 60000);
    const h = Math.floor(totalMin / 60);
    const m = totalMin % 60;
    if (h > 0 && m > 0) return `(${h}h ${m}m)`;
    if (h > 0) return `(${h}h)`;
    return `(${m}m)`;
}

function getStreamPlatform(url) {
    if (!url) return '';
    if (url.includes('instagram.com')) return '📷';
    if (url.includes('x.com') || url.includes('twitter.com')) return '𝕏';
    if (url.includes('youtube.com') || url.includes('youtu.be')) return '▶️';
    return '🔴';
}

function getStreamPlatformClass(url) {
    // All platforms share the same theme color; icon differentiates the platform
    return url ? 'cal-has-stream' : '';
}

// Returns local-time range string for a calendar event, or '' if not needed
function calLocalTimeRange(ev) {
    var eventTz = (typeof window.EVENT_TIMEZONE !== 'undefined') ? window.EVENT_TIMEZONE : null;
    if (!eventTz || typeof Intl === 'undefined') return '';
    var userTz;
    try { userTz = Intl.DateTimeFormat().resolvedOptions().timeZone; } catch (e) { return ''; }
    if (!userTz || userTz === eventTz) return '';

    var utcStart = ev.start_ts || 0;
    var utcEnd   = ev.end_ts   || 0;
    if (!utcStart) return '';

    var fmtOpts = { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: userTz };
    var localStart, localEnd;
    try {
        localStart = new Date(utcStart * 1000).toLocaleTimeString([], fmtOpts);
        localEnd   = utcEnd ? new Date(utcEnd * 1000).toLocaleTimeString([], fmtOpts) : localStart;
    } catch (e) { return ''; }

    return (localStart === localEnd) ? localStart : localStart + '–' + localEnd;
}

function initCalendarView() {
    const container = document.getElementById('month-calendar-view');
    if (!container) return;

    const events = window.CALENDAR_EVENTS || [];

    // Build sorted unique month keys (YYYY-MM) from events
    const monthSet = new Set();
    events.forEach(ev => { if (ev.start) monthSet.add(ev.start.substring(0, 7)); });
    window._calMonthKeys = Array.from(monthSet).sort();

    // Start at first event month, or current month
    if (window._calMonthKeys.length > 0) {
        const first = window._calMonthKeys[0].split('-');
        calendarYear  = parseInt(first[0], 10);
        calendarMonth = parseInt(first[1], 10) - 1;
    } else {
        const now = new Date();
        calendarYear  = now.getFullYear();
        calendarMonth = now.getMonth();
    }

    renderAndMountCalendar();
}

function navigateCalendar(delta) {
    const keys = window._calMonthKeys || [];
    if (keys.length === 0) return;
    const currentKey = calendarYear + '-' + String(calendarMonth + 1).padStart(2, '0');
    const idx = keys.indexOf(currentKey);
    const nextIdx = idx + delta;
    if (nextIdx < 0 || nextIdx >= keys.length) return;
    const parts = keys[nextIdx].split('-');
    calendarYear  = parseInt(parts[0], 10);
    calendarMonth = parseInt(parts[1], 10) - 1;
    renderAndMountCalendar();
}

function renderAndMountCalendar() {
    const container = document.getElementById('month-calendar-view');
    if (!container) return;
    const events = window.CALENDAR_EVENTS || [];

    // Reset registries for this render
    window._calChipEvents = [];
    window._calDayEvents  = {};

    container.innerHTML = renderMonthCalendar(events, calendarYear, calendarMonth);

    // Attach navigation handlers (close day panel on month change)
    const prevBtn = container.querySelector('.cal-nav-prev');
    const nextBtn = container.querySelector('.cal-nav-next');
    if (prevBtn) prevBtn.addEventListener('click', () => { closeDayPanel(); navigateCalendar(-1); });
    if (nextBtn) nextBtn.addEventListener('click', () => { closeDayPanel(); navigateCalendar(1); });

    // Show/hide nav buttons based on available months
    const keys = window._calMonthKeys || [];
    const currentKey = calendarYear + '-' + String(calendarMonth + 1).padStart(2, '0');
    const idx = keys.indexOf(currentKey);
    const onlyOneMonth = keys.length <= 1;
    if (prevBtn) prevBtn.style.visibility = (onlyOneMonth || idx <= 0) ? 'hidden' : 'visible';
    if (nextBtn) nextBtn.style.visibility = (onlyOneMonth || idx >= keys.length - 1) ? 'hidden' : 'visible';

    // Attach chip click → detail modal (desktop)
    container.querySelectorAll('.cal-chip').forEach(chip => {
        chip.addEventListener('click', function(e) {
            e.stopPropagation();
            const idx = parseInt(this.dataset.calidx, 10);
            const data = window._calChipEvents[idx];
            if (data) openCalendarDetailModal(data);
        });
    });

    // Attach day cell click → day panel
    container.querySelectorAll('.cal-day[data-calday]').forEach(cell => {
        cell.addEventListener('click', function(e) {
            if (e.target.closest('.cal-chip')) return;
            const dateKey = this.dataset.calday;
            // Filter directly from source (robust, avoids side-effect registry issues)
            const dayEvs = (window.CALENDAR_EVENTS || [])
                .filter(ev => ev.start && ev.start.substring(0, 10) === dateKey)
                .sort((a, b) => (a.start || '').localeCompare(b.start || ''));
            if (dayEvs.length === 0) return;
            openDayPanel(dateKey, dayEvs);
            container.querySelectorAll('.cal-day.cal-day-selected').forEach(c => c.classList.remove('cal-day-selected'));
            this.classList.add('cal-day-selected');
        });
    });

    // Re-render day panel if it was open before calendar was re-mounted (e.g. language change)
    if (window._calActiveDayKey && window._calActiveDayEvs) {
        openDayPanel(window._calActiveDayKey, window._calActiveDayEvs);
        const selectedCell = container.querySelector(`.cal-day[data-calday="${CSS.escape(window._calActiveDayKey)}"]`);
        if (selectedCell) selectedCell.classList.add('cal-day-selected');
    }
}

function renderMonthCalendar(events, year, month) {
    const lang = translations[currentLang] || translations['th'];
    const monthName = lang.months[month];
    const yearDisplay = year + (lang.yearOffset || 0);
    const daysShort = lang.daysShort || ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

    // Group events by YYYY-MM-DD key
    const byDay = {};
    events.forEach(ev => {
        if (!ev.start) return;
        const dateKey = ev.start.substring(0, 10);
        if (!byDay[dateKey]) byDay[dateKey] = [];
        byDay[dateKey].push(ev);
    });

    // Calendar grid: first day of month, number of days
    const firstDay = new Date(year, month, 1).getDay(); // 0=Sun
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const today = new Date();
    const todayKey = today.getFullYear() + '-' +
        String(today.getMonth()+1).padStart(2,'0') + '-' +
        String(today.getDate()).padStart(2,'0');

    let html = `<div class="month-calendar">`;

    // Header
    html += `<div class="cal-header">
        <button class="cal-nav-btn cal-nav-prev" aria-label="previous month">${lang['calendar.prev'] || '◀'}</button>
        <span class="cal-month-label">${monthName} ${yearDisplay}</span>
        <button class="cal-nav-btn cal-nav-next" aria-label="next month">${lang['calendar.next'] || '▶'}</button>
    </div>`;

    // Day-of-week headers
    html += `<div class="cal-grid">`;
    daysShort.forEach((d, i) => {
        const cls = i === 0 ? ' cal-dow-sun' : (i === 6 ? ' cal-dow-sat' : '');
        html += `<div class="cal-dow${cls}">${d}</div>`;
    });

    // Blank cells before first day
    for (let i = 0; i < firstDay; i++) {
        html += `<div class="cal-day cal-day-empty"></div>`;
    }

    // Day cells
    for (let d = 1; d <= daysInMonth; d++) {
        const dateKey = year + '-' + String(month+1).padStart(2,'0') + '-' + String(d).padStart(2,'0');
        const dayEvents = byDay[dateKey] || [];
        const isToday = dateKey === todayKey;
        const dow = (firstDay + d - 1) % 7;
        const isSun = dow === 0;
        const isSat = dow === 6;

        let cellCls = 'cal-day';
        if (isToday) cellCls += ' cal-day-today';
        if (isSun) cellCls += ' cal-day-sun';
        if (isSat) cellCls += ' cal-day-sat';

        const hasEvents = dayEvents.length > 0;
        if (hasEvents) {
            cellCls += ' cal-day-has-events';
            window._calDayEvents[dateKey] = dayEvents;
        }

        html += `<div class="${cellCls}"${hasEvents ? ` data-calday="${dateKey}"` : ''}>`;
        html += `<div class="cal-day-num">${d}</div>`;

        if (hasEvents) {
            // Chips (desktop)
            html += `<div class="cal-chips">`;
            dayEvents.forEach(ev => {
                const platform = getStreamPlatform(ev.stream_url);
                const platformCls = getStreamPlatformClass(ev.stream_url);
                const artist = (ev.categories || ev.organizer || ev.title || '').split(',')[0].trim();
                const timeStr = ev.start ? ev.start.substring(11, 16) : '';
                const localRange = calLocalTimeRange(ev);
                const chipIdx = window._calChipEvents.length;
                window._calChipEvents.push(ev);
                html += `<div class="cal-chip ${platformCls}${localRange ? ' cal-chip-has-local' : ''}" data-calidx="${chipIdx}" title="${escapeHtmlAttr(ev.title || '')}">`;
                if (platform) html += `<span class="cal-chip-icon">${platform}</span>`;
                html += `<span class="cal-chip-artist">${escapeHtml(artist)}</span>`;
                if (timeStr) html += `<span class="cal-chip-time">${timeStr}</span>`;
                const crossDayN = calCrossDay(ev);
                if (crossDayN > 0) html += `<span class="cal-chip-nextday">+${crossDayN}</span>`;
                if (localRange) {
                    const t = (typeof translations !== 'undefined' && translations[currentLang || 'th']) ? translations[currentLang || 'th'] : null;
                    const label = t ? (t['tz.localTime'] || 'local') : 'local';
                    html += `<span class="cal-chip-time-local">(${escapeHtml(localRange)} ${escapeHtml(label)})</span>`;
                }
                html += `</div>`;
            });
            html += `</div>`;

            // Dots (mobile) — max 3 dots then "+N"
            const dotMax = 3;
            html += `<div class="cal-day-dots">`;
            dayEvents.slice(0, dotMax).forEach(ev => {
                const cls = ev.stream_url ? ' cal-dot-stream' : '';
                html += `<span class="cal-dot${cls}"></span>`;
            });
            if (dayEvents.length > dotMax) {
                html += `<span class="cal-dot-more">+${dayEvents.length - dotMax}</span>`;
            }
            html += `</div>`;
        }

        html += `</div>`;
    }

    // Trailing blank cells to complete last row
    const totalCells = firstDay + daysInMonth;
    const trailing = (7 - (totalCells % 7)) % 7;
    for (let i = 0; i < trailing; i++) {
        html += `<div class="cal-day cal-day-empty"></div>`;
    }

    html += `</div></div>`; // close cal-grid, month-calendar
    return html;
}

function calCrossDay(ev) {
    if (!ev.start || !ev.end) return 0;
    const startDay = ev.start.substring(0, 10);
    const endDay   = ev.end.substring(0, 10);
    if (startDay === endDay) return 0;
    return Math.round((new Date(endDay) - new Date(startDay)) / 86400000);
}

function escapeHtmlAttr(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

// Calendar detail modal
function openCalendarDetailModal(ev) {
    const platform = getStreamPlatform(ev.stream_url);
    const timeStart = ev.start ? ev.start.substring(11, 16) : '';
    const timeEnd   = ev.end   ? ev.end.substring(11, 16)   : '';
    const timeRange = (timeStart && timeEnd && timeStart !== timeEnd) ? `${timeStart} – ${timeEnd}` : timeStart;

    const crossDayN = calCrossDay(ev);
    const duration = formatDuration(ev.start, ev.end);
    const localRange = calLocalTimeRange(ev);
    const t = (typeof translations !== 'undefined' && translations[currentLang || 'th']) ? translations[currentLang || 'th'] : null;
    const localLabel = t ? (t['tz.localTime'] || 'local') : 'local';

    let body = `<div class="cal-detail-modal-inner">`;
    if (timeRange) body += `<h3 class="cal-detail-title">${escapeHtml(timeRange)}${crossDayN > 0 ? ` <span class="cal-chip-nextday">+${crossDayN}</span>` : ''}${duration ? ` <span class="cal-detail-duration">${escapeHtml(duration)}</span>` : ''}</h3>`;
    if (localRange) body += `<div class="cal-detail-time-local">(${escapeHtml(localRange)} ${escapeHtml(localLabel)})</div>`;
    if (ev.location) body += `<div class="cal-detail-row">📍 ${escapeHtml(ev.location)}</div>`;
    if (ev.categories) body += `<div class="cal-detail-row">🎤 ${escapeHtml(ev.categories)}</div>`;
    if (ev.program_type) body += `<div class="cal-detail-row">🏷️ ${escapeHtml(ev.program_type)}</div>`;
    if (ev.description) body += `<div class="cal-detail-desc">${escapeHtml(ev.description)}</div>`;
    if (ev.stream_url) {
        body += `<a class="cal-detail-join btn btn-danger" href="${escapeHtmlAttr(ev.stream_url)}" target="_blank" rel="noopener noreferrer">${platform} Join Live</a>`;
    }
    body += `</div>`;

    openModal(body, ev.title || '');
}

// Generic modal for calendar detail (reuses request modal overlay)
function openModal(bodyHtml, titleText) {
    let overlay = document.getElementById('calDetailOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'calDetailOverlay';
        overlay.className = 'req-modal-overlay';
        overlay.innerHTML = `<div class="req-modal" style="max-width:440px;">
            <div class="req-modal-header" style="padding:10px 15px;">
                <span id="calDetailHeaderTitle" class="cal-detail-header-title"></span>
                <button class="req-close" onclick="closeCalDetailModal()" aria-label="close">✕</button>
            </div>
            <div class="req-modal-body" id="calDetailBody"></div>
        </div>`;
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) closeCalDetailModal();
        });
        document.body.appendChild(overlay);
    }
    document.getElementById('calDetailHeaderTitle').textContent = titleText || '';
    document.getElementById('calDetailBody').innerHTML = bodyHtml;
    overlay.classList.add('active');
}

function closeCalDetailModal() {
    const overlay = document.getElementById('calDetailOverlay');
    if (overlay) overlay.classList.remove('active');
}

// Day panel (mobile: tap day → list of programs)
function openDayPanel(dateKey, dayEvs) {
    window._calActiveDayKey = dateKey;
    window._calActiveDayEvs = dayEvs;

    const lang = translations[currentLang] || translations['th'];

    // Format date label
    const [y, m, d] = dateKey.split('-').map(Number);
    const monthName = lang.months ? lang.months[m - 1] : m;
    const yearDisplay = y + (lang.yearOffset || 0);
    const dateLabel = `${d} ${monthName} ${yearDisplay}`;

    // Panel-specific event registry (avoids dependency on chip registry)
    window._calDpEvents = [];

    let itemsHtml = '';
    dayEvs.forEach(ev => {
        const platform = getStreamPlatform(ev.stream_url);
        const artist = (ev.categories || ev.organizer || '').split(',').map(s => s.trim()).filter(Boolean).join(', ');
        const timeStart = ev.start ? ev.start.substring(11, 16) : '';
        const timeEnd   = ev.end   ? ev.end.substring(11, 16)   : '';
        const timeRange = (timeStart && timeEnd && timeStart !== timeEnd) ? `${timeStart} – ${timeEnd}` : timeStart;
        const crossDayN = calCrossDay(ev);
        const duration  = formatDuration(ev.start, ev.end);
        const localRange = calLocalTimeRange(ev);
        const dpIdx = window._calDpEvents.length;
        window._calDpEvents.push(ev);

        itemsHtml += `<div class="cal-dp-item" data-dpidx="${dpIdx}">`;

        // Left: platform icon
        itemsHtml += `<div class="cal-dp-item-left"><span class="cal-dp-icon">${platform || '📅'}</span></div>`;

        // Info block
        itemsHtml += `<div class="cal-dp-item-info">`;

        itemsHtml += `<div class="cal-dp-item-title">${escapeHtml(ev.title || artist || '—')}</div>`;
        if (artist)     itemsHtml += `<div class="cal-dp-item-artist">${escapeHtml(artist)}</div>`;
        if (timeRange)  itemsHtml += `<div class="cal-dp-item-time">🕐 ${escapeHtml(timeRange)}${crossDayN > 0 ? ` <span class="cal-chip-nextday">+${crossDayN}</span>` : ''}${duration ? ` <span class="cal-detail-duration">${escapeHtml(duration)}</span>` : ''}</div>`;
        if (localRange) {
            const t = (typeof translations !== 'undefined' && translations[currentLang || 'th']) ? translations[currentLang || 'th'] : null;
            const label = t ? (t['tz.localTime'] || 'local') : 'local';
            itemsHtml += `<div class="cal-dp-item-time-local">(${escapeHtml(localRange)} ${escapeHtml(label)})</div>`;
        }
        if (ev.program_type) itemsHtml += `<span class="cal-dp-item-type">${escapeHtml(ev.program_type)}</span>`;
        if (ev.description)  itemsHtml += `<div class="cal-dp-item-desc">${escapeHtml(ev.description)}</div>`;
        if (ev.stream_url) {
            itemsHtml += `<div class="cal-dp-join-wrap"><a class="cal-dp-join btn btn-danger btn-sm" href="${escapeHtmlAttr(ev.stream_url)}" target="_blank" rel="noopener noreferrer">🔴 Live</a></div>`;
        }

        itemsHtml += `</div>`; // info
        itemsHtml += `</div>`; // item
    });

    // Create or reuse panel
    let panel = document.getElementById('calDayPanel');
    if (!panel) {
        panel = document.createElement('div');
        panel.id = 'calDayPanel';
        const calView = document.getElementById('month-calendar-view');
        calView.parentNode.insertBefore(panel, calView.nextSibling);
    }

    panel.innerHTML = `
        <div class="cal-dp-header">
            <span class="cal-dp-date">${escapeHtml(dateLabel)}</span>
            <button class="req-close" onclick="closeDayPanel()" aria-label="close">✕</button>
        </div>
        <div class="cal-dp-body">${itemsHtml}</div>`;

    panel.classList.add('active');

    // Item click → detail modal (uses panel registry, not chip registry)
    panel.querySelectorAll('.cal-dp-item').forEach(item => {
        item.addEventListener('click', function(e) {
            if (e.target.closest('.cal-dp-join')) return;
            const idx = parseInt(this.dataset.dpidx, 10);
            const ev = window._calDpEvents[idx];
            if (ev) openCalendarDetailModal(ev);
        });
    });

    panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function closeDayPanel() {
    const panel = document.getElementById('calDayPanel');
    if (panel) panel.classList.remove('active');
    const calView = document.getElementById('month-calendar-view');
    if (calView) calView.querySelectorAll('.cal-day-selected').forEach(c => c.classList.remove('cal-day-selected'));
    window._calActiveDayKey = null;
    window._calActiveDayEvs = null;
}

// Show/hide gradient shadow at edges of .gantt-view when content overflows horizontally
function setupGanttScrollIndicator(container) {
    function update() {
        var hasOverflow = container.scrollWidth > container.clientWidth + 1;
        var atStart = container.scrollLeft <= 1;
        var atEnd = container.scrollLeft + container.clientWidth >= container.scrollWidth - 1;
        container.classList.toggle('has-scroll-right', hasOverflow && !atEnd);
        container.classList.toggle('has-scroll-left', hasOverflow && !atStart);
    }
    container.addEventListener('scroll', update);
    // Initial check after layout settles
    setTimeout(update, 60);
    // Also re-check on resize (orientation change on iOS)
    window.addEventListener('resize', update);
}

// Calculate position and width for an event bar
function calculateEventPosition(startTime, endTime, startHour, endHour) {
    const totalMinutes = (endHour - startHour) * 60;

    const [startH, startM] = startTime.split(':').map(Number);
    const [endH, endM] = endTime.split(':').map(Number);

    let startMinutes = (startH - startHour) * 60 + startM;
    let endMinutes = (endH - startHour) * 60 + endM;

    // Clamp to bounds
    startMinutes = Math.max(0, startMinutes);
    endMinutes = Math.min(totalMinutes, endMinutes);

    const left = (startMinutes / totalMinutes) * 100;
    const width = ((endMinutes - startMinutes) / totalMinutes) * 100;

    return {
        left: Math.max(0, left),
        width: Math.max(width, 2) // Minimum width 2%
    };
}

// Extract venue name from full location string
function extractVenueName(location) {
    if (!location) return null;
    return location;
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Render Vertical Gantt chart HTML (time as Y-axis, venues as columns)
function renderGanttChart(events) {
    const lang = translations[currentLang];
    const venues = getVenues();

    // Find actual time range from events
    let minHour = 24;
    let maxHour = 0;

    events.forEach(event => {
        const startTime = event.start.includes('T')
            ? event.start.split('T')[1].substring(0, 5)
            : event.start.substring(11, 16);
        const endTime = event.end.includes('T')
            ? event.end.split('T')[1].substring(0, 5)
            : event.end.substring(11, 16);

        const startH = parseInt(startTime.split(':')[0]);
        const endH = parseInt(endTime.split(':')[0]);
        minHour = Math.min(minHour, startH);
        maxHour = Math.max(maxHour, endH);
    });

    // Use actual range with padding
    const startHour = minHour < 24 ? Math.max(0, minHour) : GANTT_CONFIG.startHour;
    const endHour = maxHour > 0 ? Math.min(24, maxHour + 1) : GANTT_CONFIG.endHour;
    const totalHours = endHour - startHour;

    // Group events by venue
    const eventsByVenue = {};
    const venueSet = new Set();

    events.forEach(event => {
        const venue = event.location || 'Other';
        venueSet.add(venue);
        if (!eventsByVenue[venue]) {
            eventsByVenue[venue] = [];
        }
        eventsByVenue[venue].push(event);
    });

    // Use venues from data, fallback to discovered venues
    // Only show venues that have events (hide empty venues)
    const venueList = venues.length > 0
        ? venues.filter(v => eventsByVenue[v] && eventsByVenue[v].length > 0)
        : Array.from(venueSet);

    // Slot height in pixels (must match CSS)
    const isMobile = window.innerWidth <= 768;
    const slotHeight = isMobile ? 65 : 80;
    const totalHeight = totalHours * slotHeight;

    // Calculate total min-width matching CSS column widths
    // This ensures the wrapper itself is wide enough so flex children don't overflow it,
    // pushing horizontal scroll up to .gantt-view (parent) instead.
    // Fixes iOS Safari sticky header not scrolling with content.
    const timeAxisWidth = isMobile ? 50 : 60;
    const venueColWidth = isMobile ? 80 : 100;
    const totalMinWidth = timeAxisWidth + venueList.length * venueColWidth;

    // Build HTML - Vertical layout
    let html = `<div class="gantt-chart vertical" style="min-width: ${totalMinWidth}px;">`;

    // Header with venue names
    html += `<div class="gantt-header-vertical">`;
    html += `<div class="gantt-time-label">${lang['table.time'] || 'Time'}</div>`;
    venueList.forEach(venue => {
        const shortVenueName = venue.replace(' STAGE', '').replace('Stage', '');
        html += `<div class="gantt-venue-header">${escapeHtml(shortVenueName)}</div>`;
    });
    html += `</div>`;

    // Body with time rows - set explicit height
    html += `<div class="gantt-body-vertical" style="height: ${totalHeight}px;">`;

    // Time axis column
    html += `<div class="gantt-time-axis">`;
    for (let hour = startHour; hour < endHour; hour++) {
        const timeStr = hour.toString().padStart(2, '0') + ':00';
        html += `<div class="gantt-time-slot">${timeStr}</div>`;
    }
    html += `</div>`;

    // Venue columns
    venueList.forEach(venue => {
        const venueEvents = eventsByVenue[venue] || [];

        html += `<div class="gantt-venue-column" data-venue="${escapeHtml(venue)}" style="height: ${totalHeight}px;">`;

        // Grid lines
        html += `<div class="gantt-grid-vertical">`;
        for (let hour = startHour; hour < endHour; hour++) {
            html += `<div class="gantt-grid-slot"></div>`;
        }
        html += `</div>`;

        // Detect overlaps within this venue
        const overlaps = detectOverlaps(venueEvents);

        // Event bars (vertical positioning)
        venueEvents.forEach((event, idx) => {
            const startTime = event.start.includes('T')
                ? event.start.split('T')[1].substring(0, 5)
                : event.start.substring(11, 16);
            const endTime = event.end.includes('T')
                ? event.end.split('T')[1].substring(0, 5)
                : event.end.substring(11, 16);

            const position = calculateEventPositionVertical(startTime, endTime, startHour, endHour);
            const title = event.title || '';
            const categories = event.categories || '';
            const description = event.description || '';
            const programType = event.program_type || '';

            // Check overlap and assign stack position
            const overlapInfo = overlaps[idx] || { hasOverlap: false, stackIndex: 0, stackTotal: 1 };
            let stackClass = '';
            let stackInlineStyle = `top: ${position.top}%; height: ${position.height}%;`;
            if (overlapInfo.hasOverlap) {
                stackClass = ' has-overlap';
                const n = overlapInfo.stackTotal;
                const i = overlapInfo.stackIndex;
                const leftPct  = (i / n * 100).toFixed(2);
                const rightPct = ((n - i - 1) / n * 100).toFixed(2);
                stackInlineStyle += ` left: calc(${leftPct}% + 2px); right: calc(${rightPct}% + 2px);`;
            }

            html += `
                <div class="gantt-program-vertical${stackClass}"
                     style="${stackInlineStyle}"
                     data-start="${startTime}"
                     data-end="${endTime}"
                     data-title="${escapeHtml(title)}"
                     data-venue="${escapeHtml(venue)}"
                     data-categories="${escapeHtml(categories)}"
                     data-description="${escapeHtml(description)}"
                     data-program-type="${escapeHtml(programType)}"
                     onclick="showEventTooltip(this, event)">
                    <div class="gantt-program-row-v">
                        <span class="gantt-program-time-v">${startTime}</span>
                        <span class="gantt-program-title-v">${escapeHtml(title)}</span>
                    </div>
                    ${programType ? `<div class="gantt-program-type-v">${escapeHtml(programType)}</div>` : ''}
                </div>
            `;
        });

        html += `</div>`;
    });

    html += `</div>`;

    // Legend
    html += `
        <div class="gantt-legend">
            <div class="gantt-legend-item">
                <div class="gantt-legend-bar"></div>
                <span>Program</span>
            </div>
            <div class="gantt-legend-item">
                <div class="gantt-legend-bar overlap"></div>
                <span>Overlap</span>
            </div>
        </div>
    `;

    html += `</div>`;

    return html;
}

// Calculate position for vertical layout (top and height as percentages)
function calculateEventPositionVertical(startTime, endTime, startHour, endHour) {
    const totalMinutes = (endHour - startHour) * 60;

    const [startH, startM] = startTime.split(':').map(Number);
    const [endH, endM] = endTime.split(':').map(Number);

    let startMinutes = (startH - startHour) * 60 + startM;
    let endMinutes = (endH - startHour) * 60 + endM;

    // Clamp to bounds
    startMinutes = Math.max(0, startMinutes);
    endMinutes = Math.min(totalMinutes, endMinutes);

    const top = (startMinutes / totalMinutes) * 100;
    const height = ((endMinutes - startMinutes) / totalMinutes) * 100;

    // Minimum height 4% (~29 minutes in 12-hour timeline) to ensure text is readable
    // CSS min-height provides additional pixel-based minimum
    return {
        top: Math.max(0, top),
        height: Math.max(height, 4)
    };
}

// Detect overlapping events within same venue
function detectOverlaps(events) {
    const overlaps = {};

    events.forEach((event, i) => {
        overlaps[i] = { hasOverlap: false, stackIndex: 0, stackTotal: 1, overlappingWith: [] };
    });

    // Check each pair for overlaps
    for (let i = 0; i < events.length; i++) {
        for (let j = i + 1; j < events.length; j++) {
            const startA = getTimeMinutes(events[i].start);
            const endA = getTimeMinutes(events[i].end);
            const startB = getTimeMinutes(events[j].start);
            const endB = getTimeMinutes(events[j].end);

            // Check if they overlap (not just touch)
            if (startA < endB && endA > startB) {
                overlaps[i].hasOverlap = true;
                overlaps[j].hasOverlap = true;
                overlaps[i].overlappingWith.push(j);
                overlaps[j].overlappingWith.push(i);
            }
        }
    }

    // Assign stack positions for overlapping events
    const assigned = new Set();
    events.forEach((event, i) => {
        if (overlaps[i].hasOverlap && !assigned.has(i)) {
            const group = [i, ...overlaps[i].overlappingWith];
            const uniqueGroup = [...new Set(group)].sort((a, b) => a - b);

            uniqueGroup.forEach((idx, stackIdx) => {
                overlaps[idx].stackIndex = stackIdx;
                overlaps[idx].stackTotal = uniqueGroup.length;
                assigned.add(idx);
            });
        }
    });

    return overlaps;
}

// Convert time string to minutes since midnight
function getTimeMinutes(timeStr) {
    const time = timeStr.includes('T')
        ? timeStr.split('T')[1].substring(0, 5)
        : timeStr.substring(11, 16);
    const [h, m] = time.split(':').map(Number);
    return h * 60 + m;
}

// Event tooltip functionality
let tooltipTimeout;
let currentTooltip = null;

function showEventTooltip(element, e) {
    clearTimeout(tooltipTimeout);

    // Remove existing tooltip
    if (currentTooltip) {
        currentTooltip.remove();
    }

    const tooltip = document.createElement('div');
    tooltip.className = 'gantt-program-tooltip show';
    currentTooltip = tooltip;

    const title = element.dataset.title;
    const venue = element.dataset.venue;
    const startTime = element.dataset.start;
    const endTime = element.dataset.end;
    const categories = element.dataset.categories;
    const description = element.dataset.description;
    const lang = translations[currentLang];

    // สร้าง tooltip ด้วย DOM methods แทน innerHTML เพื่อป้องกัน XSS
    const closeBtn = document.createElement('button');
    closeBtn.className = 'tooltip-close';
    closeBtn.setAttribute('aria-label', 'Close');
    closeBtn.textContent = '×';
    closeBtn.onclick = closeTooltip;
    tooltip.appendChild(closeBtn);

    const titleEl = document.createElement('h4');
    titleEl.textContent = title;
    tooltip.appendChild(titleEl);

    if (venue) {
        const venueP = document.createElement('p');
        const venueStrong = document.createElement('strong');
        venueStrong.textContent = (lang['table.venue'] || 'Venue') + ':';
        venueP.appendChild(venueStrong);
        venueP.appendChild(document.createTextNode(' ' + venue));
        tooltip.appendChild(venueP);
    }

    const timeP = document.createElement('p');
    const timeStrong = document.createElement('strong');
    timeStrong.textContent = (lang['table.time'] || 'Time') + ':';
    timeP.appendChild(timeStrong);
    timeP.appendChild(document.createTextNode(' ' + (startTime === endTime ? startTime : startTime + ' - ' + endTime)));
    tooltip.appendChild(timeP);

    if (categories) {
        const catP = document.createElement('p');
        const catStrong = document.createElement('strong');
        catStrong.textContent = (lang['table.categories'] || 'Artists') + ':';
        catP.appendChild(catStrong);
        catP.appendChild(document.createTextNode(' ' + categories));
        tooltip.appendChild(catP);
    }

    if (description) {
        const descP = document.createElement('p');
        descP.style.cssText = 'font-size: 0.85em; color: #6c757d; margin-top: 8px;';
        descP.textContent = description;
        tooltip.appendChild(descP);
    }

    document.body.appendChild(tooltip);

    // Position tooltip
    const rect = element.getBoundingClientRect();
    const tooltipRect = tooltip.getBoundingClientRect();

    // Calculate position
    let left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
    let top = rect.bottom + 8;

    // Keep within viewport
    if (left < 10) left = 10;
    if (left + tooltipRect.width > window.innerWidth - 10) {
        left = window.innerWidth - tooltipRect.width - 10;
    }
    if (top + tooltipRect.height > window.innerHeight - 10) {
        top = rect.top - tooltipRect.height - 8;
    }

    tooltip.style.left = `${left}px`;
    tooltip.style.top = `${top}px`;
}

function hideEventTooltip() {
    tooltipTimeout = setTimeout(() => {
        if (currentTooltip) {
            currentTooltip.remove();
            currentTooltip = null;
        }
    }, 150);
}

// Close tooltip immediately
function closeTooltip() {
    clearTimeout(tooltipTimeout);
    if (currentTooltip) {
        currentTooltip.remove();
        currentTooltip = null;
    }
}

// Close tooltip when clicking outside
document.addEventListener('click', function(e) {
    if (currentTooltip && !currentTooltip.contains(e.target) &&
        !e.target.closest('.gantt-program') && !e.target.closest('.gantt-program-vertical')) {
        closeTooltip();
    }
});

// Initialize language on page load
document.addEventListener('DOMContentLoaded', function() {
    changeLanguage(currentLang);
    initializeView();
    injectFavNavButton();
    initTimezoneDisplay();
});

// Show local-timezone equivalents when user's browser TZ differs from event TZ
function initTimezoneDisplay() {
    var eventTz = (typeof window.EVENT_TIMEZONE !== 'undefined') ? window.EVENT_TIMEZONE : null;
    if (!eventTz || typeof Intl === 'undefined') return;

    var userTz;
    try { userTz = Intl.DateTimeFormat().resolvedOptions().timeZone; } catch (e) { return; }
    if (!userTz || userTz === eventTz) return;

    // Update timezone badge: show inline client TZ when it differs from event TZ
    var badge = document.getElementById('eventTimezoneDisplay');
    if (badge) {
        badge.textContent = '🕐 ' + eventTz + ' (' + userTz + ')';
    }

    // Add local-time labels next to each program time (skip if already added)
    document.querySelectorAll('.program-time[data-utc]').forEach(function(el) {
        if (el.nextSibling && el.nextSibling.classList && el.nextSibling.classList.contains('program-time-local')) return;

        var utcStart = parseInt(el.getAttribute('data-utc'), 10);
        var utcEnd   = parseInt(el.getAttribute('data-utc-end') || '0', 10);
        if (!utcStart) return;

        var fmtOpts = { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: userTz };
        var localStart, localEnd;
        try {
            localStart = new Date(utcStart * 1000).toLocaleTimeString([], fmtOpts);
            localEnd   = utcEnd ? new Date(utcEnd * 1000).toLocaleTimeString([], fmtOpts) : localStart;
        } catch (e) { return; }

        var localRange = (localStart === localEnd) ? localStart : localStart + '–' + localEnd;

        var localSpan = document.createElement('span');
        localSpan.className = 'program-time-local';
        localSpan.setAttribute('data-localtime', localRange);
        var label = (typeof translations !== 'undefined' && translations[currentLang || 'th'])
            ? (translations[currentLang || 'th']['tz.localTime'] || 'local')
            : 'local';
        localSpan.textContent = '(' + localRange + ' ' + label + ')';
        el.parentNode.insertBefore(localSpan, el.nextSibling);
    });
}

function updateTimezoneLabels(lang) {
    var eventTz = (typeof window.EVENT_TIMEZONE !== 'undefined') ? window.EVENT_TIMEZONE : null;
    if (!eventTz) return;

    var userTz;
    try { userTz = Intl.DateTimeFormat().resolvedOptions().timeZone; } catch (e) { return; }
    if (!userTz || userTz === eventTz) return;

    var t = (typeof translations !== 'undefined' && translations[lang]) ? translations[lang] : null;

    // Update label text in existing spans (time stays the same, only label word changes)
    var label = t ? (t['tz.localTime'] || 'local') : 'local';
    document.querySelectorAll('.program-time-local[data-localtime]').forEach(function(span) {
        var localTime = span.getAttribute('data-localtime');
        span.textContent = '(' + localTime + ' ' + label + ')';
    });
}

document.addEventListener('appLangChange', function(e) {
    updateTimezoneLabels(e.detail.lang);
});

// Inject ⭐ My Favorites shortcut into header when fav_slug exists in localStorage
function injectFavNavButton() {
    const slug = localStorage.getItem('fav_slug');
    if (!slug) return;

    const topLeft = document.querySelector('.header-top-left');
    if (!topLeft) return;

    // Don't inject on the favorites pages themselves
    const path = window.location.pathname;
    if (/\/my(-favorites)?(\/|\.php|$)/.test(path)) return;

    const base = (typeof BASE_PATH !== 'undefined' ? BASE_PATH : '');

    // ⭐ My Favorites → /my-favorites/{slug}
    const aFav = document.createElement('a');
    aFav.href = base + '/my-favorites/' + encodeURIComponent(slug);
    aFav.className = 'home-icon-btn';
    aFav.title = 'My Favorites';
    aFav.setAttribute('aria-label', 'My Favorites');
    aFav.textContent = '⭐';
    topLeft.appendChild(aFav);

    // 📅 My Upcoming Programs → /my/{slug}
    const aDash = document.createElement('a');
    aDash.href = base + '/my/' + encodeURIComponent(slug);
    aDash.className = 'home-icon-btn';
    aDash.title = 'My Upcoming Programs';
    aDash.setAttribute('aria-label', 'My Upcoming Programs');
    aDash.textContent = '📅';
    topLeft.appendChild(aDash);

    // Silent background validation — remove stale slug if server rejects it
    fetch(base + '/api/favorites?action=get&slug=' + encodeURIComponent(slug))
        .then(function(res) {
            if (res.status === 400 || res.status === 404) {
                localStorage.removeItem('fav_slug');
                aFav.remove();
                aDash.remove();
            }
        })
        .catch(function() { /* network error — leave buttons, retry next page */ });
}
