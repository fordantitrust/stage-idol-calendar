<?php
/**
 * Admin API for Event Management
 * CRUD operations for events
 */

require_once __DIR__ . '/../config.php';

// Security headers
send_security_headers();
header('Content-Type: application/json; charset=utf-8');
// CORS: อนุญาตเฉพาะ same-origin (ลบ wildcard เพื่อความปลอดภัย)
// header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// IP Whitelist check - ต้องผ่านก่อน login check
require_api_allowed_ip();

// Authentication: Require login for all API access
require_api_login();

// CSRF Protection: Validate token for state-changing requests (POST, PUT, DELETE)
require_csrf_token();

// Database connection
$db = null;

try {
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    jsonResponse(false, null, safe_error_message('Database connection failed', $e->getMessage()));
    exit;
}

// Get action
$action = $_GET['action'] ?? '';

// Role-based access control: restrict admin-only actions
$adminOnlyActions = [
    'backup_create', 'backup_list', 'backup_download',
    'backup_delete', 'backup_restore', 'backup_upload_restore',
    'users_list', 'users_get', 'users_create', 'users_update', 'users_delete',
];

if (in_array($action, $adminOnlyActions)) {
    require_api_admin_role();
}

switch ($action) {
    case 'programs_list':
        listPrograms();
        break;
    case 'programs_get':
        getProgram();
        break;
    case 'programs_create':
        createProgram();
        break;
    case 'programs_update':
        updateProgram();
        break;
    case 'programs_delete':
        deleteProgram();
        break;
    case 'programs_venues':
        getVenues();
        break;
    case 'requests':
        listRequests();
        break;
    case 'request_approve':
        approveRequest();
        break;
    case 'request_reject':
        rejectRequest();
        break;
    case 'pending_count':
        getPendingCount();
        break;
    case 'upload_ics':
        uploadAndParseIcs();
        break;
    case 'import_ics_confirm':
        confirmIcsImport();
        break;
    case 'programs_bulk_delete':
        bulkDeletePrograms();
        break;
    case 'programs_bulk_update':
        bulkUpdatePrograms();
        break;
    case 'credits_list':
        listCredits();
        break;
    case 'credits_get':
        getCredit();
        break;
    case 'credits_create':
        createCredit();
        break;
    case 'credits_update':
        updateCredit();
        break;
    case 'credits_delete':
        deleteCredit();
        break;
    case 'credits_bulk_delete':
        bulkDeleteCredits();
        break;
    // Events CRUD
    case 'events_list':
        listEvents();
        break;
    case 'events_get':
        getEvent();
        break;
    case 'events_create':
        createEvent();
        break;
    case 'events_update':
        updateEvent();
        break;
    case 'events_delete':
        deleteEvent();
        break;
    // Change Password
    case 'change_password':
        changeAdminPassword();
        break;
    // User Management (admin only)
    case 'users_list':
        listUsers();
        break;
    case 'users_get':
        getUser();
        break;
    case 'users_create':
        createUser();
        break;
    case 'users_update':
        updateUser();
        break;
    case 'users_delete':
        deleteUser();
        break;
    // Backup/Restore
    case 'backup_create':
        createBackup();
        break;
    case 'backup_list':
        listBackups();
        break;
    case 'backup_download':
        downloadBackup();
        break;
    case 'backup_delete':
        deleteBackupFile();
        break;
    case 'backup_restore':
        restoreBackup();
        break;
    case 'backup_upload_restore':
        uploadAndRestoreBackup();
        break;
    case 'theme_get':
        getThemeSetting();
        break;
    case 'theme_save':
        saveThemeSetting();
        break;
    default:
        jsonResponse(false, null, 'Invalid action');
}

/**
 * Change admin password
 */
function changeAdminPassword() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST method required');
        return;
    }

    $userId = $_SESSION['admin_user_id'] ?? null;
    if ($userId === null) {
        jsonResponse(false, null, 'Password change requires database-managed user. Run: php tools/migrate-add-admin-users-table.php');
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $currentPassword = $input['current_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword)) {
        jsonResponse(false, null, 'Current password and new password are required');
        return;
    }

    $result = change_admin_password($userId, $currentPassword, $newPassword);
    jsonResponse($result['success'], null, $result['message']);
}

/**
 * List events with pagination and filters
 */
