<?php
/**
 * Public API for Event Requests (แจ้งเพิ่ม / แก้ไข งาน)
 */

require_once __DIR__ . '/../config.php';

send_security_headers();
header('Content-Type: application/json; charset=utf-8');

// Rate limiting
if (!defined('RATE_LIMIT_MAX')) {
    define('RATE_LIMIT_MAX', 10);
}
if (!defined('RATE_LIMIT_WINDOW')) {
    define('RATE_LIMIT_WINDOW', 3600);
}

$dbPath = DB_PATH;

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

audit_api_context();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'submit':
        submitEventRequest();
        break;
    case 'events':
        getActiveEvents();
        break;
    default:
        evJsonResponse(false, null, 'Invalid action');
}

function submitEventRequest() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        evJsonResponse(false, null, 'POST required');
    }

    $ip = get_client_ip();
    if (!evCheckRateLimit($ip)) {
        audit_log(['action' => 'event_request_blocked', 'outcome' => 'blocked', 'error_code' => 'rate_limit',
            'entity_type' => 'event_request', 'metadata' => ['ip' => $ip]]);
        http_response_code(429);
        evJsonResponse(false, null, 'Too many requests');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        evJsonResponse(false, null, 'Invalid JSON');
    }

    // Only 'add' requests are accepted
    if (empty($input['type']) || $input['type'] !== 'add') {
        evJsonResponse(false, null, 'Invalid type');
    }

    if (empty(trim($input['requester_name'] ?? ''))) {
        evJsonResponse(false, null, "Field 'requester_name' is required");
    }

    // Validate date format if provided (YYYY-MM-DD)
    foreach (['start_date', 'end_date'] as $field) {
        $val = $input[$field] ?? '';
        if ($val !== '' && $val !== null) {
            if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $val, $m)) {
                evJsonResponse(false, null, "Invalid date format for '$field'");
            }
            [, $y, $mo, $d] = $m;
            if (!checkdate((int)$mo, (int)$d, (int)$y)) {
                evJsonResponse(false, null, "Invalid date value for '$field'");
            }
        }
    }

    $data = [
        ':request_type'    => 'add',
        ':event_id'        => null,
        ':name'            => mb_substr(trim($input['name'] ?? ''), 0, 200) ?: null,
        ':description'     => mb_substr(trim($input['description'] ?? ''), 0, 2000) ?: null,
        ':start_date'      => ($input['start_date'] ?? '') !== '' ? $input['start_date'] : null,
        ':end_date'        => ($input['end_date'] ?? '') !== '' ? $input['end_date'] : null,
        ':requester_name'  => mb_substr(trim($input['requester_name']), 0, 100),
        ':requester_email' => mb_substr(trim($input['requester_email'] ?? ''), 0, 200) ?: null,
        ':note'            => mb_substr(trim($input['note'] ?? ''), 0, 1000) ?: null,
    ];

    try {
        $stmt = $db->prepare("
            INSERT INTO event_requests (request_type, event_id, name, description, start_date, end_date, requester_name, requester_email, note)
            VALUES (:request_type, :event_id, :name, :description, :start_date, :end_date, :requester_name, :requester_email, :note)
        ");
        $stmt->execute($data);
        $requestId = $db->lastInsertId();
        evRecordRequest($ip);
        audit_log(['action' => 'event_request_create', 'outcome' => 'success',
            'entity_type' => 'event_request', 'entity_id' => (int)$requestId,
            'entity_label' => mb_substr($data[':name'] ?? '', 0, 80),
            'metadata' => [
                'requester_name'  => $data[':requester_name'],
                'requester_email' => $data[':requester_email'] ?: null,
                'start_date'      => $data[':start_date'],
                'end_date'        => $data[':end_date'],
            ]]);
        email_notify_event_request_created($requestId, $input);
        evJsonResponse(true, ['id' => $requestId], 'Request submitted');
    } catch (PDOException $e) {
        audit_log(['action' => 'event_request_create', 'outcome' => 'failure', 'error_code' => 'db_error',
            'entity_type' => 'event_request',
            'metadata' => ['requester_name' => $data[':requester_name'] ?? null]]);
        evJsonResponse(false, null, 'Submit failed');
    }
}

function getActiveEvents() {
    global $db;

    try {
        // Check table exists
        $chk = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='events'");
        if (!$chk->fetch()) {
            evJsonResponse(true, []);
        }

        $stmt = $db->query("SELECT id, name, start_date, end_date FROM events WHERE is_active = 1 ORDER BY start_date DESC, name ASC LIMIT 200");
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        evJsonResponse(true, $events);
    } catch (PDOException $e) {
        evJsonResponse(false, null, 'Failed to fetch events');
    }
}

function evCheckRateLimit($ip) {
    $file = sys_get_temp_dir() . '/evrate_' . md5($ip) . '.json';
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

function evRecordRequest($ip) {
    $file = sys_get_temp_dir() . '/evrate_' . md5($ip) . '.json';
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

function evJsonResponse($success, $data = null, $message = '') {
    echo json_encode(['success' => $success, 'data' => $data, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}
