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
    case 'programs_types':
        getTypes();
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
    case 'title_get':
        getTitleSetting();
        break;
    case 'title_save':
        saveTitleSetting();
        break;
    case 'disclaimer_get':
        getDisclaimerSetting();
        break;
    case 'disclaimer_save':
        saveDisclaimerSetting();
        break;
    // Telegram Config
    case 'telegram_config_get':
        getTelegramConfig();
        break;
    case 'telegram_config_save':
        saveTelegramConfig();
        break;
    case 'telegram_webhook_test':
        testTelegramWebhook();
        break;
    case 'telegram_webhook_register':
        registerTelegramWebhook();
        break;
    case 'telegram_log_get':
        getTelegramLog();
        break;
    case 'telegram_log_download':
        downloadTelegramLog();
        break;
    // Contact Channels
    case 'contact_channels_list':
        listContactChannels();
        break;
    case 'contact_channels_get':
        getContactChannel();
        break;
    case 'contact_channels_create':
        createContactChannel();
        break;
    case 'contact_channels_update':
        updateContactChannel();
        break;
    case 'contact_channels_delete':
        deleteContactChannel();
        break;
    // Artists CRUD
    case 'artists_list':
        listArtists();
        break;
    case 'artists_autocomplete':
        autocompleteArtists();
        break;
    case 'artists_get':
        getArtist();
        break;
    case 'artists_create':
        createArtist();
        break;
    case 'artists_update':
        updateArtist();
        break;
    case 'artists_delete':
        deleteArtist();
        break;
    case 'artists_groups':
        listArtistGroups();
        break;
    case 'artists_variants_list':
        listArtistVariants();
        break;
    case 'artists_variants_create':
        createArtistVariant();
        break;
    case 'artists_variants_delete':
        deleteArtistVariant();
        break;
    case 'artists_bulk_set_group':
        artistsBulkSetGroup();
        break;
    case 'artists_bulk_import':
        artistsBulkImport();
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
        // Escape % and _ for LIKE operator to prevent wildcard injection
        $searchEscaped = str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $search);
        $where[] = "(title LIKE :search ESCAPE '\\' OR organizer LIKE :search ESCAPE '\\' OR categories LIKE :search ESCAPE '\\')";
        $params[':search'] = '%' . $searchEscaped . '%';
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
        $sql = "SELECT id, uid, title, start, end, location, organizer, description, categories, program_type, stream_url, created_at, updated_at
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
        $fieldsToEscape = ['title', 'location', 'organizer', 'description', 'categories', 'program_type', 'stream_url', 'uid'];
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
        $fieldsToEscape = ['title', 'location', 'organizer', 'description', 'categories', 'program_type', 'stream_url', 'uid'];
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

        $programType = isset($input['program_type']) && $input['program_type'] !== '' ? trim($input['program_type']) : null;
        $streamUrlRaw = isset($input['stream_url']) && $input['stream_url'] !== '' ? trim($input['stream_url']) : null;
        // Only allow http/https schemes to prevent javascript: URI XSS
        $streamUrl = ($streamUrlRaw !== null && preg_match('/^https?:\/\//i', $streamUrlRaw)) ? $streamUrlRaw : null;

        $stmt = $db->prepare("
            INSERT INTO programs (uid, title, start, end, location, organizer, description, categories, program_type, stream_url, event_id, created_at, updated_at)
            VALUES (:uid, :title, :start, :end, :location, :organizer, :description, :categories, :program_type, :stream_url, :event_id, :created_at, :updated_at)
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
            ':program_type' => $programType,
            ':stream_url' => $streamUrl,
            ':event_id' => $eventId,
            ':created_at' => $now,
            ':updated_at' => $now
        ]);

        $id = $db->lastInsertId();

        syncProgramArtists($db, (int)$id, $input['categories'] ?? '');

        invalidate_data_version_cache();
        invalidate_feed_cache();
        invalidate_query_cache();
        invalidate_artist_query_cache();
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

        $programType = array_key_exists('program_type', $input)
            ? (($input['program_type'] !== '' && $input['program_type'] !== null) ? trim($input['program_type']) : null)
            : null;
        $streamUrlRaw = array_key_exists('stream_url', $input)
            ? (($input['stream_url'] !== '' && $input['stream_url'] !== null) ? trim($input['stream_url']) : null)
            : null;
        // Only allow http/https schemes to prevent javascript: URI XSS
        $streamUrl = ($streamUrlRaw !== null && preg_match('/^https?:\/\//i', $streamUrlRaw)) ? $streamUrlRaw : null;

        $stmt = $db->prepare("
            UPDATE programs
            SET title = :title,
                start = :start,
                end = :end,
                location = :location,
                organizer = :organizer,
                description = :description,
                categories = :categories,
                program_type = :program_type,
                stream_url = :stream_url,
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
            ':program_type' => $programType,
            ':stream_url' => $streamUrl,
            ':event_id' => $updateEventId,
            ':updated_at' => $now
        ]);

        if ($stmt->rowCount() === 0) {
            jsonResponse(false, null, 'Event not found');
            return;
        }

        syncProgramArtists($db, $id, $input['categories'] ?? '');

        invalidate_data_version_cache();
        invalidate_feed_cache();
        invalidate_query_cache();
        invalidate_artist_query_cache();
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

        invalidate_data_version_cache();
        invalidate_feed_cache();
        invalidate_query_cache();
        invalidate_artist_query_cache();
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

        invalidate_data_version_cache();
        invalidate_feed_cache();
        invalidate_query_cache();
        invalidate_artist_query_cache();
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
    $programType = array_key_exists('program_type', $input) ? $input['program_type'] : null;

    // Validate
    if (!is_array($ids) || empty($ids)) {
        jsonResponse(false, null, 'Event IDs array required');
        return;
    }

    if ($location === null && $organizer === null && $categories === null && $programType === null) {
        jsonResponse(false, null, 'At least one field (location, organizer, categories, or program_type) must be provided');
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

        if ($programType !== null) {
            $setClauses[] = "program_type = :program_type";
            $params[':program_type'] = ($programType !== '') ? trim($programType) : null;
        }

        $setClauses[] = "updated_at = :updated_at";
        $params[':updated_at'] = date('Y-m-d H:i:s');

        $idParams = [];
        foreach ($ids as $index => $id) {
            $key = ':id_' . $index;
            $idParams[$key] = $id;
        }
        $placeholders = implode(',', array_keys($idParams));
        $sql = "UPDATE programs SET " . implode(', ', $setClauses) . " WHERE id IN ($placeholders)";

        $stmt = $db->prepare($sql);

        // Bind SET parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        // Bind ID parameters
        foreach ($idParams as $key => $id) {
            $stmt->bindValue($key, $id, PDO::PARAM_INT);
        }

        $stmt->execute();
        $updatedCount = $stmt->rowCount();
        $failedCount = count($ids) - $updatedCount;

        $db->commit();

        invalidate_data_version_cache();
        invalidate_feed_cache();
        invalidate_query_cache();
        invalidate_artist_query_cache();
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
            $venues[] = $row['location'];
        }

        jsonResponse(true, $venues);
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to fetch venues', $e->getMessage()));
    }
}

/**
 * Get all program types (for autocomplete datalist)
 */