function listPrograms() {
    global $db;

    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = max(1, min(100, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    $search = get_sanitized_param('search', '', 200);
    $venue = get_sanitized_param('venue', '', 200);
    $dateFrom = get_sanitized_param('date_from', '', 20);
    $dateTo = get_sanitized_param('date_to', '', 20);

    // Sorting
    $allowedSortColumns = ['id', 'title', 'start', 'location', 'organizer'];
    $sortColumn = in_array($_GET['sort'] ?? '', $allowedSortColumns) ? $_GET['sort'] : 'start';
    $sortOrder = ($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

    // Event filter
    $eventId = isset($_GET['event_id']) ? intval($_GET['event_id']) : null;

    $where = [];
    $params = [];

    if ($eventId) {
        $where[] = "event_id = :event_id";
        $params[':event_id'] = $eventId;
    }

    if ($search) {
        $where[] = "(title LIKE :search OR organizer LIKE :search OR categories LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    if ($venue) {
        $where[] = "location = :venue";
        $params[':venue'] = $venue;
    }

    if ($dateFrom) {
        $where[] = "DATE(start) >= :date_from";
        $params[':date_from'] = $dateFrom;
    }

    if ($dateTo) {
        $where[] = "DATE(start) <= :date_to";
        $params[':date_to'] = $dateTo;
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    try {
        // Count total
        $countSql = "SELECT COUNT(*) as total FROM programs $whereClause";
        $stmt = $db->prepare($countSql);
        $stmt->execute($params);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Get events with dynamic sorting
        $sql = "SELECT id, uid, title, start, end, location, organizer, description, categories, created_at, updated_at
                FROM programs
                $whereClause
                ORDER BY $sortColumn $sortOrder
                LIMIT :limit OFFSET :offset";

        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Escape HTML ในข้อมูลเพื่อป้องกัน XSS
        $fieldsToEscape = ['title', 'location', 'organizer', 'description', 'categories', 'uid'];
        $events = array_map(function($event) use ($fieldsToEscape) {
            return escapeOutputData($event, $fieldsToEscape);
        }, $events);

        jsonResponse(true, [
            'events' => $events,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => intval($total),
                'totalPages' => ceil($total / $limit)
            ]
        ]);
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to fetch events', $e->getMessage()));
    }
}

/**
 * Get single event by ID
 */
function getProgram() {
    global $db;

    $id = intval($_GET['id'] ?? 0);

    if (!$id) {
        jsonResponse(false, null, 'Event ID required');
        return;
    }

    try {
        $stmt = $db->prepare("SELECT * FROM programs WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            jsonResponse(false, null, 'Event not found');
            return;
        }

        // Escape HTML ในข้อมูลเพื่อป้องกัน XSS
        $fieldsToEscape = ['title', 'location', 'organizer', 'description', 'categories', 'uid'];
        $event = escapeOutputData($event, $fieldsToEscape);

        jsonResponse(true, $event);
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to fetch event', $e->getMessage()));
    }
}

/**
 * Create new event
 */
function createProgram() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST method required');
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    if (empty($input['title']) || empty($input['start']) || empty($input['end'])) {
        jsonResponse(false, null, 'Title, start, and end are required');
        return;
    }

    // Generate UID
    $uid = uniqid('event-') . '@admin.local';

    $now = date('Y-m-d H:i:s');

    try {
        $eventId = isset($input['event_id']) ? intval($input['event_id']) : null;

        $stmt = $db->prepare("
            INSERT INTO programs (uid, title, start, end, location, organizer, description, categories, event_id, created_at, updated_at)
            VALUES (:uid, :title, :start, :end, :location, :organizer, :description, :categories, :event_id, :created_at, :updated_at)
        ");

        $stmt->execute([
            ':uid' => $uid,
            ':title' => $input['title'],
            ':start' => $input['start'],
            ':end' => $input['end'],
            ':location' => $input['location'] ?? '',
            ':organizer' => $input['organizer'] ?? '',
            ':description' => $input['description'] ?? '',
            ':categories' => $input['categories'] ?? '',
            ':event_id' => $eventId,
            ':created_at' => $now,
            ':updated_at' => $now
        ]);

        $id = $db->lastInsertId();

        jsonResponse(true, ['id' => $id, 'uid' => $uid], 'Event created successfully');
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to create event', $e->getMessage()));
    }
}

/**
 * Update existing event
 */
function updateProgram() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        jsonResponse(false, null, 'PUT method required');
        return;
    }

    $id = intval($_GET['id'] ?? 0);

    if (!$id) {
        jsonResponse(false, null, 'Event ID required');
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    if (empty($input['title']) || empty($input['start']) || empty($input['end'])) {
        jsonResponse(false, null, 'Title, start, and end are required');
        return;
    }

    $now = date('Y-m-d H:i:s');

    try {
        $updateEventId = array_key_exists('event_id', $input) ? (isset($input['event_id']) ? intval($input['event_id']) : null) : null;

        $stmt = $db->prepare("
            UPDATE programs
            SET title = :title,
                start = :start,
                end = :end,
                location = :location,
                organizer = :organizer,
                description = :description,
                categories = :categories,
                event_id = :event_id,
                updated_at = :updated_at
            WHERE id = :id
        ");

        $stmt->execute([
            ':id' => $id,
            ':title' => $input['title'],
            ':start' => $input['start'],
            ':end' => $input['end'],
            ':location' => $input['location'] ?? '',
            ':organizer' => $input['organizer'] ?? '',
            ':description' => $input['description'] ?? '',
            ':categories' => $input['categories'] ?? '',
            ':event_id' => $updateEventId,
            ':updated_at' => $now
        ]);

        if ($stmt->rowCount() === 0) {
            jsonResponse(false, null, 'Event not found');
            return;
        }

        jsonResponse(true, ['id' => $id], 'Event updated successfully');
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to update event', $e->getMessage()));
    }
}

/**
 * Delete event
 */
function deleteProgram() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        jsonResponse(false, null, 'DELETE method required');
        return;
    }

    $id = intval($_GET['id'] ?? 0);

    if (!$id) {
        jsonResponse(false, null, 'Event ID required');
        return;
    }

    try {
        $stmt = $db->prepare("DELETE FROM programs WHERE id = :id");
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() === 0) {
            jsonResponse(false, null, 'Event not found');
            return;
        }

        jsonResponse(true, null, 'Event deleted successfully');
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to delete event', $e->getMessage()));
    }
}

/**
 * Bulk delete events
 */
function bulkDeletePrograms() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        jsonResponse(false, null, 'DELETE method required');
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $ids = $input['ids'] ?? [];

    // Validate
    if (!is_array($ids) || empty($ids)) {
        jsonResponse(false, null, 'Event IDs array required');
        return;
    }

    if (count($ids) > 100) {
        jsonResponse(false, null, 'Maximum 100 events per request');
        return;
    }

    // Sanitize
    $ids = array_map('intval', $ids);
    $ids = array_filter($ids, function($id) { return $id > 0; });

    if (empty($ids)) {
        jsonResponse(false, null, 'No valid event IDs provided');
        return;
    }

    try {
        $db->beginTransaction();

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("DELETE FROM programs WHERE id IN ($placeholders)");
        $stmt->execute($ids);

        $deletedCount = $stmt->rowCount();
        $failedCount = count($ids) - $deletedCount;

        $db->commit();

        jsonResponse(true, [
            'deleted_count' => $deletedCount,
            'failed_count' => $failedCount,
            'requested_count' => count($ids)
        ], "Deleted $deletedCount events successfully");

    } catch (PDOException $e) {
        $db->rollBack();
        jsonResponse(false, null, safe_error_message('Failed to delete events', $e->getMessage()));
    }
}

/**
 * Bulk update events (location, organizer, and categories)
 */
function bulkUpdatePrograms() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        jsonResponse(false, null, 'PUT method required');
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $ids = $input['ids'] ?? [];
    $location = $input['location'] ?? null;
    $organizer = $input['organizer'] ?? null;
    $categories = $input['categories'] ?? null;

    // Validate
    if (!is_array($ids) || empty($ids)) {
        jsonResponse(false, null, 'Event IDs array required');
        return;
    }

    if ($location === null && $organizer === null && $categories === null) {
        jsonResponse(false, null, 'At least one field (location, organizer, or categories) must be provided');
        return;
    }

    if (count($ids) > 100) {
        jsonResponse(false, null, 'Maximum 100 events per request');
        return;
    }

    // Sanitize
    $ids = array_map('intval', $ids);
    $ids = array_filter($ids, function($id) { return $id > 0; });

    if (empty($ids)) {
        jsonResponse(false, null, 'No valid event IDs provided');
        return;
    }

    try {
        $db->beginTransaction();

        // Build dynamic UPDATE
        $setClauses = [];
        $params = [];

        if ($location !== null) {
            $setClauses[] = "location = :location";
            $params[':location'] = trim($location);
        }

        if ($organizer !== null) {
            $setClauses[] = "organizer = :organizer";
            $params[':organizer'] = trim($organizer);
        }

        if ($categories !== null) {
            $setClauses[] = "categories = :categories";
            $params[':categories'] = trim($categories);
        }

        $setClauses[] = "updated_at = :updated_at";
        $params[':updated_at'] = date('Y-m-d H:i:s');

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE programs SET " . implode(', ', $setClauses) . " WHERE id IN ($placeholders)";

        $stmt = $db->prepare($sql);

        // Bind named parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        // Bind positional parameters (IDs)
        foreach ($ids as $index => $id) {
            $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
        }

        $stmt->execute();
        $updatedCount = $stmt->rowCount();
        $failedCount = count($ids) - $updatedCount;

        $db->commit();

        jsonResponse(true, [
            'updated_count' => $updatedCount,
            'failed_count' => $failedCount,
            'requested_count' => count($ids)
        ], "Updated $updatedCount events successfully");

    } catch (PDOException $e) {
        $db->rollBack();
        jsonResponse(false, null, safe_error_message('Failed to update events', $e->getMessage()));
    }
}

