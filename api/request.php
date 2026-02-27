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
    echo json_encode(['success' => false, 'data' => null, 'message' => 'Database file not found: ' . $dbPath], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'data' => null, 'message' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
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

    // Sanitize
    // Resolve event_id from event_slug
    $eventId = null;
    if (!empty($input['event_slug'])) {
        $eventId = get_event_id($input['event_slug']);
    }

    $data = [
        ':type' => $input['type'],
        ':program_id' => $input['type'] === 'modify' ? intval($input['program_id']) : null,
        ':title' => mb_substr(trim($input['title']), 0, 200),
        ':start' => $input['start'],
        ':end' => $input['end'],
        ':location' => mb_substr(trim($input['location'] ?? ''), 0, 200),
        ':organizer' => mb_substr(trim($input['organizer'] ?? ''), 0, 200),
        ':description' => mb_substr(trim($input['description'] ?? ''), 0, 2000),
        ':categories' => mb_substr(trim($input['categories'] ?? ''), 0, 500),
        ':requester_name' => mb_substr(trim($input['requester_name']), 0, 100),
        ':requester_email' => mb_substr(trim($input['requester_email'] ?? ''), 0, 200),
        ':requester_note' => mb_substr(trim($input['requester_note'] ?? ''), 0, 1000),
        ':event_id' => $eventId,
    ];

    try {
        $stmt = $db->prepare("
            INSERT INTO program_requests (type, program_id, title, start, end, location, organizer, description, categories, requester_name, requester_email, requester_note, event_id)
            VALUES (:type, :program_id, :title, :start, :end, :location, :organizer, :description, :categories, :requester_name, :requester_email, :requester_note, :event_id)
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
        $eventMetaId = null;
        if ($eventSlug) {
            $eventMetaId = get_event_id($eventSlug);
        }

        if ($eventMetaId) {
            $stmt = $db->prepare("SELECT id, title, start, location, organizer FROM programs WHERE event_id = :emi ORDER BY start DESC LIMIT 100");
            $stmt->execute([':emi' => $eventMetaId]);
        } else {
            $stmt = $db->query("SELECT id, title, start, location, organizer FROM programs ORDER BY start DESC LIMIT 100");
        }
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse(true, $events);
    } catch (PDOException $e) {
        jsonResponse(false, null, 'Failed to fetch events: ' . $e->getMessage());
    }
}

function checkRateLimit($ip) {
    $file = sys_get_temp_dir() . '/rate_' . md5($ip) . '.json';
    if (!file_exists($file)) return true;
    $data = json_decode(file_get_contents($file), true);
    if (!$data) $data = [];
    $now = time();
    $data = array_filter($data, function($t) use ($now) {
        return ($now - $t) < RATE_LIMIT_WINDOW;
    });
    return count($data) < RATE_LIMIT_MAX;
}

function recordRequest($ip) {
    $file = sys_get_temp_dir() . '/rate_' . md5($ip) . '.json';
    $data = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    if (!$data) $data = [];
    $now = time();
    $data = array_filter($data, function($t) use ($now) {
        return ($now - $t) < RATE_LIMIT_WINDOW;
    });
    $data[] = $now;
    file_put_contents($file, json_encode($data));
}

function jsonResponse($success, $data = null, $message = '') {
    echo json_encode(['success' => $success, 'data' => $data, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}
