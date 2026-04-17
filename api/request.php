<?php
/**
 * Public API for Event Requests
 */

require_once __DIR__ . '/../config.php';

send_security_headers();
header('Content-Type: application/json; charset=utf-8');

// Rate limiting (use constants from config/security.php if available)
if (!defined('RATE_LIMIT_MAX')) {
    define('RATE_LIMIT_MAX', 10);
}
if (!defined('RATE_LIMIT_WINDOW')) {
    define('RATE_LIMIT_WINDOW', 3600);
}

$dbPath = DB_PATH;
$db = null;

// ตรวจสอบว่าไฟล์ database มีอยู่
if (!file_exists($dbPath)) {
    echo json_encode(['success' => false, 'data' => null, 'message' => 'Service unavailable'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'data' => null, 'message' => 'Service unavailable'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'submit':
        submitRequest();
        break;
    case 'programs':
        getEvents();
        break;
    default:
        jsonResponse(false, null, 'Invalid action');
}

function submitRequest() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST required');
    }

    $ip = get_client_ip();
    if (!checkRateLimit($ip)) {
        http_response_code(429);
        jsonResponse(false, null, 'Too many requests');
    }

    $input = json_decode(file_get_contents('php://input'), true);

    // Validate
    $required = ['type', 'title', 'start', 'end', 'requester_name'];
    foreach ($required as $f) {
        if (empty($input[$f])) {
            jsonResponse(false, null, "Field '$f' is required");
        }
    }

    if (!in_array($input['type'], ['add', 'modify'])) {
        jsonResponse(false, null, 'Invalid type');
    }

    if ($input['type'] === 'modify' && empty($input['program_id'])) {
        jsonResponse(false, null, 'Program ID required for modify');
    }

    // Validate datetime format and value ranges (YYYY-MM-DD HH:MM or YYYY-MM-DD HH:MM:SS)
    foreach (['start', 'end'] as $field) {
        $val = $input[$field] ?? '';
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})(?::(\d{2}))?$/', $val, $m)) {
            jsonResponse(false, null, "Invalid datetime format for '$field'");
        }
        [, $y, $mo, $d, $h, $mi, $s] = array_pad($m, 7, '00');
        if (!checkdate((int)$mo, (int)$d, (int)$y) || (int)$h > 23 || (int)$mi > 59 || (int)$s > 59) {
            jsonResponse(false, null, "Invalid datetime value for '$field'");
        }
    }

    // Sanitize
    // Resolve event_id from event_slug
    $eventId = null;
    if (!empty($input['event_slug'])) {
        $eventId = get_event_id($input['event_slug']);
    }

    $data = [
        ':request_type' => $input['type'],
        ':program_id'   => $input['type'] === 'modify' ? intval($input['program_id']) : null,
        ':summary'      => mb_substr(trim($input['title']), 0, 200),
        ':start'        => $input['start'],
        ':end'          => $input['end'],
        ':location'     => mb_substr(trim($input['location'] ?? ''), 0, 200),
        ':organizer'    => mb_substr(trim($input['organizer'] ?? ''), 0, 200),
        ':description'  => mb_substr(trim($input['description'] ?? ''), 0, 2000),
        ':categories'   => mb_substr(trim($input['categories'] ?? ''), 0, 500),
        ':requester_name'  => mb_substr(trim($input['requester_name']), 0, 100),
        ':requester_email' => mb_substr(trim($input['requester_email'] ?? ''), 0, 200),
        ':note'         => mb_substr(trim($input['requester_note'] ?? ''), 0, 1000),
        ':event_id'     => $eventId,
    ];

    try {
        $stmt = $db->prepare("
            INSERT INTO program_requests (request_type, program_id, summary, start, end, location, organizer, description, categories, requester_name, requester_email, note, event_id)
            VALUES (:request_type, :program_id, :summary, :start, :end, :location, :organizer, :description, :categories, :requester_name, :requester_email, :note, :event_id)
        ");
        $stmt->execute($data);
        recordRequest($ip);
        jsonResponse(true, ['id' => $db->lastInsertId()], 'Request submitted');
    } catch (PDOException $e) {
        jsonResponse(false, null, 'Submit failed');
    }
}

function getEvents() {
    global $db;

    try {
        $eventSlug = isset($_GET['event']) ? preg_replace('/[^a-zA-Z0-9\-_]/', '', $_GET['event']) : null;
        $eventId = null;
        if ($eventSlug) {
            $eventId = get_event_id($eventSlug);
            // If slug was specified but event not found/inactive, return empty list.
            if ($eventId === null) {
                jsonResponse(true, []);
                return;
            }
        }

        if ($eventId) {
            $stmt = $db->prepare("SELECT id, title, start, end, location, organizer, categories, description FROM programs WHERE event_id = :emi ORDER BY start ASC LIMIT 200");
            $stmt->execute([':emi' => $eventId]);
        } else {
            $stmt = $db->query("SELECT id, title, start, end, location, organizer, categories, description FROM programs ORDER BY start ASC LIMIT 200");
        }
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse(true, $events);
    } catch (PDOException $e) {
        jsonResponse(false, null, 'Failed to fetch programs');
    }
}

function checkRateLimit($ip) {
    $file = sys_get_temp_dir() . '/rate_' . md5($ip) . '.json';
    $handle = fopen($file, 'c+');
    if (!$handle) return true;
    flock($handle, LOCK_SH);
    $content = stream_get_contents($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) $data = [];
    if (!is_array($data)) $data = [];
    $now = time();
    $data = array_filter($data, function($t) use ($now) {
        return ($now - $t) < RATE_LIMIT_WINDOW;
    });
    return count($data) < RATE_LIMIT_MAX;
}

function recordRequest($ip) {
    $file = sys_get_temp_dir() . '/rate_' . md5($ip) . '.json';
    $handle = fopen($file, 'c+');
    if (!$handle) return;
    flock($handle, LOCK_EX);
    $content = stream_get_contents($handle);
    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) $data = [];
    if (!is_array($data)) $data = [];
    $now = time();
    $data = array_filter($data, function($t) use ($now) {
        return ($now - $t) < RATE_LIMIT_WINDOW;
    });
    $data[] = $now;
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode(array_values($data)));
    flock($handle, LOCK_UN);
    fclose($handle);
}

function jsonResponse($success, $data = null, $message = '') {
    echo json_encode(['success' => $success, 'data' => $data, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}