function getTypes() {
    global $db;

    try {
        $stmt = $db->query("
            SELECT DISTINCT program_type
            FROM programs
            WHERE program_type IS NOT NULL AND program_type != ''
            ORDER BY program_type ASC
        ");

        $types = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $types[] = $row['program_type'];
        }

        jsonResponse(true, $types);
    } catch (PDOException $e) {
        // Column may not exist yet — return empty array gracefully
        jsonResponse(true, []);
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
    $allowedMime = ['text/calendar', 'text/plain'];
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

    // Structural validation: must be a valid iCalendar file
    if (strpos($content, 'BEGIN:VCALENDAR') === false || strpos($content, 'END:VCALENDAR') === false) {
        @unlink($tempFile);
        jsonResponse(false, null, 'Invalid ICS file: missing BEGIN:VCALENDAR or END:VCALENDAR');
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

    // Collect unmatched categories (before escaping)
    $allCatCounts = [];
    foreach ($events as $ev) {
        foreach (explode(',', $ev['categories'] ?? '') as $cat) {
            $cat = trim($cat);
            if ($cat === '') continue;
            $allCatCounts[$cat] = ($allCatCounts[$cat] ?? 0) + 1;
        }
    }

    // Build variant → artist_id map from artist_variants table (DB-driven)
    // Falls back to artists-mapping.json if artist_variants table doesn't exist yet
    $variantToArtistId = []; // lowercase variant => artist_id

    $hasVariantsTable = (bool)$db->query(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='artist_variants'"
    )->fetch();

    if ($hasVariantsTable) {
        $vRows = $db->query("
            SELECT av.variant, a.id AS artist_id
            FROM artist_variants av
            JOIN artists a ON a.id = av.artist_id
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($vRows as $vRow) {
            $variantToArtistId[mb_strtolower(trim($vRow['variant']), 'UTF-8')] = (int)$vRow['artist_id'];
        }
    } else {
        // Fallback: load from artists-mapping.json and resolve canonical → artist_id
        $mappingFile = __DIR__ . '/../data/artists-mapping.json';
        if (file_exists($mappingFile)) {
            $mappingJson = json_decode(file_get_contents($mappingFile), true);
            if ($mappingJson && isset($mappingJson['artists'])) {
                foreach ($mappingJson['artists'] as $entry) {
                    if (!empty($entry['skip'])) continue;
                    $canonical = trim($entry['canonical'] ?? '');
                    if ($canonical === '') continue;
                    $s = $db->prepare('SELECT id FROM artists WHERE LOWER(name) = LOWER(?)');
                    $s->execute([$canonical]);
                    $aid = $s->fetchColumn();
                    if (!$aid) continue;
                    foreach ($entry['variants'] ?? [] as $variant) {
                        $variantToArtistId[mb_strtolower(trim($variant), 'UTF-8')] = (int)$aid;
                    }
                }
            }
        }
    }

    $unmatchedCategories = [];
    foreach ($allCatCounts as $cat => $count) {
        $s = $db->prepare('SELECT id, name FROM artists WHERE LOWER(name) = LOWER(?)');
        $s->execute([$cat]);
        if ($s->fetch()) continue; // already matched directly

        // Try variants map → find artist_id → get artist name
        $catLower  = mb_strtolower($cat, 'UTF-8');
        $suggested = null;
        $artistId  = $variantToArtistId[$catLower] ?? null;
        if ($artistId) {
            $s2 = $db->prepare('SELECT id, name FROM artists WHERE id = ?');
            $s2->execute([$artistId]);
            $row = $s2->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $suggested = [
                    'artist_id'   => (int)$row['id'],
                    'artist_name' => $row['name'],
                ];
            }
        }

        $unmatchedCategories[] = [
            'name'      => $cat,
            'count'     => $count,
            'suggested' => $suggested,
        ];
    }
    usort($unmatchedCategories, fn($a, $b) => $b['count'] - $a['count']);

    // All artists for mapping dropdown
    $allArtists = $db->query('SELECT id, name, is_group FROM artists ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
    $allArtists = array_map(fn($a) => escapeOutputData($a, ['name']), $allArtists);

    // Escape output data
    $fieldsToEscape = ['title', 'location', 'organizer', 'description', 'categories', 'uid', 'program_type'];
    $events = escapeOutputData($events, $fieldsToEscape);
    $failed = escapeOutputData($failed, ['error', 'raw_data']);

    jsonResponse(true, [
        'filename'             => basename($file['name']),
        'events'               => $events,
        'stats'                => $stats,
        'failed_events'        => $failed,
        'unmatched_categories' => $unmatchedCategories,
        'all_artists'          => $allArtists,
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
    $defaultType = isset($input['default_type']) && $input['default_type'] !== '' ? trim($input['default_type']) : null;

    if (empty($events)) {
        jsonResponse(false, null, 'No events to import');
    }

    $stats = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0, 'artist_links' => 0];
    $errors = [];
    $importedPrograms = []; // [{id, categories}]

    try {
        $db->beginTransaction();

        $insertStmt = $db->prepare("
            INSERT INTO programs (uid, title, start, end, location, organizer, description, categories, program_type, stream_url, event_id, created_at, updated_at)
            VALUES (:uid, :title, :start, :end, :location, :organizer, :description, :categories, :program_type, :stream_url, :event_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $updateStmt = $db->prepare("
            UPDATE programs SET
                title = :title, start = :start, end = :end,
                location = :location, organizer = :organizer,
                description = :description, categories = :categories,
                program_type = :program_type,
                stream_url = :stream_url,
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
                // program_type: ใช้จาก X-PROGRAM-TYPE ในไฟล์ หรือ default_type จาก UI
                $programType = !empty($event['program_type']) ? $event['program_type'] : $defaultType;
                $streamUrl = !empty($event['stream_url']) ? $event['stream_url'] : null;

                $params = [
                    ':uid' => $event['uid'],
                    ':title' => $event['title'],
                    ':start' => $event['start'],
                    ':end' => $event['end'],
                    ':location' => $event['location'] ?? '',
                    ':organizer' => $event['organizer'] ?? '',
                    ':description' => $event['description'] ?? '',
                    ':categories' => $event['categories'] ?? '',
                    ':program_type' => $programType,
                    ':stream_url' => $streamUrl
                ];

                if ($action === 'insert') {
                    $params[':event_id'] = $eventId;
                    $insertStmt->execute($params);
                    $programId = (int)$db->lastInsertId();
                    if ($programId) {
                        $importedPrograms[] = ['id' => $programId, 'categories' => $event['categories'] ?? ''];
                    }
                    $stats['inserted']++;
                } elseif ($action === 'update') {
                    $updateStmt->execute($params);
                    $s = $db->prepare('SELECT id FROM programs WHERE uid = :uid');
                    $s->execute([':uid' => $event['uid']]);
                    $programId = (int)$s->fetchColumn();
                    if ($programId) {
                        $importedPrograms[] = ['id' => $programId, 'categories' => $event['categories'] ?? ''];
                    }
                    $stats['updated']++;
                }
            } catch (PDOException $e) {
                $stats['errors']++;
                $title = $event['title'] ?? 'Unknown';
                $errors[] = "Event '$title': " . $e->getMessage();
            }
        }

        $db->commit();

        // ---- Artist linking ----
        // Build category (lowercase) → artist_id map from explicit mappings
        $artistMappings = $input['artist_mappings'] ?? [];
        $catToArtistId  = []; // lowercase cat => int|null (null = skip)

        foreach ($artistMappings as $mapping) {
            $cat    = mb_strtolower(trim(html_entity_decode($mapping['category'] ?? '', ENT_QUOTES, 'UTF-8')), 'UTF-8');
            $action = $mapping['action'] ?? 'skip';

            if ($action === 'skip') {
                $catToArtistId[$cat] = null;
            } elseif ($action === 'map' && !empty($mapping['artist_id'])) {
                $catToArtistId[$cat] = intval($mapping['artist_id']);
            } elseif ($action === 'create' && !empty($mapping['new_name'])) {
                $newName = trim(html_entity_decode($mapping['new_name'], ENT_QUOTES, 'UTF-8'));
                $isGroup = empty($mapping['is_group']) ? 0 : 1;
                $now     = date('Y-m-d H:i:s');
                $ins     = $db->prepare('INSERT OR IGNORE INTO artists (name, is_group, created_at, updated_at) VALUES (?, ?, ?, ?)');
                $ins->execute([$newName, $isGroup, $now, $now]);
                $newId = (int)$db->lastInsertId();
                if (!$newId) {
                    $s = $db->prepare('SELECT id FROM artists WHERE LOWER(name) = LOWER(?)');
                    $s->execute([$newName]);
                    $newId = (int)$s->fetchColumn();
                }
                if ($newId) $catToArtistId[$cat] = $newId;
            }
        }

        // Build variant → artist_id map for auto-linking (artist_variants table or fallback JSON)
        $variantIdMap = []; // lowercase variant => artist_id
        $hasVariantsTable = (bool)$db->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='artist_variants'"
        )->fetch();

        if ($hasVariantsTable) {
            $vRows = $db->query("
                SELECT av.variant, a.id AS artist_id
                FROM artist_variants av
                JOIN artists a ON a.id = av.artist_id
            ")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($vRows as $vRow) {
                $variantIdMap[mb_strtolower(trim($vRow['variant']), 'UTF-8')] = (int)$vRow['artist_id'];
            }
        } else {
            $mappingFile = __DIR__ . '/../data/artists-mapping.json';
            if (file_exists($mappingFile)) {
                $mappingJson = json_decode(file_get_contents($mappingFile), true);
                if ($mappingJson && isset($mappingJson['artists'])) {
                    foreach ($mappingJson['artists'] as $entry) {
                        if (!empty($entry['skip'])) continue;
                        $canonical = trim($entry['canonical'] ?? '');
                        if ($canonical === '') continue;
                        $sv = $db->prepare('SELECT id FROM artists WHERE LOWER(name) = LOWER(?)');
                        $sv->execute([$canonical]);
                        $aid = $sv->fetchColumn();
                        if (!$aid) continue;
                        foreach ($entry['variants'] ?? [] as $variant) {
                            $variantIdMap[mb_strtolower(trim($variant), 'UTF-8')] = (int)$aid;
                        }
                    }
                }
            }
        }

        // Link programs to artists
        $insertLink = $db->prepare('INSERT OR IGNORE INTO program_artists (program_id, artist_id) VALUES (?, ?)');
        foreach ($importedPrograms as $prog) {
            foreach (explode(',', html_entity_decode($prog['categories'], ENT_QUOTES, 'UTF-8')) as $catRaw) {
                $catRaw   = trim($catRaw);
                if ($catRaw === '') continue;
                $catLower = mb_strtolower($catRaw, 'UTF-8');

                if (array_key_exists($catLower, $catToArtistId)) {
                    $artistId = $catToArtistId[$catLower];
                } else {
                    // Auto-match: 1) direct name (case-insensitive), 2) variant lookup
                    $s = $db->prepare('SELECT id FROM artists WHERE LOWER(name) = LOWER(?)');
                    $s->execute([$catRaw]);
                    $artistId = $s->fetchColumn() ?: null;

                    if (!$artistId && isset($variantIdMap[$catLower])) {
                        $artistId = $variantIdMap[$catLower];
                    }

                    $catToArtistId[$catLower] = $artistId; // cache
                }

                if ($artistId) {
                    $insertLink->execute([$prog['id'], $artistId]);
                    if ($db->lastInsertId()) $stats['artist_links']++;
                }
            }
        }

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

        invalidate_data_version_cache();
        invalidate_feed_cache();
        invalidate_query_cache();
        invalidate_artist_query_cache();
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
 * Sync program_artists junction table for a single program based on its categories text.
 * Deletes all existing links for the program, then re-inserts based on category names
 * matched against the artists table (direct name match or variant lookup).
 *
 * Called after createProgram() and updateProgram() so that manual admin edits
 * to the categories field are reflected in the artist filter on the public site.
 *
 * @param PDO $db    Active database connection
 * @param int $programId
 * @param string $categories  Comma-separated category string from the programs table
 */
function syncProgramArtists(PDO $db, int $programId, string $categories): void {
    // Check program_artists table exists (v3.0.0+)
    $hasPATable = (bool)$db->query(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='program_artists'"
    )->fetch();
    if (!$hasPATable) return;

    // Remove all existing artist links for this program
    $db->prepare('DELETE FROM program_artists WHERE program_id = ?')->execute([$programId]);

    if (trim($categories) === '') return;

    // Build variant → artist_id map (lowercase)
    $hasVTable = (bool)$db->query(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='artist_variants'"
    )->fetch();
    $variantIdMap = [];
    if ($hasVTable) {
        $rows = $db->query('SELECT LOWER(variant) AS v, artist_id FROM artist_variants')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $variantIdMap[$r['v']] = (int)$r['artist_id'];
        }
    }

    $insertLink = $db->prepare('INSERT OR IGNORE INTO program_artists (program_id, artist_id) VALUES (?, ?)');
    $nameCache  = []; // lowercase name → artist_id|null

    foreach (explode(',', $categories) as $catRaw) {
        $catRaw = trim($catRaw);
        if ($catRaw === '') continue;
        $catLower = mb_strtolower($catRaw, 'UTF-8');

        if (!array_key_exists($catLower, $nameCache)) {
            // 1) exact name match (case-insensitive)
            $s = $db->prepare('SELECT id FROM artists WHERE LOWER(name) = LOWER(?)');
            $s->execute([$catRaw]);
            $artistId = $s->fetchColumn() ?: null;

            // 2) variant lookup
            if (!$artistId && isset($variantIdMap[$catLower])) {
                $artistId = $variantIdMap[$catLower];
            }

            // 3) auto-create new artist if still not found
            if (!$artistId) {
                $now = date('Y-m-d H:i:s');
                $db->prepare('INSERT INTO artists (name, is_group, created_at, updated_at) VALUES (?, 0, ?, ?)')
                   ->execute([$catRaw, $now, $now]);
                $artistId = (int)$db->lastInsertId();
                // also add to variant cache so subsequent same-name entries reuse this id
                $variantIdMap[$catLower] = $artistId;
            }

            $nameCache[$catLower] = (int)$artistId;
        }

        if ($nameCache[$catLower]) {
            $insertLink->execute([$programId, $nameCache[$catLower]]);
        }
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
        foreach ($fields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = htmlspecialchars($data[$field], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
        }
        return $data;
    }
    if (is_string($data)) {
        return htmlspecialchars($data, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
            // Escape % and _ for LIKE operator to prevent wildcard injection
            $searchEscaped = str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $search);
            $where[] = "(title LIKE :search ESCAPE '\\' OR description LIKE :search ESCAPE '\\' OR link LIKE :search ESCAPE '\\')";
            $params[':search'] = '%' . $searchEscaped . '%';
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
        // Get filter parameters
        $search = get_sanitized_param('search');
        $isActive = get_sanitized_param('is_active');
        $venueMode = get_sanitized_param('venue_mode');
        $dateFrom = get_sanitized_param('date_from');
        $dateTo = get_sanitized_param('date_to');
        $sort = get_sanitized_param('sort') ?? 'start_date';
        $order = get_sanitized_param('order') ?? 'desc';
        $page = intval($_GET['page'] ?? 1);
        $limit = intval($_GET['limit'] ?? 20);

        // Validate sort column (whitelist)
        $allowedSorts = ['id', 'name', 'start_date', 'end_date', 'is_active', 'event_count'];
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'start_date';
        }
        // Validate order
        $order = strtolower($order) === 'asc' ? 'asc' : 'desc';
        // Validate pagination
        if ($limit < 1 || $limit > 100) $limit = 20;
        if ($page < 1) $page = 1;

        // Build WHERE clause dynamically
        $whereClauses = [];
        $params = [];

        if ($search) {
            $searchTerm = '%' . str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $search) . '%';
            $whereClauses[] = "(name LIKE :search ESCAPE '\\' OR slug LIKE :search ESCAPE '\\' OR description LIKE :search ESCAPE '\\')";
            $params[':search'] = $searchTerm;
        }

        if ($isActive !== '') {
            $whereClauses[] = "is_active = :is_active";
            $params[':is_active'] = intval($isActive);
        }

        if ($venueMode !== '') {
            $whereClauses[] = "venue_mode = :venue_mode";
            $params[':venue_mode'] = $venueMode;
        }

        if ($dateFrom !== '') {
            $whereClauses[] = "DATE(start_date) >= :date_from";
            $params[':date_from'] = $dateFrom;
        }

        if ($dateTo !== '') {
            $whereClauses[] = "DATE(start_date) <= :date_to";
            $params[':date_to'] = $dateTo;
        }

        $whereSQL = !empty($whereClauses) ? ' WHERE ' . implode(' AND ', $whereClauses) : '';

        // COUNT query first for pagination total
        $countQuery = "SELECT COUNT(*) as total FROM events" . $whereSQL;
        $countStmt = $db->prepare($countQuery);
        $countStmt->execute($params);
        $total = intval($countStmt->fetch(PDO::FETCH_ASSOC)['total']);
        $countStmt->closeCursor();
        $countStmt = null;

        $totalPages = max(1, ceil($total / $limit));
        if ($page > $totalPages) $page = $totalPages;

        // Data query with subquery for event_count (N+1 fix)
        $offset = ($page - 1) * $limit;
        $dataQuery = "
            SELECT e.*,
                   (SELECT COUNT(*) FROM programs p WHERE p.event_id = e.id) as event_count
            FROM events e
            {$whereSQL}
            ORDER BY e.{$sort} {$order}
            LIMIT :limit OFFSET :offset
        ";

        $dataStmt = $db->prepare($dataQuery);
        $dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        foreach ($params as $key => $val) {
            $dataStmt->bindValue($key, $val);
        }
        $dataStmt->execute();
        $events = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
        $dataStmt->closeCursor();
        $dataStmt = null;

        // Escape output
        $fieldsToEscape = ['name', 'slug', 'description'];
        $events = array_map(function($m) use ($fieldsToEscape) {
            return escapeOutputData($m, $fieldsToEscape);
        }, $events);

        // Return paginated structure matching Programs format
        jsonResponse(true, [
            'events' => $events,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => $totalPages
            ]
        ]);
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to fetch events', $e->getMessage()));
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

    $validThemes = ['sakura', 'ocean', 'forest', 'midnight', 'sunset', 'dark', 'gray'];
    $slug = preg_replace('/[^a-zA-Z0-9\-_]/', '', trim($input['slug']));
    $name = mb_substr(trim($input['name']), 0, 200);
    $description = mb_substr(trim($input['description'] ?? ''), 0, 1000);
    $startDate = $input['start_date'] ?? null;
    $endDate = $input['end_date'] ?? null;
    $venueMode = in_array($input['venue_mode'] ?? '', ['multi', 'single', 'calendar']) ? $input['venue_mode'] : 'multi';
    $isActive = isset($input['is_active']) ? intval($input['is_active']) : 1;
    $theme = (isset($input['theme']) && in_array($input['theme'], $validThemes)) ? $input['theme'] : null;
    $emailRaw = trim($input['email'] ?? '');
    $email = ($emailRaw !== '' && filter_var($emailRaw, FILTER_VALIDATE_EMAIL)) ? $emailRaw : null;
    $timezoneRaw = trim($input['timezone'] ?? '');
    $timezone = defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 'Asia/Bangkok';
    if ($timezoneRaw !== '') {
        try { new DateTimeZone($timezoneRaw); $timezone = $timezoneRaw; } catch (Exception $e) {}
    }

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
            INSERT INTO events (slug, name, description, start_date, end_date, venue_mode, is_active, theme, email, timezone, created_at, updated_at)
            VALUES (:slug, :name, :description, :start_date, :end_date, :venue_mode, :is_active, :theme, :email, :timezone, :now, :now2)
        ");
        $stmt->execute([
            ':slug' => $slug,
            ':name' => $name,
            ':description' => $description,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':venue_mode' => $venueMode,
            ':is_active' => $isActive,
            ':theme' => $theme,
            ':email' => $email,
            ':timezone' => $timezone,
            ':now' => $now,
            ':now2' => $now
        ]);

        invalidate_query_cache();
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

    $validThemes = ['sakura', 'ocean', 'forest', 'midnight', 'sunset', 'dark', 'gray'];
    $slug = preg_replace('/[^a-zA-Z0-9\-_]/', '', trim($input['slug']));
    $name = mb_substr(trim($input['name']), 0, 200);
    $description = mb_substr(trim($input['description'] ?? ''), 0, 1000);
    $startDate = $input['start_date'] ?? null;
    $endDate = $input['end_date'] ?? null;
    $venueMode = in_array($input['venue_mode'] ?? '', ['multi', 'single', 'calendar']) ? $input['venue_mode'] : 'multi';
    $isActive = isset($input['is_active']) ? intval($input['is_active']) : 1;
    $theme = (isset($input['theme']) && in_array($input['theme'], $validThemes)) ? $input['theme'] : null;
    $emailRaw = trim($input['email'] ?? '');
    $email = ($emailRaw !== '' && filter_var($emailRaw, FILTER_VALIDATE_EMAIL)) ? $emailRaw : null;
    $timezoneRaw = trim($input['timezone'] ?? '');
    $timezone = defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 'Asia/Bangkok';
    if ($timezoneRaw !== '') {
        try { new DateTimeZone($timezoneRaw); $timezone = $timezoneRaw; } catch (Exception $e) {}
    }

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
                theme = :theme, email = :email, timezone = :timezone,
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
            ':theme' => $theme,
            ':email' => $email,
            ':timezone' => $timezone,
            ':updated_at' => date('Y-m-d H:i:s'),
            ':id' => $id
        ]);

        if ($stmt->rowCount() === 0) {
            jsonResponse(false, null, 'Convention not found');
            return;
        }

        invalidate_query_cache();
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

        invalidate_query_cache();
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
    if (!copy($dbPath, $backupDir . '/' . $autoBackupName)) {
        jsonResponse(false, null, 'Failed to create auto-backup before restore. Restore aborted.');
        return;
    }

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
    if (!copy($dbPath, $backupDir . '/' . $autoBackupName)) {
        jsonResponse(false, null, 'Failed to create auto-backup before restore. Restore aborted.');
        return;
    }

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

function getTitleSetting() {
    require_api_admin_role();
    $settingsFile = dirname(__DIR__) . '/cache/site-settings.json';
    $title = defined('APP_NAME') ? APP_NAME : 'Idol Stage Timetable';
    if (file_exists($settingsFile)) {
        $data = json_decode(file_get_contents($settingsFile), true);
        if (!empty($data['site_title'])) $title = $data['site_title'];
    }
    jsonResponse(true, ['site_title' => htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')]);
}

function saveTitleSetting() {
    require_api_admin_role();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST required');
        return;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $title = isset($input['site_title']) ? trim($input['site_title']) : '';
    if ($title === '' || mb_strlen($title) > 100) {
        jsonResponse(false, null, 'Invalid title (1–100 characters required)');
        return;
    }
    $settingsFile = dirname(__DIR__) . '/cache/site-settings.json';
    $cacheDir = dirname($settingsFile);
    if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);
    $existing = file_exists($settingsFile) ? (json_decode(file_get_contents($settingsFile), true) ?? []) : [];
    $existing['site_title'] = $title;
    $existing['updated_at'] = time();
    $ok = file_put_contents($settingsFile, json_encode($existing));
    if ($ok !== false) {
        jsonResponse(true, ['site_title' => $title], 'Title saved');
    } else {
        jsonResponse(false, null, 'Failed to save title setting');
    }
}

function getDisclaimerSetting() {
    require_api_admin_role();
    $settingsFile = dirname(__DIR__) . '/cache/site-settings.json';
    $data = file_exists($settingsFile) ? (json_decode(file_get_contents($settingsFile), true) ?? []) : [];
    $esc = fn($v) => htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    jsonResponse(true, [
        'disclaimer_th' => $esc($data['disclaimer_th'] ?? ''),
        'disclaimer_en' => $esc($data['disclaimer_en'] ?? ''),
        'disclaimer_ja' => $esc($data['disclaimer_ja'] ?? ''),
    ]);
}

function saveDisclaimerSetting() {
    require_api_admin_role();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST required');
        return;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $settingsFile = dirname(__DIR__) . '/cache/site-settings.json';
    $cacheDir = dirname($settingsFile);
    if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);
    $existing = file_exists($settingsFile) ? (json_decode(file_get_contents($settingsFile), true) ?? []) : [];
    $existing['disclaimer_th'] = isset($input['disclaimer_th']) ? trim($input['disclaimer_th']) : '';
    $existing['disclaimer_en'] = isset($input['disclaimer_en']) ? trim($input['disclaimer_en']) : '';
    $existing['disclaimer_ja'] = isset($input['disclaimer_ja']) ? trim($input['disclaimer_ja']) : '';
    $existing['updated_at'] = time();
    $ok = file_put_contents($settingsFile, json_encode($existing), LOCK_EX);
    if ($ok !== false) {
        jsonResponse(true, null, 'Disclaimer saved');
    } else {
        jsonResponse(false, null, 'Failed to save disclaimer');
    }
}

// =============================================================================
// TELEGRAM CONFIG
// =============================================================================

function getTelegramConfig() {
    require_api_admin_role();
    $configFile = __DIR__ . '/../config/telegram-config.json';

    // Default structure
    $default = [
        'bot_token' => '',
        'bot_username' => '',
        'webhook_secret' => '',
        'notify_before_minutes' => 60,
        'enabled' => false,
        'webhook_status' => 'not_configured',
        'last_webhook_test' => null,
        'updated_at' => date('c')
    ];

    $escStringFields = function(array &$cfg): void {
        foreach (['bot_token', 'bot_username', 'webhook_secret'] as $f) {
            if (isset($cfg[$f]) && is_string($cfg[$f])) {
                $cfg[$f] = htmlspecialchars($cfg[$f], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
        }
    };

    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        if (is_array($config)) {
            $config = array_merge($default, $config);
            $escStringFields($config);
            jsonResponse(true, $config);
            return;
        }
    }

    $escStringFields($default);
    jsonResponse(true, $default);
}

function saveTelegramConfig() {
    require_api_admin_role();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST required');
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $configFile = __DIR__ . '/../config/telegram-config.json';
    $configDir = dirname($configFile);

    if (!is_dir($configDir)) {
        mkdir($configDir, 0755, true);
    }

    // Load existing config
    $existing = [];
    if (file_exists($configFile)) {
        $existing = json_decode(file_get_contents($configFile), true) ?? [];
    }

    // Update with new values
    $existing['bot_token'] = trim($input['bot_token'] ?? '');
    $existing['bot_username'] = trim($input['bot_username'] ?? '');
    $existing['webhook_secret'] = trim($input['webhook_secret'] ?? '');
    $existing['notify_before_minutes'] = intval($input['notify_before_minutes'] ?? 60);
    $existing['daily_summary_start_hour'] = intval($input['daily_summary_start_hour'] ?? 9);
    $existing['daily_summary_start_minute'] = intval($input['daily_summary_start_minute'] ?? 0);
    $existing['daily_summary_end_hour'] = intval($input['daily_summary_end_hour'] ?? 9);
    $existing['daily_summary_end_minute'] = intval($input['daily_summary_end_minute'] ?? 30);
    $existing['enabled'] = (bool)($input['enabled'] ?? false);
    $existing['updated_at'] = date('c');

    // Keep existing webhook status if not explicitly set
    if (!isset($existing['webhook_status'])) {
        $existing['webhook_status'] = 'not_configured';
    }

    // Write config file
    $ok = file_put_contents($configFile, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    if ($ok !== false) {
        jsonResponse(true, $existing, 'Telegram config saved');
    } else {
        jsonResponse(false, null, 'Failed to save telegram config');
    }
}

function testTelegramWebhook() {
    require_api_admin_role();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST required');
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $botToken = trim($input['bot_token'] ?? '');

    if (!$botToken) {
        jsonResponse(false, null, 'Bot token required');
        return;
    }

    // Get webhook secret and URL from config
    $configFile = __DIR__ . '/../config/telegram-config.json';
    $config = [];
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true) ?? [];
    }

    $webhookSecret = trim($config['webhook_secret'] ?? '');
    if (!$webhookSecret) {
        jsonResponse(false, null, 'Webhook secret not configured');
        return;
    }

    // Determine webhook URL (must include subdirectory if not at root)
    $protocol = 'https'; // Telegram requires HTTPS
    $host = get_safe_host();

    // Calculate app root path (remove /admin/api.php from script name)
    // e.g., /idoltrack/admin/api.php -> /idoltrack
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $appRoot = dirname(dirname($scriptName));  // Go up 2 levels: api.php -> admin -> root
    if ($appRoot === '.' || $appRoot === '\\' || $appRoot === '/') {
        $appRoot = '';
    }

    $baseUrl = $protocol . '://' . $host . $appRoot;
    $webhookUrl = $baseUrl . '/api/telegram';

    // Call Telegram setWebhook to register the webhook
    $setWebhookUrl = 'https://api.telegram.org/bot' . $botToken . '/setWebhook';
    $postData = json_encode([
        'url' => $webhookUrl,
        'allowed_updates' => ['message', 'callback_query'],
        'secret_token' => $webhookSecret,
        'drop_pending_updates' => true
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $setWebhookUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Update webhook status in config
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true) ?? [];

        if ($httpCode === 200 && !$curlError) {
            $result = json_decode($response, true);
            if ($result['ok'] ?? false) {
                $config['webhook_status'] = 'ok';
                $config['webhook_url'] = $webhookUrl;
            } else {
                $config['webhook_status'] = 'error';
                $config['webhook_error'] = $result['description'] ?? 'Unknown error';
            }
        } else {
            $config['webhook_status'] = 'error';
            $config['webhook_error'] = $curlError ?: ('HTTP ' . $httpCode);
        }

        $config['last_webhook_test'] = date('c');
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    // Return response
    if ($httpCode === 200 && !$curlError) {
        $result = json_decode($response, true);
        if ($result['ok'] ?? false) {
            jsonResponse(true, null, 'Webhook registered successfully: ' . $webhookUrl);
            return;
        }
    }

    $errorMsg = $curlError ?: ('HTTP ' . $httpCode);
    jsonResponse(false, null, 'Failed to register webhook: ' . $errorMsg);
}

function registerTelegramWebhook() {
    require_api_admin_role();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST required');
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $botToken = trim($input['bot_token'] ?? '');
    $webhookSecret = trim($input['webhook_secret'] ?? '');

    if (!$botToken) {
        jsonResponse(false, null, 'Bot token required');
        return;
    }

    if (!$webhookSecret) {
        jsonResponse(false, null, 'Webhook secret required');
        return;
    }

    // Determine webhook URL (must include subdirectory if not at root)
    $protocol = 'https'; // Telegram requires HTTPS
    $host = get_safe_host();

    // Calculate app root path (remove /admin/api.php from script name)
    // e.g., /idoltrack/admin/api.php -> /idoltrack
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $appRoot = dirname(dirname($scriptName));  // Go up 2 levels: api.php -> admin -> root
    if ($appRoot === '.' || $appRoot === '\\' || $appRoot === '/') {
        $appRoot = '';
    }

    $baseUrl = $protocol . '://' . $host . $appRoot;
    $webhookUrl = $baseUrl . '/api/telegram';

    // Call Telegram setWebhook to register the webhook
    $setWebhookUrl = 'https://api.telegram.org/bot' . $botToken . '/setWebhook';
    $postData = json_encode([
        'url' => $webhookUrl,
        'allowed_updates' => ['message', 'callback_query'],
        'secret_token' => $webhookSecret,
        'drop_pending_updates' => true
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $setWebhookUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Update webhook status in config
    $configFile = __DIR__ . '/../config/telegram-config.json';
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true) ?? [];

        if ($httpCode === 200 && !$curlError) {
            $result = json_decode($response, true);
            if ($result['ok'] ?? false) {
                $config['webhook_status'] = 'ok';
                $config['webhook_url'] = $webhookUrl;
            } else {
                $config['webhook_status'] = 'error';
                $config['webhook_error'] = $result['description'] ?? 'Unknown error';
            }
        } else {
            $config['webhook_status'] = 'error';
            $config['webhook_error'] = $curlError ?: ('HTTP ' . $httpCode);
        }

        $config['last_webhook_register'] = date('c');
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    // Return response
    if ($httpCode === 200 && !$curlError) {
        $result = json_decode($response, true);
        if ($result['ok'] ?? false) {
            jsonResponse(true, null, 'Webhook registered successfully at: ' . $webhookUrl);
            return;
        }
    }

    $errorMsg = $curlError ?: ('HTTP ' . $httpCode);
    jsonResponse(false, null, 'Failed to register webhook: ' . $errorMsg);
}

function getTelegramLog() {
    require_login();
    // GET endpoint - no CSRF token needed

    $logDir = __DIR__ . '/../cache/logs';

    // Build list of available log files
    $files = [];

    // Current active log
    if (file_exists($logDir . '/telegram-cron.log')) {
        $files[] = [
            'key' => 'current',
            'label' => 'telegram-cron.log (current)',
            'path' => $logDir . '/telegram-cron.log'
        ];
    }

    // Dated archives: telegram-cron-YYYY-MM-DD.log
    $archives = glob($logDir . '/telegram-cron-[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9].log') ?: [];
    if (!empty($archives)) {
        rsort($archives); // newest first
        foreach ($archives as $archive) {
            $basename = basename($archive);
            $files[] = [
                'key' => $basename,
                'label' => $basename,
                'path' => $archive
            ];
        }
    }

    // Determine which file to read
    $requestedKey = get_sanitized_param('file', '');
    $selectedPath = null;

    foreach ($files as $f) {
        if ($f['key'] === $requestedKey) {
            $selectedPath = $f['path'];
            break;
        }
    }

    // Default to first available file if not found
    if (!$selectedPath && !empty($files)) {
        $selectedPath = $files[0]['path'];
        $requestedKey = $files[0]['key'];
    }

    // Read file content
    $content = '';
    $totalLines = 0;

    if ($selectedPath && file_exists($selectedPath)) {
        $lines = @file($selectedPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $totalLines = count($lines);

        // Show last 500 lines to prevent memory issues
        $maxLines = 500;
        $lastLines = array_slice($lines, -$maxLines);
        $content = implode("\n", $lastLines);
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'files' => array_map(fn($f) => ['key' => $f['key'], 'label' => $f['label']], $files),
        'selected' => $requestedKey,
        'content' => $content,
        'total_lines' => $totalLines,
        'showing_lines' => min($totalLines, 500)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function downloadTelegramLog() {
    require_login();
    require_api_admin_role();
    // GET endpoint

    $logDir = __DIR__ . '/../cache/logs';
    $requestedFile = get_sanitized_param('file', 'telegram-cron.log');

    // Validate filename - only allow telegram-cron.log or telegram-cron-YYYY-MM-DD.log pattern
    $isValid = $requestedFile === 'telegram-cron.log' ||
              preg_match('/^telegram-cron-\d{4}-\d{2}-\d{2}(?:-daily)?\.log$/', $requestedFile);

    if (!$isValid) {
        jsonResponse(false, null, 'Invalid filename');
        return;
    }

    $filePath = $logDir . '/' . $requestedFile;

    if (!file_exists($filePath)) {
        jsonResponse(false, null, 'Log file not found');
        return;
    }

    // Send file for download
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $requestedFile . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

    readfile($filePath);
    exit;
}

// =============================================================================
// CONTACT CHANNELS
// =============================================================================

function ensureContactChannelsTable() {
    global $db;
    $db->exec("CREATE TABLE IF NOT EXISTS contact_channels (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        icon TEXT DEFAULT '',
        title TEXT NOT NULL DEFAULT '',
        description TEXT DEFAULT '',
        url TEXT DEFAULT '',
        display_order INTEGER DEFAULT 0,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
}

function listContactChannels() {
    global $db;
    ensureContactChannelsTable();
    $stmt = $db->query("SELECT * FROM contact_channels ORDER BY display_order ASC, id ASC");
    $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $fields = ['icon', 'title', 'description', 'url'];
    $channels = array_map(fn($ch) => escapeOutputData($ch, $fields), $channels);
    jsonResponse(true, $channels);
}

function getContactChannel() {
    global $db;
    ensureContactChannelsTable();
    $id = intval($_GET['id'] ?? 0);
    $stmt = $db->prepare("SELECT * FROM contact_channels WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $channel = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$channel) {
        jsonResponse(false, null, 'Channel not found');
        return;
    }
    $channel = escapeOutputData($channel, ['icon', 'title', 'description', 'url']);
    jsonResponse(true, $channel);
}

function createContactChannel() {
    global $db;
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST required');
        return;
    }
    ensureContactChannelsTable();
    $input = json_decode(file_get_contents('php://input'), true);
    $title = trim($input['title'] ?? '');
    if ($title === '') {
        jsonResponse(false, null, 'Title is required');
        return;
    }
    $stmt = $db->prepare("INSERT INTO contact_channels (icon, title, description, url, display_order, is_active)
                          VALUES (:icon, :title, :description, :url, :display_order, :is_active)");
    $stmt->execute([
        ':icon'          => trim($input['icon'] ?? ''),
        ':title'         => $title,
        ':description'   => trim($input['description'] ?? ''),
        ':url'           => trim($input['url'] ?? ''),
        ':display_order' => intval($input['display_order'] ?? 0),
        ':is_active'     => isset($input['is_active']) ? (int)$input['is_active'] : 1,
    ]);
    jsonResponse(true, ['id' => $db->lastInsertId()], 'Channel created');
}

function updateContactChannel() {
    global $db;
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        jsonResponse(false, null, 'PUT required');
        return;
    }
    ensureContactChannelsTable();
    $id = intval($_GET['id'] ?? 0);
    $input = json_decode(file_get_contents('php://input'), true);
    $title = trim($input['title'] ?? '');
    if ($title === '') {
        jsonResponse(false, null, 'Title is required');
        return;
    }
    $stmt = $db->prepare("UPDATE contact_channels SET
        icon = :icon, title = :title, description = :description,
        url = :url, display_order = :display_order, is_active = :is_active
        WHERE id = :id");
    $stmt->execute([
        ':icon'          => trim($input['icon'] ?? ''),
        ':title'         => $title,
        ':description'   => trim($input['description'] ?? ''),
        ':url'           => trim($input['url'] ?? ''),
        ':display_order' => intval($input['display_order'] ?? 0),
        ':is_active'     => isset($input['is_active']) ? (int)$input['is_active'] : 1,
        ':id'            => $id,
    ]);
    jsonResponse(true, null, 'Channel updated');
}

function deleteContactChannel() {
    global $db;
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        jsonResponse(false, null, 'DELETE required');
        return;
    }
    ensureContactChannelsTable();
    $id = intval($_GET['id'] ?? 0);
    $stmt = $db->prepare("DELETE FROM contact_channels WHERE id = :id");
    $stmt->execute([':id' => $id]);
    jsonResponse(true, null, 'Channel deleted');
}

// ============================================================
// Artists CRUD
// ============================================================

/**
 * List artists with pagination, search, and type filter
 */
function listArtists() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(false, null, 'GET method required');
        return;
    }

    $page  = max(1, intval($_GET['page'] ?? 1));
    $limit = max(1, min(100, intval($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;
    $search = substr($_GET['search'] ?? '', 0, 200);

    // Filter: '' = all, '1' = groups only, '0' = non-groups only
    $isGroupFilter = isset($_GET['is_group']) && $_GET['is_group'] !== '' ? intval($_GET['is_group']) : null;

    $allowedSortColumns = ['id', 'name', 'is_group', 'created_at'];
    $sortColumn = in_array($_GET['sort'] ?? '', $allowedSortColumns) ? $_GET['sort'] : 'name';
    $sortOrder  = ($_GET['order'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

    $where  = [];
    $params = [];

    if ($search !== '') {
        $searchEscaped = str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $search);
        $where[]       = "a.name LIKE :search ESCAPE '\\'";
        $params[':search'] = '%' . $searchEscaped . '%';
    }

    if ($isGroupFilter !== null) {
        $where[]           = "a.is_group = :is_group";
        $params[':is_group'] = $isGroupFilter;
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    try {
        $countSql = "SELECT COUNT(*) as total FROM artists a $whereClause";
        $stmt     = $db->prepare($countSql);
        $stmt->execute($params);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Check if artist_variants table exists (may not exist on older installs)
        $hasVariantsTable = (bool)$db->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='artist_variants'"
        )->fetch();

        $variantCountExpr = $hasVariantsTable
            ? "(SELECT COUNT(*) FROM artist_variants av WHERE av.artist_id = a.id)"
            : "0";

        $sql = "
            SELECT a.id, a.name, a.is_group, a.group_id, a.created_at,
                   g.name AS group_name,
                   $variantCountExpr AS variant_count,
                   (SELECT COUNT(*) FROM artists m WHERE m.group_id = a.id AND m.is_group = 0) AS member_count
            FROM artists a
            LEFT JOIN artists g ON a.group_id = g.id
            $whereClause
            ORDER BY a.$sortColumn $sortOrder
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $artists = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $artists = array_map(function($a) {
            return escapeOutputData($a, ['name', 'group_name']);
        }, $artists);

        jsonResponse(true, [
            'artists'    => $artists,
            'pagination' => [
                'page'       => $page,
                'limit'      => $limit,
                'total'      => $total,
                'totalPages' => (int)ceil($total / $limit),
            ],
        ]);
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to fetch artists', $e->getMessage()));
    }
}

/**
 * Lightweight artist autocomplete
 * Returns id, name, is_group for names matching ?q= (up to 20 results).
 * Used by the Artist/Group tag-input widget in the program form.
 */
function autocompleteArtists() {
    global $db;
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(false, null, 'GET method required');
        return;
    }
    $q = substr(trim($_GET['q'] ?? ''), 0, 200);
    try {
        if ($q === '') {
            $stmt = $db->query("SELECT id, name, is_group FROM artists ORDER BY name ASC LIMIT 50");
        } else {
            $escaped = str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $q);
            $stmt = $db->prepare("SELECT id, name, is_group FROM artists WHERE name LIKE :q ESCAPE '\\' ORDER BY name ASC LIMIT 20");
            $stmt->execute([':q' => '%' . $escaped . '%']);
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $data = array_map(fn($r) => [
            'id'       => (int)$r['id'],
            'name'     => $r['name'],
            'is_group' => (bool)$r['is_group'],
        ], $rows);
        jsonResponse(true, $data);
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Autocomplete failed', $e->getMessage()));
    }
}

/**
 * Get single artist by ID
 */
function getArtist() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(false, null, 'GET method required');
        return;
    }

    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(false, null, 'Valid artist ID required');
        return;
    }

    try {
        $stmt = $db->prepare("
            SELECT a.id, a.name, a.is_group, a.group_id,
                   g.name AS group_name
            FROM artists a
            LEFT JOIN artists g ON a.group_id = g.id
            WHERE a.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $artist = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$artist) {
            jsonResponse(false, null, 'Artist not found');
            return;
        }

        $artist = escapeOutputData($artist, ['name', 'group_name']);
        jsonResponse(true, $artist);
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to fetch artist', $e->getMessage()));
    }
}

/**
 * Create new artist
 */
function createArtist() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST method required');
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $name  = trim($input['name'] ?? '');

    if ($name === '') {
        jsonResponse(false, null, 'Name is required');
        return;
    }
    if (strlen($name) > 200) {
        jsonResponse(false, null, 'Name is too long (max 200 characters)');
        return;
    }

    $isGroup = empty($input['is_group']) ? 0 : 1;
    $groupId = isset($input['group_id']) && $input['group_id'] !== '' ? intval($input['group_id']) : null;

    // group_id only valid for non-groups
    if ($isGroup) {
        $groupId = null;
    }

    try {
        $now  = date('Y-m-d H:i:s');
        $stmt = $db->prepare("
            INSERT INTO artists (name, is_group, group_id, created_at, updated_at)
            VALUES (:name, :is_group, :group_id, :created_at, :updated_at)
        ");
        $stmt->execute([
            ':name'       => $name,
            ':is_group'   => $isGroup,
            ':group_id'   => $groupId,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        $id = $db->lastInsertId();
        invalidate_data_version_cache();
        invalidate_artist_query_cache();
        jsonResponse(true, ['id' => $id], 'Artist created successfully');
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'UNIQUE') !== false) {
            jsonResponse(false, null, 'Artist name already exists');
        } else {
            jsonResponse(false, null, safe_error_message('Failed to create artist', $e->getMessage()));
        }
    }
}

/**
 * Update existing artist
 */
function updateArtist() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        jsonResponse(false, null, 'PUT method required');
        return;
    }

    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(false, null, 'Valid artist ID required');
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $name  = trim($input['name'] ?? '');

    if ($name === '') {
        jsonResponse(false, null, 'Name is required');
        return;
    }
    if (strlen($name) > 200) {
        jsonResponse(false, null, 'Name is too long (max 200 characters)');
        return;
    }

    $isGroup = empty($input['is_group']) ? 0 : 1;
    $groupId = isset($input['group_id']) && $input['group_id'] !== '' ? intval($input['group_id']) : null;

    if ($isGroup) {
        $groupId = null;
    }

    // Prevent self-reference
    if ($groupId !== null && $groupId === $id) {
        jsonResponse(false, null, 'An artist cannot be a member of itself');
        return;
    }

    try {
        $stmt = $db->prepare("
            UPDATE artists
            SET name = :name, is_group = :is_group, group_id = :group_id, updated_at = :updated_at
            WHERE id = :id
        ");
        $stmt->execute([
            ':name'       => $name,
            ':is_group'   => $isGroup,
            ':group_id'   => $groupId,
            ':updated_at' => date('Y-m-d H:i:s'),
            ':id'         => $id,
        ]);

        if ($stmt->rowCount() === 0) {
            jsonResponse(false, null, 'Artist not found or no changes made');
            return;
        }

        invalidate_data_version_cache();
        invalidate_artist_query_cache();
        jsonResponse(true, null, 'Artist updated successfully');
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'UNIQUE') !== false) {
            jsonResponse(false, null, 'Artist name already exists');
        } else {
            jsonResponse(false, null, safe_error_message('Failed to update artist', $e->getMessage()));
        }
    }
}

/**
 * Delete artist
 */
function deleteArtist() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        jsonResponse(false, null, 'DELETE method required');
        return;
    }

    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(false, null, 'Valid artist ID required');
        return;
    }

    try {
        // Check if this artist is used as a group by other artists
        $stmt = $db->prepare("SELECT COUNT(*) FROM artists WHERE group_id = :id");
        $stmt->execute([':id' => $id]);
        $memberCount = $stmt->fetchColumn();

        if ($memberCount > 0) {
            jsonResponse(false, null, "Cannot delete: this artist is a group with $memberCount member(s). Reassign members first.");
            return;
        }

        $stmt = $db->prepare("DELETE FROM artists WHERE id = :id");
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() === 0) {
            jsonResponse(false, null, 'Artist not found');
            return;
        }

        invalidate_data_version_cache();
        invalidate_artist_query_cache();
        jsonResponse(true, null, 'Artist deleted successfully');
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to delete artist', $e->getMessage()));
    }
}