/**
 * Get all venues
 */
function getVenues() {
    global $db;

    try {
        $stmt = $db->query("
            SELECT DISTINCT location
            FROM programs
            WHERE location IS NOT NULL AND location != ''
            ORDER BY location ASC
        ");

        $venues = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Escape HTML เพื่อป้องกัน XSS
            $venues[] = htmlspecialchars($row['location'], ENT_QUOTES, 'UTF-8');
        }

        jsonResponse(true, $venues);
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to fetch venues', $e->getMessage()));
    }
}

/**
 * List requests
 */
function listRequests() {
    global $db;
    $status = $_GET['status'] ?? '';
    $eventId = isset($_GET['event_id']) ? intval($_GET['event_id']) : null;
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;

    $conditions = [];
    $params = [];
    if ($status && in_array($status, ['pending', 'approved', 'rejected'])) {
        $conditions[] = "status = :status";
        $params[':status'] = $status;
    }
    if ($eventId) {
        $conditions[] = "event_id = :event_id";
        $params[':event_id'] = $eventId;
    }
    $where = $conditions ? "WHERE " . implode(' AND ', $conditions) : "";

    try {
        $countStmt = $db->prepare("SELECT COUNT(*) as total FROM program_requests $where");
        $countStmt->execute($params);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        $sql = "SELECT * FROM program_requests $where ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fields ที่ต้อง escape เพื่อป้องกัน XSS
        $fieldsToEscape = ['title', 'location', 'organizer', 'description', 'categories',
                          'requester_name', 'requester_email', 'requester_note', 'admin_note', 'reviewed_by'];

        // สำหรับ request ประเภท modify ให้ดึงข้อมูล event เดิมมาด้วย
        foreach ($requests as &$req) {
            if ($req['type'] === 'modify' && !empty($req['program_id'])) {
                $eventStmt = $db->prepare("SELECT id, title, start, end, location, organizer, description, categories FROM programs WHERE id = :id");
                $eventStmt->execute([':id' => $req['program_id']]);
                $originalEvent = $eventStmt->fetch(PDO::FETCH_ASSOC);
                // Escape original_event ด้วย
                $req['original_event'] = $originalEvent ? escapeOutputData($originalEvent, $fieldsToEscape) : null;
            } else {
                $req['original_event'] = null;
            }
            // Escape request data
            $req = escapeOutputData($req, $fieldsToEscape);
        }
        unset($req); // ล้าง reference

        jsonResponse(true, [
            'requests' => $requests,
            'pagination' => ['page' => $page, 'total' => intval($total), 'totalPages' => ceil($total / $limit)]
        ]);
    } catch (PDOException $e) {
        jsonResponse(false, null, 'Failed to fetch requests');
    }
}

/**
 * Approve request
 */
function approveRequest() {
    global $db;
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') jsonResponse(false, null, 'PUT required');

    $id = intval($_GET['id'] ?? 0);
    if (!$id) jsonResponse(false, null, 'ID required');

    $input = json_decode(file_get_contents('php://input'), true);

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("SELECT * FROM program_requests WHERE id = :id AND status = 'pending'");
        $stmt->execute([':id' => $id]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$req) { $db->rollBack(); jsonResponse(false, null, 'Not found or processed'); }

        $now = date('Y-m-d H:i:s');

        if ($req['type'] === 'add') {
            $uid = uniqid('req-') . '@local';
            $reqEventId = $req['event_id'] ?? null;
            $stmt = $db->prepare("INSERT INTO programs (uid, title, start, end, location, organizer, description, categories, event_id, created_at, updated_at) VALUES (:uid, :title, :start, :end, :location, :organizer, :description, :categories, :event_id, :now, :now2)");
            $stmt->execute([':uid' => $uid, ':title' => $req['title'], ':start' => $req['start'], ':end' => $req['end'], ':location' => $req['location'], ':organizer' => $req['organizer'], ':description' => $req['description'], ':categories' => $req['categories'], ':event_id' => $reqEventId, ':now' => $now, ':now2' => $now]);
            $programId = $db->lastInsertId();
        } else {
            $programId = $req['program_id'];
            $stmt = $db->prepare("UPDATE programs SET title = :title, start = :start, end = :end, location = :location, organizer = :organizer, description = :description, categories = :categories, updated_at = :now WHERE id = :id");
            $stmt->execute([':id' => $programId, ':title' => $req['title'], ':start' => $req['start'], ':end' => $req['end'], ':location' => $req['location'], ':organizer' => $req['organizer'], ':description' => $req['description'], ':categories' => $req['categories'], ':now' => $now]);
        }

        $stmt = $db->prepare("UPDATE program_requests SET status = 'approved', admin_note = :note, reviewed_at = :now, reviewed_by = :by WHERE id = :id");
        $stmt->execute([':id' => $id, ':note' => $input['admin_note'] ?? '', ':now' => $now, ':by' => $_SESSION['admin_username'] ?? 'admin']);

        $db->commit();
        jsonResponse(true, ['program_id' => $programId], 'Approved');
    } catch (PDOException $e) {
        $db->rollBack();
        jsonResponse(false, null, 'Failed');
    }
}

/**
 * Reject request
 */
function rejectRequest() {
    global $db;
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') jsonResponse(false, null, 'PUT required');

    $id = intval($_GET['id'] ?? 0);
    if (!$id) jsonResponse(false, null, 'ID required');

    $input = json_decode(file_get_contents('php://input'), true);

    try {
        $stmt = $db->prepare("UPDATE program_requests SET status = 'rejected', admin_note = :note, reviewed_at = :now, reviewed_by = :by WHERE id = :id AND status = 'pending'");
        $stmt->execute([':id' => $id, ':note' => $input['admin_note'] ?? '', ':now' => date('Y-m-d H:i:s'), ':by' => $_SESSION['admin_username'] ?? 'admin']);
        if ($stmt->rowCount() === 0) jsonResponse(false, null, 'Not found or processed');
        jsonResponse(true, null, 'Rejected');
    } catch (PDOException $e) {
        jsonResponse(false, null, 'Failed');
    }
}

/**
 * Get pending count
 */
function getPendingCount() {
    global $db;
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM program_requests WHERE status = 'pending'");
        jsonResponse(true, ['count' => intval($stmt->fetch(PDO::FETCH_ASSOC)['count'])]);
    } catch (PDOException $e) {
        jsonResponse(false, null, 'Failed');
    }
}

/**
 * Upload and parse ICS file
 * Upload ไฟล์ .ics และ parse events พร้อมตรวจสอบ duplicates
 */
