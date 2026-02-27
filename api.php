<?php

require_once 'config.php';
require_once 'IcsParser.php';

// Security headers
send_security_headers();
header('Content-Type: application/json; charset=utf-8');

/**
 * Send JSON response with HTTP caching headers (ETag + Cache-Control)
 * Supports 304 Not Modified for unchanged data
 *
 * @param mixed $data  Data to encode as JSON
 * @param int   $maxAge Cache duration in seconds (default: 5 minutes)
 */
function sendJsonWithCache($data, $maxAge = 300) {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $etag = '"' . md5($json) . '"';

    header('Cache-Control: public, max-age=' . $maxAge);
    header('ETag: ' . $etag);

    $ifNoneMatch = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : '';
    if ($ifNoneMatch === $etag) {
        http_response_code(304);
        exit;
    }

    echo $json;
}

/**
 * Escape HTML entities ในข้อมูลเพื่อป้องกัน XSS
 */
function escapeApiData($data, $fields = []) {
    if (is_array($data)) {
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $value = escapeApiData($value, $fields);
            } elseif (is_string($value) && (empty($fields) || in_array($key, $fields))) {
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }
        }
        unset($value);
    } elseif (is_string($data)) {
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    return $data;
}

// Multi-event support
$eventSlug = isset($_GET['event']) ? preg_replace('/[^a-zA-Z0-9\-_]/', '', $_GET['event']) : null;
$eventMetaId = null;
if ($eventSlug) {
    $eventMeta = get_event_by_slug($eventSlug);
    $eventMetaId = $eventMeta ? intval($eventMeta['id']) : null;
}

$parser = new IcsParser('ics', true, 'data/calendar.db', $eventMetaId);

$action = $_GET['action'] ?? 'programs';
$fieldsToEscape = ['title', 'location', 'organizer', 'description', 'categories', 'uid'];

try {
    switch ($action) {
        case 'programs':
            $events = $parser->getAllEvents();

            // Filter by organizer
            if (!empty($_GET['organizer'])) {
                $organizer = $_GET['organizer'];
                $events = array_filter($events, function($event) use ($organizer) {
                    return $event['organizer'] === $organizer;
                });
                $events = array_values($events); // Re-index array
            }

            // Filter by location
            if (!empty($_GET['location'])) {
                $location = $_GET['location'];
                $events = array_filter($events, function($event) use ($location) {
                    return $event['location'] === $location;
                });
                $events = array_values($events); // Re-index array
            }

            // Escape HTML เพื่อป้องกัน XSS
            $events = array_map(function($event) use ($fieldsToEscape) {
                return escapeApiData($event, $fieldsToEscape);
            }, $events);

            sendJsonWithCache($events);
            break;

        case 'organizers':
            $organizers = $parser->getAllOrganizers();
            // Escape HTML เพื่อป้องกัน XSS
            $organizers = array_map(function($org) {
                return htmlspecialchars($org, ENT_QUOTES, 'UTF-8');
            }, $organizers);
            sendJsonWithCache($organizers);
            break;

        case 'locations':
            $locations = $parser->getAllLocations();
            // Escape HTML เพื่อป้องกัน XSS
            $locations = array_map(function($loc) {
                return htmlspecialchars($loc, ENT_QUOTES, 'UTF-8');
            }, $locations);
            sendJsonWithCache($locations);
            break;

        case 'events_list':
            $activeEvents = get_all_active_events();
            $activeEvents = array_map(function($ev) {
                return [
                    'id' => intval($ev['id']),
                    'slug' => htmlspecialchars($ev['slug'], ENT_QUOTES, 'UTF-8'),
                    'name' => htmlspecialchars($ev['name'], ENT_QUOTES, 'UTF-8'),
                    'description' => htmlspecialchars($ev['description'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'start_date' => $ev['start_date'],
                    'end_date' => $ev['end_date'],
                    'venue_mode' => $ev['venue_mode'],
                ];
            }, $activeEvents);
            sendJsonWithCache($activeEvents, 600); // events list เปลี่ยนไม่บ่อย cache 10 นาที
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'error' => 'Invalid action. Use: programs, organizers, locations, or events_list'
            ], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => safe_error_message('Server error', $e->getMessage())
    ], JSON_UNESCAPED_UNICODE);
}