/**
 * List groups only (for group_id dropdown in artist modal)
 */
function listArtistGroups() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(false, null, 'GET method required');
        return;
    }

    try {
        $stmt   = $db->query("SELECT id, name FROM artists WHERE is_group = 1 ORDER BY name ASC");
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $groups = array_map(function($g) {
            return escapeOutputData($g, ['name']);
        }, $groups);

        jsonResponse(true, ['groups' => $groups]);
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to fetch groups', $e->getMessage()));
    }
}

/**
 * List variants for an artist
 */
function listArtistVariants() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(false, null, 'GET method required');
        return;
    }

    $artistId = isset($_GET['artist_id']) ? (int)$_GET['artist_id'] : 0;
    if (!$artistId) {
        jsonResponse(false, null, 'artist_id required');
        return;
    }

    try {
        $stmt = $db->prepare("
            SELECT id, variant, created_at
            FROM artist_variants
            WHERE artist_id = ?
            ORDER BY variant ASC
        ");
        $stmt->execute([$artistId]);
        $variants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $variants = array_map(fn($v) => escapeOutputData($v, ['variant']), $variants);

        jsonResponse(true, ['variants' => $variants]);
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to fetch variants', $e->getMessage()));
    }
}

/**
 * Create (add) a variant for an artist
 */