function uploadAndParseIcs() {
    global $db;

    // Validate file upload
    if (!isset($_FILES['ics_file'])) {
        jsonResponse(false, null, 'No file uploaded');
    }

    $file = $_FILES['ics_file'];

    // Security validations
    $allowedExt = ['ics'];
    $allowedMime = ['text/calendar', 'text/plain', 'application/octet-stream'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $mime = @mime_content_type($file['tmp_name']);

    if (!in_array($ext, $allowedExt)) {
        jsonResponse(false, null, 'Invalid file type. Only .ics files allowed');
    }
    if ($mime && !in_array($mime, $allowedMime)) {
        jsonResponse(false, null, 'Invalid MIME type');
    }
    if ($file['size'] > $maxSize) {
        jsonResponse(false, null, 'File too large. Maximum 5MB allowed');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(false, null, 'Upload error: ' . $file['error']);
    }

    // Save to temporary location
    $tempFile = sys_get_temp_dir() . '/ics_upload_' . uniqid() . '.ics';
    if (!move_uploaded_file($file['tmp_name'], $tempFile)) {
        jsonResponse(false, null, 'Failed to save uploaded file');
    }

    // Parse ICS file
    $content = file_get_contents($tempFile);
    if ($content === false) {
        @unlink($tempFile);
        jsonResponse(false, null, 'Failed to read uploaded file');
    }

    // Extract VEVENT blocks
    preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $content, $matches);

    // Require IcsParser
    require_once __DIR__ . '/../IcsParser.php';
    $parser = new IcsParser('ics', false);

    $events = [];
    $failed = [];
    $stats = [
        'total' => count($matches[1]),
        'parsed' => 0,
        'failed' => 0,
        'duplicates' => 0
    ];

    foreach ($matches[1] as $index => $eventData) {
        $event = $parser->parseEvent($eventData);

        if (!$event) {
            $failed[] = [
                'index' => $index + 1,
                'error' => 'Failed to parse event',
                'raw_data' => substr($eventData, 0, 200)
            ];
            $stats['failed']++;
            continue;
        }

        // Validate required fields
        $errors = [];
        if (empty($event['title'])) $errors[] = 'Missing title';
        if (empty($event['start'])) $errors[] = 'Missing start time';
        if (empty($event['end'])) $errors[] = 'Missing end time';

        // Check for duplicates
        $stmt = $db->prepare("SELECT id FROM programs WHERE uid = :uid");
        $stmt->execute([':uid' => $event['uid']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        $event['temp_id'] = 'temp_' . ($index + 1);
        $event['is_duplicate'] = (bool)$existing;
        $event['existing_event_id'] = $existing ? $existing['id'] : null;
        $event['validation_errors'] = $errors;

        if ($existing) $stats['duplicates']++;

        $events[] = $event;
        $stats['parsed']++;
    }

    // Store temp file path in session for later use
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['pending_ics_file'] = $tempFile;
    $_SESSION['pending_ics_filename'] = basename($file['name']);

    // Escape output data
    $fieldsToEscape = ['title', 'location', 'organizer', 'description', 'categories', 'uid'];
    $events = escapeOutputData($events, $fieldsToEscape);
    $failed = escapeOutputData($failed, ['error', 'raw_data']);

    jsonResponse(true, [
        'filename' => basename($file['name']),
        'events' => $events,
        'stats' => $stats,
        'failed_events' => $failed
    ], 'File uploaded and parsed successfully');
}

/**
 * Confirm ICS import
 * รับ events จาก preview และ import ลง database
 */
function confirmIcsImport() {
    global $db;

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        jsonResponse(false, null, 'Invalid request data');
    }

    $events = $input['events'] ?? [];
    $saveFile = $input['save_file'] ?? true;
    $eventId = isset($input['event_id']) ? intval($input['event_id']) : null;

    if (empty($events)) {
        jsonResponse(false, null, 'No events to import');
    }

    $stats = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
    $errors = [];

    try {
        $db->beginTransaction();

        $insertStmt = $db->prepare("
            INSERT INTO programs (uid, title, start, end, location, organizer, description, categories, event_id, created_at, updated_at)
            VALUES (:uid, :title, :start, :end, :location, :organizer, :description, :categories, :event_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $updateStmt = $db->prepare("
            UPDATE programs SET
                title = :title, start = :start, end = :end,
                location = :location, organizer = :organizer,
                description = :description, categories = :categories,
                updated_at = CURRENT_TIMESTAMP
            WHERE uid = :uid
        ");

        foreach ($events as $event) {
            $action = $event['action'] ?? 'skip';

            if ($action === 'skip') {
                $stats['skipped']++;
                continue;
            }

            // Validate required fields
            if (empty($event['title']) || empty($event['start']) || empty($event['end']) || empty($event['uid'])) {
                $stats['errors']++;
                $errors[] = "Event missing required fields";
                continue;
            }

            try {
                $params = [
                    ':uid' => $event['uid'],
                    ':title' => $event['title'],
                    ':start' => $event['start'],
                    ':end' => $event['end'],
                    ':location' => $event['location'] ?? '',
                    ':organizer' => $event['organizer'] ?? '',
                    ':description' => $event['description'] ?? '',
                    ':categories' => $event['categories'] ?? ''
                ];

                if ($action === 'insert') {
                    $params[':event_id'] = $eventId;
                    $insertStmt->execute($params);
                    $stats['inserted']++;
                } elseif ($action === 'update') {
                    $updateStmt->execute($params);
                    $stats['updated']++;
                }
            } catch (PDOException $e) {
                $stats['errors']++;
                $title = $event['title'] ?? 'Unknown';
                $errors[] = "Event '$title': " . $e->getMessage();
            }
        }

        $db->commit();

        // Save file to ics/ folder if requested
        $savedFilename = null;
        if ($saveFile && isset($_SESSION['pending_ics_file'])) {
            $tempFile = $_SESSION['pending_ics_file'];
            $originalName = $_SESSION['pending_ics_filename'] ?? 'upload.ics';

            // Generate unique filename: upload_YYYYMMDD_HHMMSS.ics
            $timestamp = date('Ymd_His');
            $savedFilename = "upload_{$timestamp}.ics";
            $destination = __DIR__ . '/../ics/' . $savedFilename;

            if (file_exists($tempFile)) {
                if (copy($tempFile, $destination)) {
                    @unlink($tempFile); // Remove temp file
                } else {
                    $errors[] = "Failed to save file to ics/ folder";
                }
            }

            unset($_SESSION['pending_ics_file']);
            unset($_SESSION['pending_ics_filename']);
        }

        jsonResponse(true, [
            'saved_filename' => $savedFilename,
            'stats' => $stats,
            'errors' => $errors
        ], 'Import completed successfully');

    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(false, null, 'Import failed: ' . $e->getMessage());
    }
}

/**
 * Escape HTML entities ในข้อมูลเพื่อป้องกัน XSS
 * @param mixed $data - ข้อมูลที่ต้องการ escape (array หรือ string)
 * @param array $fields - รายชื่อ fields ที่ต้อง escape (ถ้าเป็น array)
 * @return mixed
 */
function escapeOutputData($data, $fields = []) {
    if (is_array($data)) {
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                // Recursive สำหรับ nested arrays
                $value = escapeOutputData($value, $fields);
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

// ============================================================================
// CREDITS API FUNCTIONS
// ============================================================================

/**
 * List credits with pagination and search
 */
function listCredits() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(false, null, 'GET method required');
        return;
    }

    try {
        // Pagination
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = max(1, min(100, intval($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        // Search
        $search = substr($_GET['search'] ?? '', 0, 200);

        // Sorting
        $allowedSortColumns = ['id', 'title', 'display_order', 'created_at'];
        $sortColumn = in_array($_GET['sort'] ?? '', $allowedSortColumns) ? $_GET['sort'] : 'display_order';
        $sortOrder = ($_GET['order'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

        // Event filter
        $eventId = isset($_GET['event_id']) ? intval($_GET['event_id']) : null;

        // Build WHERE clause
        $where = [];
        $params = [];

        if ($eventId) {
            $where[] = "(event_id IS NULL OR event_id = :event_id)";
            $params[':event_id'] = $eventId;
        }

        if ($search) {
            $where[] = "(title LIKE :search OR description LIKE :search OR link LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count total
        $countSql = "SELECT COUNT(*) as total FROM credits $whereClause";
        $stmt = $db->prepare($countSql);
        $stmt->execute($params);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Fetch data
        $sql = "SELECT * FROM credits $whereClause ORDER BY $sortColumn $sortOrder LIMIT :limit OFFSET :offset";
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $credits = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Escape output
        $fieldsToEscape = ['title', 'link', 'description'];
        $credits = array_map(function($credit) use ($fieldsToEscape) {
            return escapeOutputData($credit, $fieldsToEscape);
        }, $credits);

        $totalPages = ceil($total / $limit);

        jsonResponse(true, [
            'credits' => $credits,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => $totalPages
            ]
        ]);

    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to fetch credits', $e->getMessage()));
    }
}

/**
 * Get single credit by ID
 */
function getCredit() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(false, null, 'GET method required');
        return;
    }

    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(false, null, 'Valid credit ID required');
        return;
    }

    try {
        $stmt = $db->prepare("SELECT * FROM credits WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $credit = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$credit) {
            jsonResponse(false, null, 'Credit not found');
            return;
        }

        $fieldsToEscape = ['title', 'link', 'description'];
        $credit = escapeOutputData($credit, $fieldsToEscape);

        jsonResponse(true, $credit);

    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to fetch credit', $e->getMessage()));
    }
}

/**
 * Create new credit
 */
function createCredit() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST method required');
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    // Validation
    if (empty($input['title'])) {
        jsonResponse(false, null, 'Title is required');
        return;
    }

    $title = trim($input['title']);
    $link = trim($input['link'] ?? '');
    $description = trim($input['description'] ?? '');
    $display_order = intval($input['display_order'] ?? 0);
    $creditEventId = isset($input['event_id']) ? intval($input['event_id']) : null;

    if (strlen($title) > 200) {
        jsonResponse(false, null, 'Title is too long (max 200 characters)');
        return;
    }

    if (strlen($description) > 1000) {
        jsonResponse(false, null, 'Description is too long (max 1000 characters)');
        return;
    }

    try {
        $now = date('Y-m-d H:i:s');

        $stmt = $db->prepare("
            INSERT INTO credits (title, link, description, display_order, event_id, created_at, updated_at)
            VALUES (:title, :link, :description, :display_order, :event_id, :created_at, :updated_at)
        ");

        $stmt->execute([
            ':title' => $title,
            ':link' => $link,
            ':description' => $description,
            ':display_order' => $display_order,
            ':event_id' => $creditEventId,
            ':created_at' => $now,
            ':updated_at' => $now
        ]);

        $id = $db->lastInsertId();

        // Invalidate cache
        invalidate_credits_cache($creditEventId);

        jsonResponse(true, ['id' => $id], 'Credit created successfully');

    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to create credit', $e->getMessage()));
    }
}

/**
 * Update existing credit
 */
function updateCredit() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        jsonResponse(false, null, 'PUT method required');
        return;
    }

    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(false, null, 'Valid credit ID required');
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    // Validation
    if (empty($input['title'])) {
        jsonResponse(false, null, 'Title is required');
        return;
    }

    $title = trim($input['title']);
    $link = trim($input['link'] ?? '');
    $description = trim($input['description'] ?? '');
    $display_order = intval($input['display_order'] ?? 0);
    $creditEventId = isset($input['event_id']) ? (is_null($input['event_id']) ? null : intval($input['event_id'])) : null;

    if (strlen($title) > 200) {
        jsonResponse(false, null, 'Title is too long (max 200 characters)');
        return;
    }

    if (strlen($description) > 1000) {
        jsonResponse(false, null, 'Description is too long (max 1000 characters)');
        return;
    }

    try {
        $stmt = $db->prepare("
            UPDATE credits
            SET title = :title,
                link = :link,
                description = :description,
                display_order = :display_order,
                event_id = :event_id,
                updated_at = :updated_at
            WHERE id = :id
        ");

        $stmt->execute([
            ':title' => $title,
            ':link' => $link,
            ':description' => $description,
            ':display_order' => $display_order,
            ':event_id' => $creditEventId,
            ':updated_at' => date('Y-m-d H:i:s'),
            ':id' => $id
        ]);

        if ($stmt->rowCount() === 0) {
            jsonResponse(false, null, 'Credit not found or no changes made');
            return;
        }

        // Invalidate cache
        invalidate_credits_cache($creditEventId);

        jsonResponse(true, null, 'Credit updated successfully');

    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to update credit', $e->getMessage()));
    }
}

/**
 * Delete credit
 */
function deleteCredit() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        jsonResponse(false, null, 'DELETE method required');
        return;
    }

    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(false, null, 'Valid credit ID required');
        return;
    }

    try {
        $stmt = $db->prepare("DELETE FROM credits WHERE id = :id");
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() === 0) {
            jsonResponse(false, null, 'Credit not found');
            return;
        }

        // Invalidate cache
        invalidate_credits_cache();

        jsonResponse(true, null, 'Credit deleted successfully');

    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to delete credit', $e->getMessage()));
    }
}

/**
 * Bulk delete credits
 */
function bulkDeleteCredits() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        jsonResponse(false, null, 'DELETE method required');
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $ids = $input['ids'] ?? [];

    // Validation
    if (!is_array($ids) || empty($ids)) {
        jsonResponse(false, null, 'Credit IDs array required');
        return;
    }

    if (count($ids) > 100) {
        jsonResponse(false, null, 'Maximum 100 credits per request');
        return;
    }

    // Sanitize
    $ids = array_map('intval', $ids);
    $ids = array_filter($ids, function($id) { return $id > 0; });

    if (empty($ids)) {
        jsonResponse(false, null, 'No valid credit IDs provided');
        return;
    }

    try {
        $db->beginTransaction();

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("DELETE FROM credits WHERE id IN ($placeholders)");
        $stmt->execute($ids);

        $deletedCount = $stmt->rowCount();
        $failedCount = count($ids) - $deletedCount;

        $db->commit();

        // Invalidate cache
        invalidate_credits_cache();

        jsonResponse(true, [
            'deleted_count' => $deletedCount,
            'failed_count' => $failedCount,
            'requested_count' => count($ids)
        ], "Deleted $deletedCount credits successfully");

    } catch (PDOException $e) {
        $db->rollBack();
        jsonResponse(false, null, safe_error_message('Failed to delete credits', $e->getMessage()));
    }
}

