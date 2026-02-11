<?php

require_once 'config.php';
require_once 'IcsParser.php';

// Security headers
send_security_headers();
header('Content-Type: application/json; charset=utf-8');

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

$parser = new IcsParser('ics');

$action = $_GET['action'] ?? 'events';
$fieldsToEscape = ['title', 'location', 'organizer', 'description', 'categories', 'uid'];

try {
    switch ($action) {
        case 'events':
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

            echo json_encode($events, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;

        case 'organizers':
            $organizers = $parser->getAllOrganizers();
            // Escape HTML เพื่อป้องกัน XSS
            $organizers = array_map(function($org) {
                return htmlspecialchars($org, ENT_QUOTES, 'UTF-8');
            }, $organizers);
            echo json_encode($organizers, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;

        case 'locations':
            $locations = $parser->getAllLocations();
            // Escape HTML เพื่อป้องกัน XSS
            $locations = array_map(function($loc) {
                return htmlspecialchars($loc, ENT_QUOTES, 'UTF-8');
            }, $locations);
            echo json_encode($locations, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'error' => 'Invalid action. Use: events, organizers, or locations'
            ], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => safe_error_message('Server error', $e->getMessage())
    ], JSON_UNESCAPED_UNICODE);
}