function createArtistVariant() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST method required');
        return;
    }

    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $artistId = isset($body['artist_id']) ? (int)$body['artist_id'] : 0;
    $variant  = trim($body['variant'] ?? '');

    if (!$artistId) {
        jsonResponse(false, null, 'artist_id required');
        return;
    }
    if ($variant === '') {
        jsonResponse(false, null, 'variant cannot be empty');
        return;
    }
    if (mb_strlen($variant, 'UTF-8') > 200) {
        jsonResponse(false, null, 'variant too long (max 200 characters)');
        return;
    }

    try {
        // Check artist exists
        $check = $db->prepare("SELECT id FROM artists WHERE id = ?");
        $check->execute([$artistId]);
        if (!$check->fetch()) {
            jsonResponse(false, null, 'Artist not found');
            return;
        }

        // Check table exists (idempotent — table may not exist on older installs)
        $tableExists = $db->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='artist_variants'"
        )->fetch();
        if (!$tableExists) {
            jsonResponse(false, null, 'artist_variants table not found. Run: php tools/migrate-add-artist-variants-table.php');
            return;
        }

        $stmt = $db->prepare("INSERT OR IGNORE INTO artist_variants (artist_id, variant) VALUES (?, ?)");
        $stmt->execute([$artistId, $variant]);
        $newId = $db->lastInsertId();

        if (!$newId) {
            jsonResponse(false, null, 'Variant already exists for this artist');
            return;
        }

        invalidate_artist_query_cache();
        jsonResponse(true, ['id' => (int)$newId, 'variant' => $variant], 'Variant added');
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to create variant', $e->getMessage()));
    }
}