// ============================================================================
// EVENTS META (CONVENTIONS) API FUNCTIONS
// ============================================================================

/**
 * List all events (conventions)
 */
function listEvents() {
    global $db;

    try {
        $stmt = $db->query("SELECT * FROM events ORDER BY start_date DESC, name ASC");
        $metas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add event count for each meta
        foreach ($metas as &$meta) {
            $countStmt = $db->prepare("SELECT COUNT(*) as count FROM programs WHERE event_id = :id");
            $countStmt->execute([':id' => $meta['id']]);
            $meta['event_count'] = intval($countStmt->fetch(PDO::FETCH_ASSOC)['count']);
        }
        unset($meta);

        $fieldsToEscape = ['name', 'slug', 'description'];
        $metas = array_map(function($m) use ($fieldsToEscape) {
            return escapeOutputData($m, $fieldsToEscape);
        }, $metas);

        jsonResponse(true, $metas);
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to fetch events meta', $e->getMessage()));
    }
}

/**
 * Get single event_meta by ID
 */
function getEvent() {
    global $db;

    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        jsonResponse(false, null, 'Event meta ID required');
        return;
    }

    try {
        $stmt = $db->prepare("SELECT * FROM events WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $meta = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$meta) {
            jsonResponse(false, null, 'Event meta not found');
            return;
        }

        $fieldsToEscape = ['name', 'slug', 'description'];
        $meta = escapeOutputData($meta, $fieldsToEscape);

        jsonResponse(true, $meta);
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to fetch event meta', $e->getMessage()));
    }
}

