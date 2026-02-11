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
    domCache.eventTimes = document.querySelectorAll('.event-time');
}
document.addEventListener('DOMContentLoaded', populateDomCache);

// Change language
function changeLanguage(lang) {
    currentLang = lang;
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

    // Update day names
    const dayNameEls = domCache.dayNames || document.querySelectorAll('.day-name');
    dayNameEls.forEach(el => {
        const dayNum = parseInt(el.dataset.day);
        el.textContent = lang.days[dayNum];
    });

    // Update time format (for index page)
    const eventTimeEls = domCache.eventTimes || document.querySelectorAll('.event-time');
    eventTimeEls.forEach(el => {
        const startTime = el.dataset.start;
        const endTime = el.dataset.end;
        const formattedStart = formatTime(startTime, lang.timeFormat, lang['time.unit']);
        const formattedEnd = formatTime(endTime, lang.timeFormat, lang['time.unit']);
        el.textContent = formattedStart + ' - ' + formattedEnd;
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

// Lazy load html2canvas
let html2canvasLoaded = false;
let html2canvasLoading = false;

function loadHtml2Canvas() {
    return new Promise((resolve, reject) => {
        if (html2canvasLoaded) {
            resolve();
            return;
        }

        if (html2canvasLoading) {
            // Wait for existing load to complete
            const checkInterval = setInterval(() => {
                if (html2canvasLoaded) {
                    clearInterval(checkInterval);
                    resolve();
                }
            }, 100);
            return;
        }

        html2canvasLoading = true;
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
        script.onload = () => {
            html2canvasLoaded = true;
            html2canvasLoading = false;
            resolve();
        };
        script.onerror = () => {
            html2canvasLoading = false;
            reject(new Error('Failed to load html2canvas'));
        };
        document.head.appendChild(script);
    });
}

// Save as image function with lazy loading
async function saveAsImage() {
    const button = event.target;
    button.disabled = true;
    const originalText = button.textContent;
    button.textContent = translations[currentLang]['message.generating'];

    try {
        // Lazy load html2canvas
        await loadHtml2Canvas();

        const container = document.querySelector('.container');
        const filtersSection = document.querySelector('.filters');
        const isMobile = window.innerWidth <= 768;

        // Store original styles
        const originalBackground = document.body.style.background;
        const originalPadding = document.body.style.padding;
        const originalFiltersDisplay = filtersSection ? filtersSection.style.display : null;

        // Hide filters temporarily
        if (filtersSection) filtersSection.style.display = 'none';

        // Adjust styles temporarily for better image
        document.body.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
        document.body.style.padding = isMobile ? '10px' : '20px';

        // html2canvas options
        const canvasOptions = {
            scale: 2,
            backgroundColor: null,
            logging: false,
            useCORS: true,
            allowTaint: true,
            windowWidth: isMobile ? window.innerWidth : 1200,
            width: isMobile ? container.scrollWidth : null,
            onclone: function(clonedDoc) {
                const clonedContainer = clonedDoc.querySelector('.container');
                if (clonedContainer) {
                    if (isMobile) {
                        clonedContainer.style.margin = '0';
                        clonedContainer.style.maxWidth = '100%';
                    } else {
                        clonedContainer.style.margin = '0 auto';
                        clonedContainer.style.maxWidth = '1200px';
                    }
                }
                // ซ่อน column แจ้งแก้ไข
                clonedDoc.querySelectorAll('.col-edit-request, .event-action-cell').forEach(function(el) {
                    el.style.display = 'none';
                });
            }
        };

        // Generate image
        const canvas = await html2canvas(container, canvasOptions);

        // Restore original styles
        document.body.style.background = originalBackground;
        document.body.style.padding = originalPadding;
        if (filtersSection) filtersSection.style.display = originalFiltersDisplay;

        // Create filename
        const date = new Date();
        const dateStr = date.toISOString().split('T')[0];
        const deviceType = isMobile ? 'mobile' : 'desktop';
        const filename = `stage-idol-calendar-${dateStr}-${deviceType}.png`;

        // Download image
        canvas.toBlob(function(blob) {
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.download = filename;
            link.href = url;
            link.click();
            URL.revokeObjectURL(url);

            button.disabled = false;
            button.textContent = originalText;
            showNotification(translations[currentLang]['message.success']);
        });

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
        if (key === 'artist[]' || key === 'venue[]') {
            params.append(key, value);
        }
    }

    window.location.href = 'export.php?' + params.toString();
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
    const toggle = document.getElementById('viewToggle');
    if (toggle) {
        toggle.checked = isGanttView;
        toggleView(isGanttView);
    }
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

            // Check overlap and assign stack position
            const overlapInfo = overlaps[idx] || { hasOverlap: false, stackIndex: 0, stackTotal: 1 };
            const stackClass = overlapInfo.hasOverlap ? ` has-overlap stack-h-${overlapInfo.stackIndex + 1}` : '';

            html += `
                <div class="gantt-event-vertical${stackClass}"
                     style="top: ${position.top}%; height: ${position.height}%;"
                     data-start="${startTime}"
                     data-end="${endTime}"
                     data-title="${escapeHtml(title)}"
                     data-venue="${escapeHtml(venue)}"
                     data-categories="${escapeHtml(categories)}"
                     data-description="${escapeHtml(description)}"
                     onclick="showEventTooltip(this, event)">
                    <div class="gantt-event-time-v">${startTime}</div>
                    <div class="gantt-event-title-v">${escapeHtml(title)}</div>
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
                <span>Event</span>
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
    tooltip.className = 'gantt-event-tooltip show';
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
    timeP.appendChild(document.createTextNode(' ' + startTime + ' - ' + endTime));
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
        !e.target.closest('.gantt-event') && !e.target.closest('.gantt-event-vertical')) {
        closeTooltip();
    }
});

// Initialize language on page load
document.addEventListener('DOMContentLoaded', function() {
    changeLanguage(currentLang);
    initializeView();
});