/**
 * Delete a variant
 */
function deleteArtistVariant() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        jsonResponse(false, null, 'DELETE method required');
        return;
    }

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) {
        jsonResponse(false, null, 'id required');
        return;
    }

    try {
        $stmt = $db->prepare("DELETE FROM artist_variants WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            jsonResponse(false, null, 'Variant not found');
            return;
        }

        invalidate_artist_query_cache();
        jsonResponse(true, null, 'Variant deleted');
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to delete variant', $e->getMessage()));
    }
}

/**
 * Clone an artist (duplicate with all variants)
 */
/**
 * Bulk set group_id for multiple artists
 */
function artistsBulkSetGroup() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST method required');
        return;
    }

    $input    = json_decode(file_get_contents('php://input'), true);
    $ids      = $input['ids'] ?? [];
    $groupId  = isset($input['group_id']) && $input['group_id'] !== '' ? intval($input['group_id']) : null;

    if (empty($ids) || !is_array($ids)) {
        jsonResponse(false, null, 'No artist IDs provided');
        return;
    }
    if (count($ids) > 200) {
        jsonResponse(false, null, 'Too many artists (max 200)');
        return;
    }

    $ids = array_map('intval', $ids);
    $ids = array_filter($ids, fn($i) => $i > 0);

    if (empty($ids)) {
        jsonResponse(false, null, 'No valid artist IDs');
        return;
    }

    // Validate group exists (if set)
    if ($groupId !== null) {
        $stmt = $db->prepare("SELECT id, is_group FROM artists WHERE id = ?");
        $stmt->execute([$groupId]);
        $group = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        $stmt = null;

        if (!$group) {
            jsonResponse(false, null, 'Group not found');
            return;
        }
        if (!$group['is_group']) {
            jsonResponse(false, null, 'Selected artist is not a group');
            return;
        }
        // Remove group itself from the ids to prevent self-reference
        $ids = array_values(array_diff($ids, [$groupId]));
    }

    if (empty($ids)) {
        jsonResponse(false, null, 'No valid artist IDs after filtering');
        return;
    }

    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("
            UPDATE artists
            SET group_id = ?, updated_at = ?
            WHERE id IN ($placeholders) AND is_group = 0
        ");
        $params = array_merge([$groupId, date('Y-m-d H:i:s')], $ids);
        $stmt->execute($params);
        $affected = $stmt->rowCount();
        $stmt->closeCursor();
        $stmt = null;

        invalidate_data_version_cache();
        invalidate_artist_query_cache();
        jsonResponse(true, ['affected' => $affected], "Updated $affected artist(s)");
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to update artists', $e->getMessage()));
    }
}