/**
 * Create new event_meta (convention)
 */
function createEvent() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST method required');
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['name']) || empty($input['slug'])) {
        jsonResponse(false, null, 'Name and slug are required');
        return;
    }

    $slug = preg_replace('/[^a-zA-Z0-9\-_]/', '', trim($input['slug']));
    $name = mb_substr(trim($input['name']), 0, 200);
    $description = mb_substr(trim($input['description'] ?? ''), 0, 1000);
    $startDate = $input['start_date'] ?? null;
    $endDate = $input['end_date'] ?? null;
    $venueMode = in_array($input['venue_mode'] ?? '', ['multi', 'single']) ? $input['venue_mode'] : 'multi';
    $isActive = isset($input['is_active']) ? intval($input['is_active']) : 1;

    try {
        // Check unique slug
        $check = $db->prepare("SELECT id FROM events WHERE slug = :slug");
        $check->execute([':slug' => $slug]);
        if ($check->fetch()) {
            jsonResponse(false, null, 'Slug already exists');
            return;
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare("
            INSERT INTO events (slug, name, description, start_date, end_date, venue_mode, is_active, created_at, updated_at)
            VALUES (:slug, :name, :description, :start_date, :end_date, :venue_mode, :is_active, :now, :now2)
        ");
        $stmt->execute([
            ':slug' => $slug,
            ':name' => $name,
            ':description' => $description,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':venue_mode' => $venueMode,
            ':is_active' => $isActive,
            ':now' => $now,
            ':now2' => $now
        ]);

        jsonResponse(true, ['id' => $db->lastInsertId()], 'Convention created successfully');
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to create convention', $e->getMessage()));
    }
}

/**
 * Update existing event_meta
 */
function updateEvent() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        jsonResponse(false, null, 'PUT method required');
        return;
    }

    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        jsonResponse(false, null, 'Event meta ID required');
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['name']) || empty($input['slug'])) {
        jsonResponse(false, null, 'Name and slug are required');
        return;
    }

    $slug = preg_replace('/[^a-zA-Z0-9\-_]/', '', trim($input['slug']));
    $name = mb_substr(trim($input['name']), 0, 200);
    $description = mb_substr(trim($input['description'] ?? ''), 0, 1000);
    $startDate = $input['start_date'] ?? null;
    $endDate = $input['end_date'] ?? null;
    $venueMode = in_array($input['venue_mode'] ?? '', ['multi', 'single']) ? $input['venue_mode'] : 'multi';
    $isActive = isset($input['is_active']) ? intval($input['is_active']) : 1;

    try {
        // Check slug uniqueness (exclude self)
        $check = $db->prepare("SELECT id FROM events WHERE slug = :slug AND id != :id");
        $check->execute([':slug' => $slug, ':id' => $id]);
        if ($check->fetch()) {
            jsonResponse(false, null, 'Slug already exists');
            return;
        }

        $stmt = $db->prepare("
            UPDATE events
            SET slug = :slug, name = :name, description = :description,
                start_date = :start_date, end_date = :end_date,
                venue_mode = :venue_mode, is_active = :is_active,
                updated_at = :updated_at
            WHERE id = :id
        ");
        $stmt->execute([
            ':slug' => $slug,
            ':name' => $name,
            ':description' => $description,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':venue_mode' => $venueMode,
            ':is_active' => $isActive,
            ':updated_at' => date('Y-m-d H:i:s'),
            ':id' => $id
        ]);

        if ($stmt->rowCount() === 0) {
            jsonResponse(false, null, 'Convention not found');
            return;
        }

        jsonResponse(true, ['id' => $id], 'Convention updated successfully');
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to update convention', $e->getMessage()));
    }
}

/**
 * Delete event_meta
 */
function deleteEvent() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        jsonResponse(false, null, 'DELETE method required');
        return;
    }

    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        jsonResponse(false, null, 'Event meta ID required');
        return;
    }

    try {
        // Check if there are programs linked to this event
        $countStmt = $db->prepare("SELECT COUNT(*) as count FROM programs WHERE event_id = :id");
        $countStmt->execute([':id' => $id]);
        $eventCount = intval($countStmt->fetch(PDO::FETCH_ASSOC)['count']);

        if ($eventCount > 0) {
            jsonResponse(false, null, "Cannot delete: $eventCount events are linked to this convention. Delete or reassign them first.");
            return;
        }

        $stmt = $db->prepare("DELETE FROM events WHERE id = :id");
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() === 0) {
            jsonResponse(false, null, 'Convention not found');
            return;
        }

        jsonResponse(true, null, 'Convention deleted successfully');
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to delete convention', $e->getMessage()));
    }
}

// ============================================================================
// USER MANAGEMENT FUNCTIONS (admin only)
// ============================================================================

/**
 * List all admin users
 */
function listUsers() {
    global $db;

    try {
        $stmt = $db->query("SELECT id, username, display_name, role, is_active, created_at, updated_at, last_login_at FROM admin_users ORDER BY id ASC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $fieldsToEscape = ['username', 'display_name'];
        $users = array_map(function($user) use ($fieldsToEscape) {
            return escapeOutputData($user, $fieldsToEscape);
        }, $users);

        jsonResponse(true, ['users' => $users]);
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to fetch users', $e->getMessage()));
    }
}

/**
 * Get single user by ID
 */
function getUser() {
    global $db;

    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        jsonResponse(false, null, 'User ID required');
        return;
    }

    try {
        $stmt = $db->prepare("SELECT id, username, display_name, role, is_active, created_at, updated_at, last_login_at FROM admin_users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            jsonResponse(false, null, 'User not found');
            return;
        }

        $fieldsToEscape = ['username', 'display_name'];
        $user = escapeOutputData($user, $fieldsToEscape);

        jsonResponse(true, $user);
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to fetch user', $e->getMessage()));
    }
}

/**
 * Create new admin user
 */
