<?php
require_once 'config.php';
require_once 'IcsParser.php';

// Security headers (excluding X-Frame-Options for download)
header('X-Content-Type-Options: nosniff');

// Multi-event support
$eventSlug = get_current_event_slug();
$eventMeta = get_event_by_slug($eventSlug);

// If a specific slug was requested but the event doesn't exist or is inactive,
// return 404 instead of silently exporting all programs.
if ($eventSlug !== DEFAULT_EVENT_SLUG && $eventMeta === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Event not found or inactive.";
    exit;
}

$eventId   = $eventMeta ? intval($eventMeta['id']) : null;
$eventName = $eventMeta ? $eventMeta['name'] : 'Idol Stage Event';

// สร้าง IcsParser instance
$parser = new IcsParser('ics', true, 'data/calendar.db', $eventId);

// ดึงข้อมูลทั้งหมด
$allEvents = $parser->getAllEvents();

// รับค่า filter จาก GET parameters with sanitization
$filterArtists = get_sanitized_array_param('artist', 200, 50);
$filterVenues = get_sanitized_array_param('venue', 200, 50);
$filterTypes = get_sanitized_array_param('type', 200, 50);

// กรองข้อมูล (ใช้ CATEGORIES - รองรับหลายค่าแยกด้วย comma)
$filteredEvents = array_filter($allEvents, function($event) use ($filterArtists, $filterVenues, $filterTypes) {
    // ตรวจสอบ artist/categories (รองรับหลายค่าแยกด้วย comma)
    $artistMatch = empty($filterArtists);
    if (!$artistMatch && isset($event['categories'])) {
        // แยก categories ด้วย comma
        $eventCategories = array_map('trim', explode(',', $event['categories']));
        // ตรวจสอบว่ามี category ใดที่ตรงกับ filter หรือไม่
        foreach ($filterArtists as $filterArtist) {
            if (in_array($filterArtist, $eventCategories)) {
                $artistMatch = true;
                break;
            }
        }
    }

    $venueMatch = empty($filterVenues) || (isset($event['location']) && in_array($event['location'], $filterVenues));
    $typeMatch = empty($filterTypes) || in_array($event['program_type'] ?? '', $filterTypes);
    return $artistMatch && $venueMatch && $typeMatch;
});

// สร้างชื่อไฟล์
$slugSuffix = ($eventSlug && $eventSlug !== DEFAULT_EVENT_SLUG) ? '-' . $eventSlug : '';
$filename = 'stage-idol-calendar' . $slugSuffix . '-' . date('Y-m-d') . '.ics';

// ตั้งค่า headers สำหรับดาวน์โหลดไฟล์
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');


// นับจำนวน events สำหรับ debug
$totalEvents = count($allEvents);
$exportedEvents = count($filteredEvents);

// เริ่มสร้างเนื้อหา ICS
echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
$siteTitle = get_site_title();
echo "PRODID:-//" . $siteTitle . "//NONSGML v1.0//EN\r\n";
echo "CALSCALE:GREGORIAN\r\n";
echo "METHOD:PUBLISH\r\n";
echo "X-WR-CALNAME:" . ($eventName ? $eventName . " - " : "") . $siteTitle . "\r\n";
echo "X-WR-TIMEZONE:Asia/Bangkok\r\n";
echo "X-WR-CALDESC:Exported events from " . $siteTitle . " ($exportedEvents of $totalEvents events)\r\n";

// เพิ่ม events
foreach ($filteredEvents as $event) {
    // แปลง datetime เป็นรูปแบบ ICS (YYYYMMDDTHHmmssZ)
    // ใช้ gmdate() เพื่อแปลงเป็น UTC timezone
    $startTime = gmdate('Ymd\THis\Z', strtotime($event['start']));
    $endTime = gmdate('Ymd\THis\Z', strtotime($event['end']));
    $createdTime = gmdate('Ymd\THis\Z');

    // สร้าง UID ที่ unique
    $uid = isset($event['uid']) ? $event['uid'] : md5($event['title'] . $event['start']) . '@stageidol.local';

    echo "BEGIN:VEVENT\r\n";
    echo "UID:" . $uid . "\r\n";
    echo "DTSTAMP:" . $createdTime . "\r\n";
    echo "DTSTART:" . $startTime . "\r\n";
    echo "DTEND:" . $endTime . "\r\n";
    echo "SUMMARY:" . escapeIcsValue($event['title']) . "\r\n";

    if (!empty($event['location'])) {
        echo "LOCATION:" . escapeIcsValue($event['location']) . "\r\n";
    }

    // ORGANIZER = ข้อมูลของงาน (event) ที่ program นี้สังกัด
    if ($eventMeta && !empty($eventMeta['name'])) {
        $orgName  = escapeIcsValue($eventMeta['name']);
        $orgEmail = (!empty($eventMeta['email']) && filter_var($eventMeta['email'], FILTER_VALIDATE_EMAIL))
            ? $eventMeta['email']
            : 'noreply@stageidol.local';
        echo "ORGANIZER;CN=\"" . $orgName . "\":mailto:" . $orgEmail . "\r\n";
    }

    if (!empty($event['description'])) {
        echo "DESCRIPTION:" . escapeIcsValue($event['description']) . "\r\n";
    }

    if (!empty($event['stream_url'])) {
        echo "URL:" . escapeIcsValue($event['stream_url']) . "\r\n";
    }

    // เพิ่ม CATEGORIES (ถ้ามีจากไฟล์ต้นฉบับ หรือใช้ชื่อศิลปิน) + program_type ถ้ามี
    $categoriesParts = [];
    if (!empty($event['categories'])) {
        $categoriesParts[] = $event['categories'];
    }
    if (!empty($event['program_type'])) {
        $categoriesParts[] = $event['program_type'];
    }
    if (!empty($categoriesParts)) {
        echo "CATEGORIES:" . escapeIcsValue(implode(',', $categoriesParts)) . "\r\n";
    }

    echo "STATUS:CONFIRMED\r\n";
    echo "SEQUENCE:0\r\n";
    echo "BEGIN:VALARM\r\n";
    echo "TRIGGER:-PT15M\r\n";
    echo "ACTION:DISPLAY\r\n";
    echo "DESCRIPTION:Reminder\r\n";
    echo "END:VALARM\r\n";
    echo "END:VEVENT\r\n";
}

echo "END:VCALENDAR\r\n";

/**
 * Escape special characters in ICS values
 *
 * @param string $value The value to escape
 * @return string The escaped value
 */
function escapeIcsValue($value) {
    // Replace special characters according to RFC 5545
    $value = str_replace('\\', '\\\\', $value); // Backslash
    $value = str_replace(',', '\\,', $value);   // Comma
    $value = str_replace(';', '\\;', $value);   // Semicolon
    $value = str_replace("\n", '\\n', $value);  // Newline
    $value = str_replace("\r", '', $value);     // Remove carriage return
    return $value;
}
?>