/**
 * Bulk import artists from a list of names
 */
function artistsBulkImport() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST method required');
        return;
    }

    $input   = json_decode(file_get_contents('php://input'), true);
    $names   = $input['names'] ?? [];
    $isGroup = empty($input['is_group']) ? 0 : 1;
    $groupId = isset($input['group_id']) && $input['group_id'] !== '' ? intval($input['group_id']) : null;

    if (empty($names) || !is_array($names)) {
        jsonResponse(false, null, 'No names provided');
        return;
    }
    if (count($names) > 500) {
        jsonResponse(false, null, 'Too many names (max 500 per import)');
        return;
    }

    // Groups cannot have a parent group
    if ($isGroup) {
        $groupId = null;
    }

    // Validate group if specified
    if ($groupId !== null) {
        $stmt = $db->prepare("SELECT id, is_group FROM artists WHERE id = ?");
        $stmt->execute([$groupId]);
        $group = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        $stmt = null;
        if (!$group || !$group['is_group']) {
            jsonResponse(false, null, 'Invalid group ID');
            return;
        }
    }

    $results = [];
    $now     = date('Y-m-d H:i:s');
    $created = 0;

    foreach ($names as $rawName) {
        $name = trim((string)$rawName);
        if ($name === '') continue;
        if (strlen($name) > 200) {
            $results[] = ['name' => $name, 'status' => 'error', 'message' => 'ชื่อยาวเกิน 200 ตัวอักษร'];
            continue;
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO artists (name, is_group, group_id, created_at, updated_at)
                VALUES (:name, :is_group, :group_id, :created_at, :updated_at)
            ");
            $stmt->execute([
                ':name'       => $name,
                ':is_group'   => $isGroup,
                ':group_id'   => $groupId,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            $newId = $db->lastInsertId();
            $stmt->closeCursor();
            $stmt = null;
            $results[] = ['name' => $name, 'status' => 'created', 'id' => $newId];
            $created++;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'UNIQUE') !== false) {
                $results[] = ['name' => $name, 'status' => 'duplicate'];
            } else {
                $results[] = ['name' => $name, 'status' => 'error', 'message' => 'Database error'];
            }
        }
    }

    if ($created > 0) {
        invalidate_data_version_cache();
        invalidate_artist_query_cache();
    }

    jsonResponse(true, ['results' => $results, 'created' => $created], "Import เสร็จสิ้น: สร้าง {$created} คน");
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