function createUser() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST method required');
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    $displayName = trim($input['display_name'] ?? '');
    $role = $input['role'] ?? 'agent';
    $isActive = isset($input['is_active']) ? intval($input['is_active']) : 1;

    // Validation
    if (empty($username)) {
        jsonResponse(false, null, 'Username is required');
        return;
    }

    if (strlen($username) > 50) {
        jsonResponse(false, null, 'Username is too long (max 50 characters)');
        return;
    }

    if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $username)) {
        jsonResponse(false, null, 'Username can only contain letters, numbers, underscore, hyphen, and dot');
        return;
    }

    if (empty($password) || strlen($password) < 8) {
        jsonResponse(false, null, 'Password is required (min 8 characters)');
        return;
    }

    if (!in_array($role, ['admin', 'agent'])) {
        jsonResponse(false, null, 'Invalid role. Must be admin or agent');
        return;
    }

    if (strlen($displayName) > 100) {
        jsonResponse(false, null, 'Display name is too long (max 100 characters)');
        return;
    }

    try {
        // Check unique username
        $check = $db->prepare("SELECT id FROM admin_users WHERE username = :username");
        $check->execute([':username' => $username]);
        if ($check->fetch()) {
            jsonResponse(false, null, 'Username already exists');
            return;
        }

        $now = date('Y-m-d H:i:s');
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $db->prepare("
            INSERT INTO admin_users (username, password_hash, display_name, role, is_active, created_at, updated_at)
            VALUES (:username, :password_hash, :display_name, :role, :is_active, :created_at, :updated_at)
        ");
        $stmt->execute([
            ':username' => $username,
            ':password_hash' => $passwordHash,
            ':display_name' => $displayName ?: $username,
            ':role' => $role,
            ':is_active' => $isActive,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        jsonResponse(true, ['id' => $db->lastInsertId()], 'User created successfully');
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to create user', $e->getMessage()));
    }
}

/**
 * Update existing admin user
 */
function updateUser() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        jsonResponse(false, null, 'PUT method required');
        return;
    }

    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        jsonResponse(false, null, 'User ID required');
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $displayName = trim($input['display_name'] ?? '');
    $role = $input['role'] ?? '';
    $isActive = isset($input['is_active']) ? intval($input['is_active']) : 1;
    $newPassword = $input['password'] ?? '';

    // Validation
    if (!in_array($role, ['admin', 'agent'])) {
        jsonResponse(false, null, 'Invalid role. Must be admin or agent');
        return;
    }

    if (strlen($displayName) > 100) {
        jsonResponse(false, null, 'Display name is too long (max 100 characters)');
        return;
    }

    // Prevent changing own role
    $currentUserId = $_SESSION['admin_user_id'] ?? null;
    if ($currentUserId !== null && intval($currentUserId) === $id) {
        // Check if trying to change own role
        $selfStmt = $db->prepare("SELECT role FROM admin_users WHERE id = :id");
        $selfStmt->execute([':id' => $id]);
        $self = $selfStmt->fetch(PDO::FETCH_ASSOC);
        if ($self && $self['role'] !== $role) {
            jsonResponse(false, null, 'Cannot change your own role');
            return;
        }
        // Prevent deactivating self
        if (!$isActive) {
            jsonResponse(false, null, 'Cannot deactivate your own account');
            return;
        }
    }

    try {
        // Check user exists
        $check = $db->prepare("SELECT id FROM admin_users WHERE id = :id");
        $check->execute([':id' => $id]);
        if (!$check->fetch()) {
            jsonResponse(false, null, 'User not found');
            return;
        }

        $now = date('Y-m-d H:i:s');

        if (!empty($newPassword)) {
            if (strlen($newPassword) < 8) {
                jsonResponse(false, null, 'Password must be at least 8 characters');
                return;
            }
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare("
                UPDATE admin_users
                SET display_name = :display_name, role = :role, is_active = :is_active,
                    password_hash = :password_hash, updated_at = :updated_at
                WHERE id = :id
            ");
            $stmt->execute([
                ':display_name' => $displayName,
                ':role' => $role,
                ':is_active' => $isActive,
                ':password_hash' => $passwordHash,
                ':updated_at' => $now,
                ':id' => $id,
            ]);
        } else {
            $stmt = $db->prepare("
                UPDATE admin_users
                SET display_name = :display_name, role = :role, is_active = :is_active,
                    updated_at = :updated_at
                WHERE id = :id
            ");
            $stmt->execute([
                ':display_name' => $displayName,
                ':role' => $role,
                ':is_active' => $isActive,
                ':updated_at' => $now,
                ':id' => $id,
            ]);
        }

        jsonResponse(true, ['id' => $id], 'User updated successfully');
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to update user', $e->getMessage()));
    }
}

/**
 * Delete admin user
 */
function deleteUser() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        jsonResponse(false, null, 'DELETE method required');
        return;
    }

    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        jsonResponse(false, null, 'User ID required');
        return;
    }

    // Cannot delete self
    $currentUserId = $_SESSION['admin_user_id'] ?? null;
    if ($currentUserId !== null && intval($currentUserId) === $id) {
        jsonResponse(false, null, 'Cannot delete your own account');
        return;
    }

    try {
        // Check if this is the last admin user
        $user = $db->prepare("SELECT role FROM admin_users WHERE id = :id");
        $user->execute([':id' => $id]);
        $userData = $user->fetch(PDO::FETCH_ASSOC);

        if (!$userData) {
            jsonResponse(false, null, 'User not found');
            return;
        }

        if ($userData['role'] === 'admin') {
            $adminCount = $db->query("SELECT COUNT(*) as count FROM admin_users WHERE role = 'admin' AND is_active = 1")->fetch(PDO::FETCH_ASSOC)['count'];
            if (intval($adminCount) <= 1) {
                jsonResponse(false, null, 'Cannot delete the last admin user');
                return;
            }
        }

        $stmt = $db->prepare("DELETE FROM admin_users WHERE id = :id");
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() === 0) {
            jsonResponse(false, null, 'User not found');
            return;
        }

        jsonResponse(true, null, 'User deleted successfully');
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to delete user', $e->getMessage()));
    }
}

// ============================================================================
// BACKUP/RESTORE FUNCTIONS
// ============================================================================

/**
 * Validate backup filename to prevent path traversal
 */
function validateBackupFilename($filename) {
    if (empty($filename)) {
        return false;
    }
    // Only allow alphanumeric, underscore, hyphen, dot
    if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.db$/', $filename)) {
        return false;
    }
    // Block path traversal
    if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
        return false;
    }
    return true;
}

/**
 * Get backup directory path
 */
function getBackupDir() {
    return __DIR__ . '/../backups';
}

/**
 * Create a backup of the database
 */
function createBackup() {
    global $db;

    $dbPath = DB_PATH;
    $backupDir = getBackupDir();

    if (!file_exists($dbPath)) {
        jsonResponse(false, null, 'Database file not found');
        return;
    }

    // Create backup directory if not exists
    if (!is_dir($backupDir)) {
        if (!mkdir($backupDir, 0755, true)) {
            jsonResponse(false, null, 'Failed to create backup directory');
            return;
        }
    }

    $timestamp = gmdate('Ymd_His');
    $backupFilename = "backup_{$timestamp}.db";
    $backupPath = $backupDir . '/' . $backupFilename;

    // Close DB connection first to release file lock (important on Windows)
    $db = null;

    $success = copy($dbPath, $backupPath);

    // Reopen DB connection
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (!$success) {
        jsonResponse(false, null, 'Failed to create backup');
        return;
    }

    jsonResponse(true, [
        'filename' => $backupFilename,
        'size' => filesize($backupPath),
        'created_at' => gmdate('Y-m-d H:i:s')
    ], 'Backup created successfully');
}

/**
 * List all backup files
 */
function listBackups() {
    $backupDir = getBackupDir();

    if (!is_dir($backupDir)) {
        jsonResponse(true, ['backups' => []]);
        return;
    }

    $files = glob($backupDir . '/*.db') ?: [];
    $backups = [];

    foreach ($files as $file) {
        $filename = basename($file);
        $backups[] = [
            'filename' => $filename,
            'size' => filesize($file),
            'created_at' => gmdate('Y-m-d H:i:s', filemtime($file))
        ];
    }

    // Sort by modification time descending (newest first)
    usort($backups, function($a, $b) {
        return strcmp($b['created_at'], $a['created_at']);
    });

    jsonResponse(true, ['backups' => $backups]);
}

/**
 * Download a backup file
 */
function downloadBackup() {
    $filename = $_GET['filename'] ?? '';

    if (!validateBackupFilename($filename)) {
        jsonResponse(false, null, 'Invalid filename');
        return;
    }

    $backupDir = getBackupDir();
    $filePath = $backupDir . '/' . $filename;

    if (!file_exists($filePath)) {
        jsonResponse(false, null, 'Backup file not found');
        return;
    }

    // Send file for download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

    readfile($filePath);
    exit;
}

/**
 * Delete a backup file
 */
function deleteBackupFile() {
    $input = json_decode(file_get_contents('php://input'), true);
    $filename = $input['filename'] ?? '';

    if (!validateBackupFilename($filename)) {
        jsonResponse(false, null, 'Invalid filename');
        return;
    }

    $backupDir = getBackupDir();
    $filePath = $backupDir . '/' . $filename;

    if (!file_exists($filePath)) {
        jsonResponse(false, null, 'Backup file not found');
        return;
    }

    if (!unlink($filePath)) {
        jsonResponse(false, null, 'Failed to delete backup file');
        return;
    }

    jsonResponse(true, null, 'Backup deleted successfully');
}

/**
 * Validate that a file is a valid SQLite database
 */
function isValidSqliteFile($filePath) {
    $handle = fopen($filePath, 'rb');
    if (!$handle) {
        return false;
    }
    $header = fread($handle, 16);
    fclose($handle);

    // SQLite database file header
    return $header === "SQLite format 3\0";
}

/**
 * Restore database from a backup file on server
 */
function restoreBackup() {
    global $db;

    $input = json_decode(file_get_contents('php://input'), true);
    $filename = $input['filename'] ?? '';

    if (!validateBackupFilename($filename)) {
        jsonResponse(false, null, 'Invalid filename');
        return;
    }

    $backupDir = getBackupDir();
    $backupPath = $backupDir . '/' . $filename;
    $dbPath = DB_PATH;

    if (!file_exists($backupPath)) {
        jsonResponse(false, null, 'Backup file not found');
        return;
    }

    // Validate SQLite format
    if (!isValidSqliteFile($backupPath)) {
        jsonResponse(false, null, 'Invalid database file format');
        return;
    }

    // Auto-create backup before restore (safety net)
    $autoBackupName = 'auto_before_restore_' . gmdate('Ymd_His') . '.db';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    copy($dbPath, $backupDir . '/' . $autoBackupName);

    // Close current DB connection
    $db = null;

    // Copy backup to database
    if (!copy($backupPath, $dbPath)) {
        jsonResponse(false, null, 'Failed to restore database');
        return;
    }

    // Invalidate all caches
    invalidate_all_caches();

    jsonResponse(true, [
        'restored_from' => $filename,
        'auto_backup' => $autoBackupName
    ], 'Database restored successfully. Auto-backup created: ' . $autoBackupName);
}

/**
 * Upload a .db file and restore from it
 */
function uploadAndRestoreBackup() {
    global $db;

    if (!isset($_FILES['backup_file'])) {
        jsonResponse(false, null, 'No file uploaded');
        return;
    }

    $file = $_FILES['backup_file'];
    $maxSize = 50 * 1024 * 1024; // 50MB

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($ext !== 'db') {
        jsonResponse(false, null, 'Invalid file type. Only .db files allowed');
        return;
    }
    if ($file['size'] > $maxSize) {
        jsonResponse(false, null, 'File too large. Maximum 50MB allowed');
        return;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(false, null, 'Upload error: ' . $file['error']);
        return;
    }

    // Validate SQLite format
    if (!isValidSqliteFile($file['tmp_name'])) {
        jsonResponse(false, null, 'Invalid database file. Not a valid SQLite database');
        return;
    }

    $dbPath = DB_PATH;
    $backupDir = getBackupDir();

    // Auto-create backup before restore
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    $autoBackupName = 'auto_before_restore_' . gmdate('Ymd_His') . '.db';
    copy($dbPath, $backupDir . '/' . $autoBackupName);

    // Close current DB connection
    $db = null;

    // Move uploaded file to database location
    if (!move_uploaded_file($file['tmp_name'], $dbPath)) {
        jsonResponse(false, null, 'Failed to restore database');
        return;
    }

    // Invalidate all caches
    invalidate_all_caches();

    jsonResponse(true, [
        'auto_backup' => $autoBackupName
    ], 'Database restored from uploaded file. Auto-backup created: ' . $autoBackupName);
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Get site theme setting
 */
function getThemeSetting() {
    require_api_admin_role();
    $themeFile = dirname(__DIR__) . '/cache/site-theme.json';
    $theme = 'sakura';
    if (file_exists($themeFile)) {
        $data = json_decode(file_get_contents($themeFile), true);
        if (isset($data['theme'])) $theme = $data['theme'];
    }
    jsonResponse(true, ['theme' => $theme]);
}

/**
 * Save site theme setting (admin only)
 */
function saveThemeSetting() {
    require_api_admin_role();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST required');
        return;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $validThemes = ['sakura', 'ocean', 'forest', 'midnight', 'sunset', 'dark', 'gray'];
    $theme = $input['theme'] ?? 'sakura';
    if (!in_array($theme, $validThemes)) {
        jsonResponse(false, null, 'Invalid theme');
        return;
    }
    $themeFile = dirname(__DIR__) . '/cache/site-theme.json';
    $cacheDir = dirname($themeFile);
    if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);
    $ok = file_put_contents($themeFile, json_encode(['theme' => $theme, 'updated_at' => time()]));
    if ($ok !== false) {
        jsonResponse(true, ['theme' => $theme], 'Theme saved');
    } else {
        jsonResponse(false, null, 'Failed to save theme setting');
    }
}

/**
 * Send JSON response
 */
function jsonResponse($success, $data = null, $message = '') {
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
