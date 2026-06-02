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

// Audit context — set request_id, IP, UA once per request
audit_api_context();

// CSRF Protection: Validate token for state-changing requests (POST, PUT, DELETE)
require_csrf_token();

// ── Helper: validate social/ticket URL (http/https only, or null) ─────────────
function sanitize_social_url(string $raw): ?string {
    $url = trim($raw);
    return ($url !== '' && preg_match('/^https?:\/\//i', $url)) ? $url : null;
}

// Database connection
$db = null;

try {
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    jsonResponse(false, null, safe_error_message('Database connection failed', $e->getMessage()));
    exit;
}

function adminTwofaColumnsExist(PDO $db): bool {
    $flagFile = dirname(DB_PATH) . '/.admin_2fa_columns_ready';
    if (is_file($flagFile)) {
        return true;
    }

    try {
        $table = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='admin_users'")->fetch();
        if (!$table) {
            return false;
        }
        $columns = $db->query("PRAGMA table_info(admin_users)")->fetchAll(PDO::FETCH_COLUMN, 1);
        foreach (['twofa_enabled', 'twofa_secret', 'twofa_backup_codes', 'twofa_confirmed_at', 'twofa_last_used_step'] as $name) {
            if (!in_array($name, $columns, true)) {
                return false;
            }
        }
        @file_put_contents($flagFile, date('c'), LOCK_EX);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

$adminTwofaColumnsExist = adminTwofaColumnsExist($db);

function adminTableExists(PDO $db, string $table): bool {
    $stmt = $db->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = :name");
    $stmt->execute([':name' => $table]);
    return (bool)$stmt->fetchColumn();
}

function adminColumnExists(PDO $db, string $table, string $column): bool {
    if (!adminTableExists($db, $table)) {
        return false;
    }
    $columns = $db->query("PRAGMA table_info(" . $table . ")")->fetchAll(PDO::FETCH_COLUMN, 1);
    return in_array($column, $columns, true);
}

function adminCountScalar(PDO $db, string $sql, array $params = []): int {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return intval($stmt->fetchColumn() ?: 0);
}

function apiForbidden(string $message = 'Forbidden') {
    http_response_code(403);
    jsonResponse(false, null, $message);
}

function requireApiAdminOrAgentRole(): void {
    if (!(is_admin_role() || is_agent_role())) {
        apiForbidden('Admin or agent role required');
    }
}

function requireApiOrganizerRole(): void {
    if (!isOrganizerRequest()) {
        apiForbidden('Organizer role required');
    }
}

function isOrganizerRequest(): bool {
    return function_exists('is_organizer_role') && is_organizer_role();
}

function organizerUserId(): ?int {
    $userId = function_exists('current_admin_user_id') ? current_admin_user_id() : ($_SESSION['admin_user_id'] ?? null);
    return $userId === null ? null : intval($userId);
}

function eventOrganizerSchemaReady(PDO $db): bool {
    return adminTableExists($db, 'event_organizers') && adminColumnExists($db, 'events', 'created_by_user_id');
}

function organizerEventWhere(string $eventColumn = 'event_id'): string {
    return "$eventColumn IN (SELECT event_id FROM event_organizers WHERE user_id = :organizer_user_id)";
}

function bindOrganizerUser(PDOStatement $stmt): void {
    $stmt->bindValue(':organizer_user_id', organizerUserId() ?? 0, PDO::PARAM_INT);
}

function requireCanManageEventId(int $eventId): void {
    if (!can_manage_event($eventId)) {
        apiForbidden('You do not have permission to manage this event');
    }
}

function requireCanManageProgramId(PDO $db, int $programId): void {
    $stmt = $db->prepare("SELECT event_id FROM programs WHERE id = :id");
    $stmt->execute([':id' => $programId]);
    $eventId = $stmt->fetchColumn();
    if ($eventId === false) {
        jsonResponse(false, null, 'Event not found');
    }
    if (!$eventId || !can_manage_event((int)$eventId)) {
        apiForbidden('You do not have permission to manage this program');
    }
}

function requireCanManageProgramIds(PDO $db, array $ids): void {
    if (!isOrganizerRequest()) {
        return;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT COUNT(*) FROM programs WHERE id IN ($placeholders) AND event_id IN (SELECT event_id FROM event_organizers WHERE user_id = ?)");
    $stmt->execute(array_merge($ids, [organizerUserId() ?? 0]));
    if (intval($stmt->fetchColumn()) !== count($ids)) {
        apiForbidden('All selected programs must belong to events you manage');
    }
}

function requireCanManageCreditId(PDO $db, int $creditId): void {
    $stmt = $db->prepare("SELECT event_id FROM credits WHERE id = :id");
    $stmt->execute([':id' => $creditId]);
    $eventId = $stmt->fetchColumn();
    if ($eventId === false) {
        jsonResponse(false, null, 'Credit not found');
    }
    if (!$eventId || !can_manage_event((int)$eventId)) {
        apiForbidden('You do not have permission to manage this credit');
    }
}

function requireCanManageCreditIds(PDO $db, array $ids): void {
    if (!isOrganizerRequest()) {
        return;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT COUNT(*) FROM credits WHERE id IN ($placeholders) AND event_id IN (SELECT event_id FROM event_organizers WHERE user_id = ?)");
    $stmt->execute(array_merge($ids, [organizerUserId() ?? 0]));
    if (intval($stmt->fetchColumn()) !== count($ids)) {
        apiForbidden('All selected credits must belong to events you manage');
    }
}

// Get action
$action = $_GET['action'] ?? '';

// Role-based access control: restrict admin-only actions
$adminOnlyActions = [
    'backup_create', 'backup_list', 'backup_download',
    'backup_delete', 'backup_restore', 'backup_upload_restore',
    'users_list', 'users_get', 'users_create', 'users_update', 'users_delete',
    'twofa_reset_user',
    'event_organizers_list', 'event_organizers_update',
    // Log viewers — defense in depth (functions also call require_api_admin_role)
    'telegram_log_get', 'telegram_log_download',
    'webpush_log_get', 'webpush_log_download',
    'email_log_get', 'email_log_download',
    'admin_audit_log_get', 'admin_audit_log_download',
];

if (in_array($action, $adminOnlyActions)) {
    require_api_admin_role();
}

if (isOrganizerRequest()) {
    $organizerAllowedActions = [
        'dashboard_stats',
        'programs_list', 'programs_get', 'programs_create', 'programs_update',
        'programs_delete', 'programs_venues', 'programs_types',
        'venues_autocomplete', 'venues_list',
        'artists_autocomplete', 'artists_groups', 'artist_requests_create',
        'programs_bulk_delete', 'programs_bulk_update',
        'credits_list', 'credits_get', 'credits_create', 'credits_update',
        'credits_delete', 'credits_bulk_delete',
        'events_list', 'events_get', 'events_create', 'events_update', 'events_delete',
        'events_request_activate',
        'event_pictures_list', 'event_picture_upload', 'event_picture_delete',
        'event_pictures_reorder',
        'event_cover_upload', 'event_cover_delete',
        'event_header_cover_upload', 'event_header_cover_delete',
        'change_password',
        'twofa_status', 'twofa_begin_setup', 'twofa_confirm_setup',
        'twofa_disable', 'twofa_regenerate_backup_codes',
    ];
    if (!in_array($action, $organizerAllowedActions, true)) {
        apiForbidden('Organizer role does not have access to this action');
    }
    $organizerAccountActions = [
        'change_password', 'twofa_status', 'twofa_begin_setup', 'twofa_confirm_setup',
        'twofa_disable', 'twofa_regenerate_backup_codes',
    ];
    if (!in_array($action, $organizerAccountActions, true) && !eventOrganizerSchemaReady($db)) {
        jsonResponse(false, null, 'Organizer schema not ready. Run: php tools/migrate-add-organizer-role.php');
    }
}

switch ($action) {
    case 'dashboard_stats':
        getDashboardStats();
        break;
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
    case 'artist_requests_list':
        listArtistRequests();
        break;
    case 'artist_requests_create':
        createArtistRequest();
        break;
    case 'artist_request_approve':
        approveArtistRequest();
        break;
    case 'artist_request_reject':
        rejectArtistRequest();
        break;
    // Event Requests CRUD
    case 'event_requests_list':
        listEventRequests();
        break;
    case 'event_request_approve':
        approveEventRequest();
        break;
    case 'event_request_reject':
        rejectEventRequest();
        break;
    case 'event_request_pending_count':
        getEventRequestPendingCount();
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
    case 'events_request_activate':
        requestActivateEvent();
        break;
    case 'event_organizers_list':
        listEventOrganizers();
        break;
    case 'event_organizers_update':
        updateEventOrganizers();
        break;
    // Change Password
    case 'change_password':
        changeAdminPassword();
        break;
    // Two-factor authentication
    case 'twofa_status':
        getTwofaStatus();
        break;
    case 'twofa_begin_setup':
        beginTwofaSetup();
        break;
    case 'twofa_confirm_setup':
        confirmTwofaSetup();
        break;
    case 'twofa_disable':
        disableTwofa();
        break;
    case 'twofa_regenerate_backup_codes':
        regenerateTwofaBackupCodes();
        break;
    case 'twofa_reset_user':
        resetUserTwofa();
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
    case 'site_cover_bg_upload':
        uploadSiteCoverBg();
        break;
    case 'site_cover_bg_delete':
        deleteSiteCoverBg();
        break;
    case 'disclaimer_get':
        getDisclaimerSetting();
        break;
    case 'disclaimer_save':
        saveDisclaimerSetting();
        break;
    // Analytics Config (GA + AdSense)
    case 'analytics_config_get':
        getAnalyticsConfig();
        break;
    case 'analytics_config_save':
        saveAnalyticsConfig();
        break;
    // Email Config
    case 'email_config_get':
        getEmailConfig();
        break;
    case 'email_config_save':
        saveEmailConfig();
        break;
    case 'email_test_send':
        sendEmailTest();
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
    // Web Push Config
    case 'webpush_config_get':
        getWebPushConfig();
        break;
    case 'webpush_config_save':
        saveWebPushConfig();
        break;
    case 'webpush_vapid_generate':
        generateWebPushVapidKeys();
        break;
    // Web Push Log
    case 'webpush_log_get':
        getWebPushLog();
        break;
    case 'webpush_log_download':
        downloadWebPushLog();
        break;
    // Email Log
    case 'email_log_get':
        getEmailLog();
        break;
    case 'email_log_download':
        downloadEmailLog();
        break;
    // Admin Audit Log
    case 'admin_audit_log_get':
        getAdminAuditLog();
        break;
    case 'admin_audit_log_download':
        downloadAdminAuditLog();
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
    case 'artist_picture_upload':
        uploadArtistPicture();
        break;
    case 'artist_picture_delete':
        deleteArtistPicture();
        break;
    case 'event_pictures_list':
        listEventPictures();
        break;
    case 'event_picture_upload':
        uploadEventPicture();
        break;
    case 'event_picture_delete':
        deleteEventPicture();
        break;
    case 'event_pictures_reorder':
        reorderEventPictures();
        break;
    case 'event_cover_upload':
        uploadEventCover();
        break;
    case 'event_cover_delete':
        deleteEventCover();
        break;
    case 'event_header_cover_upload':
        uploadEventHeaderCover();
        break;
    case 'event_header_cover_delete':
        deleteEventHeaderCover();
        break;
    case 'artists_bulk_set_group':
        artistsBulkSetGroup();
        break;
    case 'artists_bulk_import':
        artistsBulkImport();
        break;
    // Venues CRUD + variants + merge (v16.0.0)
    case 'venues_list':
        listVenues();
        break;
    case 'venues_autocomplete':
        autocompleteVenues();
        break;
    case 'venues_get':
        getVenue();
        break;
    case 'venues_create':
        createVenue();
        break;
    case 'venues_update':
        updateVenue();
        break;
    case 'venues_delete':
        deleteVenue();
        break;
    case 'venues_variants_list':
        listVenueVariants();
        break;
    case 'venues_variants_create':
        createVenueVariant();
        break;
    case 'venues_variants_delete':
        deleteVenueVariant();
        break;
    case 'venues_merge':
        mergeVenues();
        break;
    default:
        jsonResponse(false, null, 'Invalid action');
}

/**
 * Dashboard analytics summary (read-only)
 */
function getDashboardStats() {
    global $db, $adminTwofaColumnsExist;

    $role      = $_SESSION['admin_role'] ?? 'agent';
    $isAdmin   = $role === 'admin';
    $isOrganizer = $role === 'organizer';
    $today     = date('Y-m-d');
    $next7Days = date('Y-m-d', strtotime('+7 days'));

    try {
        $hasEvents        = adminTableExists($db, 'events');
        $hasPrograms      = adminTableExists($db, 'programs');
        $hasCredits       = adminTableExists($db, 'credits');
        $hasArtists       = adminTableExists($db, 'artists');
        $hasProgRequests  = adminTableExists($db, 'program_requests');
        $hasEventRequests = adminTableExists($db, 'event_requests');
        $hasArtistRequests = adminTableExists($db, 'artist_requests');
        $hasAdminUsers    = adminTableExists($db, 'admin_users');
        $hasProgramEventId = adminColumnExists($db, 'programs', 'event_id');
        $hasEventCover     = adminColumnExists($db, 'events', 'cover_image');
        $hasEventCardCover = adminColumnExists($db, 'events', 'cover_image_card');
        $hasEventHeader    = adminColumnExists($db, 'events', 'header_cover_image');
        $hasEventTicket    = adminColumnExists($db, 'events', 'ticket_url');
        $hasArtistPicture  = adminColumnExists($db, 'artists', 'display_picture');

        $organizerEventSql = $isOrganizer ? "id IN (SELECT event_id FROM event_organizers WHERE user_id = :organizer_user_id)" : "1=1";
        $organizerProgramSql = $isOrganizer ? "event_id IN (SELECT event_id FROM event_organizers WHERE user_id = :organizer_user_id)" : "1=1";
        $organizerParams = $isOrganizer ? [':organizer_user_id' => organizerUserId() ?? 0] : [];

        $events = [
            'total'    => $hasEvents ? adminCountScalar($db, "SELECT COUNT(*) FROM events WHERE $organizerEventSql", $organizerParams) : 0,
            'active'   => $hasEvents ? adminCountScalar($db, "SELECT COUNT(*) FROM events WHERE is_active = 1 AND $organizerEventSql", $organizerParams) : 0,
            'upcoming' => $hasEvents ? adminCountScalar($db, "SELECT COUNT(*) FROM events WHERE start_date IS NOT NULL AND DATE(start_date) >= :today AND $organizerEventSql", array_merge([':today' => $today], $organizerParams)) : 0,
            'past'     => $hasEvents ? adminCountScalar($db, "SELECT COUNT(*) FROM events WHERE COALESCE(DATE(end_date), DATE(start_date)) < :today AND $organizerEventSql", array_merge([':today' => $today], $organizerParams)) : 0,
        ];

        $programs = [
            'total'      => $hasPrograms ? adminCountScalar($db, "SELECT COUNT(*) FROM programs WHERE $organizerProgramSql", $organizerParams) : 0,
            'today'      => $hasPrograms ? adminCountScalar($db, "SELECT COUNT(*) FROM programs WHERE DATE(start) = :today AND $organizerProgramSql", array_merge([':today' => $today], $organizerParams)) : 0,
            'next7_days' => $hasPrograms ? adminCountScalar($db, "SELECT COUNT(*) FROM programs WHERE DATE(start) BETWEEN :today AND :next7 AND $organizerProgramSql", array_merge([':today' => $today, ':next7' => $next7Days], $organizerParams)) : 0,
        ];

        $credits = [
            'total' => $hasCredits ? adminCountScalar($db, "SELECT COUNT(*) FROM credits WHERE " . ($isOrganizer ? "event_id IN (SELECT event_id FROM event_organizers WHERE user_id = :organizer_user_id)" : "1=1"), $organizerParams) : 0,
        ];

        $artists = [
            'total'   => ($hasArtists && !$isOrganizer) ? adminCountScalar($db, "SELECT COUNT(*) FROM artists") : 0,
            'groups'  => ($hasArtists && !$isOrganizer) ? adminCountScalar($db, "SELECT COUNT(*) FROM artists WHERE is_group = 1") : 0,
            'members' => ($hasArtists && !$isOrganizer) ? adminCountScalar($db, "SELECT COUNT(*) FROM artists WHERE is_group = 0") : 0,
        ];

        $programRequestStatus = [
            'pending'  => ($hasProgRequests && !$isOrganizer) ? adminCountScalar($db, "SELECT COUNT(*) FROM program_requests WHERE status = 'pending'") : 0,
            'approved' => ($hasProgRequests && !$isOrganizer) ? adminCountScalar($db, "SELECT COUNT(*) FROM program_requests WHERE status = 'approved'") : 0,
            'rejected' => ($hasProgRequests && !$isOrganizer) ? adminCountScalar($db, "SELECT COUNT(*) FROM program_requests WHERE status = 'rejected'") : 0,
        ];

        $eventRequestStatus = [
            'pending'  => ($hasEventRequests && !$isOrganizer) ? adminCountScalar($db, "SELECT COUNT(*) FROM event_requests WHERE status = 'pending'") : 0,
            'approved' => ($hasEventRequests && !$isOrganizer) ? adminCountScalar($db, "SELECT COUNT(*) FROM event_requests WHERE status = 'approved'") : 0,
            'rejected' => ($hasEventRequests && !$isOrganizer) ? adminCountScalar($db, "SELECT COUNT(*) FROM event_requests WHERE status = 'rejected'") : 0,
        ];

        $eventGuestRequestStatus = [
            'pending'  => ($hasEventRequests && !$isOrganizer) ? adminCountScalar($db, "SELECT COUNT(*) FROM event_requests WHERE status = 'pending' AND request_type != 'activate'") : 0,
            'approved' => ($hasEventRequests && !$isOrganizer) ? adminCountScalar($db, "SELECT COUNT(*) FROM event_requests WHERE status = 'approved' AND request_type != 'activate'") : 0,
            'rejected' => ($hasEventRequests && !$isOrganizer) ? adminCountScalar($db, "SELECT COUNT(*) FROM event_requests WHERE status = 'rejected' AND request_type != 'activate'") : 0,
        ];

        $eventActiveRequestStatus = [
            'pending'  => ($hasEventRequests && !$isOrganizer) ? adminCountScalar($db, "SELECT COUNT(*) FROM event_requests WHERE status = 'pending' AND request_type = 'activate'") : 0,
            'approved' => ($hasEventRequests && !$isOrganizer) ? adminCountScalar($db, "SELECT COUNT(*) FROM event_requests WHERE status = 'approved' AND request_type = 'activate'") : 0,
            'rejected' => ($hasEventRequests && !$isOrganizer) ? adminCountScalar($db, "SELECT COUNT(*) FROM event_requests WHERE status = 'rejected' AND request_type = 'activate'") : 0,
        ];

        $artistRequestParams = [];
        $artistRequestWhere = "1=1";
        if ($isOrganizer) {
            $artistRequestWhere = "requester_user_id = :organizer_user_id";
            $artistRequestParams[':organizer_user_id'] = organizerUserId() ?? 0;
        }
        $artistRequestStatus = [
            'pending'  => $hasArtistRequests ? adminCountScalar($db, "SELECT COUNT(*) FROM artist_requests WHERE status = 'pending' AND $artistRequestWhere", $artistRequestParams) : 0,
            'approved' => $hasArtistRequests ? adminCountScalar($db, "SELECT COUNT(*) FROM artist_requests WHERE status = 'approved' AND $artistRequestWhere", $artistRequestParams) : 0,
            'rejected' => $hasArtistRequests ? adminCountScalar($db, "SELECT COUNT(*) FROM artist_requests WHERE status = 'rejected' AND $artistRequestWhere", $artistRequestParams) : 0,
        ];

        $upcomingEvents = [];
        if ($hasEvents) {
            $stmt = $db->prepare("
                SELECT e.id, e.name, e.slug, e.start_date, e.end_date, e.is_active,
                       " . ($hasPrograms && $hasProgramEventId ? "(SELECT COUNT(*) FROM programs p WHERE p.event_id = e.id)" : "0") . " AS program_count
                FROM events e
                WHERE e.start_date IS NOT NULL AND DATE(e.start_date) >= :today
                  " . ($isOrganizer ? "AND e.id IN (SELECT event_id FROM event_organizers WHERE user_id = :organizer_user_id)" : "") . "
                ORDER BY DATE(e.start_date) ASC, e.name ASC
                LIMIT 6
            ");
            $params = [':today' => $today];
            if ($isOrganizer) $params[':organizer_user_id'] = organizerUserId() ?? 0;
            $stmt->execute($params);
            $upcomingEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $programsByEvent = [];
        if ($hasEvents) {
            $stmt = $db->query("
                SELECT e.id, e.name, e.slug, e.start_date, e.end_date, e.is_active,
                       " . ($hasPrograms && $hasProgramEventId ? "(SELECT COUNT(*) FROM programs p WHERE p.event_id = e.id)" : "0") . " AS program_count
                FROM events e
                " . ($isOrganizer ? "WHERE e.id IN (SELECT event_id FROM event_organizers WHERE user_id = " . intval(organizerUserId() ?? 0) . ")" : "") . "
                ORDER BY program_count DESC, DATE(e.start_date) DESC, e.name ASC
                LIMIT 8
            ");
            $programsByEvent = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $contentHealth = [
            'events_missing_cover'        => ($hasEvents && $hasEventCover) ? adminCountScalar($db, "SELECT COUNT(*) FROM events WHERE COALESCE(TRIM(cover_image), '') = '' AND $organizerEventSql", $organizerParams) : 0,
            'events_missing_card_cover'   => ($hasEvents && $hasEventCardCover) ? adminCountScalar($db, "SELECT COUNT(*) FROM events WHERE COALESCE(TRIM(cover_image_card), '') = '' AND $organizerEventSql", $organizerParams) : 0,
            'events_missing_header_cover' => ($hasEvents && $hasEventHeader) ? adminCountScalar($db, "SELECT COUNT(*) FROM events WHERE COALESCE(TRIM(header_cover_image), '') = '' AND $organizerEventSql", $organizerParams) : 0,
            'events_missing_ticket_url'   => ($hasEvents && $hasEventTicket) ? adminCountScalar($db, "SELECT COUNT(*) FROM events WHERE COALESCE(TRIM(ticket_url), '') = '' AND $organizerEventSql", $organizerParams) : 0,
            'artists_missing_picture'     => ($hasArtists && $hasArtistPicture && !$isOrganizer) ? adminCountScalar($db, "SELECT COUNT(*) FROM artists WHERE COALESCE(TRIM(display_picture), '') = ''") : 0,
        ];

        $adminOnly = null;
        if ($isAdmin) {
            $adminOnly = [
                'active_admin_users' => $hasAdminUsers ? adminCountScalar($db, "SELECT COUNT(*) FROM admin_users WHERE is_active = 1") : 0,
                'twofa_enabled_users' => ($hasAdminUsers && $adminTwofaColumnsExist) ? adminCountScalar($db, "SELECT COUNT(*) FROM admin_users WHERE twofa_enabled = 1") : 0,
            ];
        }

        jsonResponse(true, [
            'site_title' => get_site_title(),
            'app_version' => APP_VERSION,
            'role' => $role,
            'generated_at' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get(),
            'kpis' => [
                'events' => $events,
                'programs' => $programs,
                'credits' => $credits,
                'artists' => $artists,
                'requests' => [
                    'pending_total' => $programRequestStatus['pending'] + $eventGuestRequestStatus['pending'] + $eventActiveRequestStatus['pending'] + $artistRequestStatus['pending'],
                    'program_requests_pending' => $programRequestStatus['pending'],
                    'event_requests_pending' => $eventGuestRequestStatus['pending'],
                    'event_active_requests_pending' => $eventActiveRequestStatus['pending'],
                    'artist_requests_pending' => $artistRequestStatus['pending'],
                ],
            ],
            'upcoming_events' => $upcomingEvents,
            'programs_by_event' => $programsByEvent,
            'request_status' => [
                'program_requests' => $programRequestStatus,
                'event_requests' => $eventGuestRequestStatus,
                'event_active_requests' => $eventActiveRequestStatus,
                'artist_requests' => $artistRequestStatus,
            ],
            'content_health' => $contentHealth,
            'admin_only' => $adminOnly,
        ]);
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to load dashboard stats', $e->getMessage()));
    }
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
    if ($result['success']) {
        audit_admin_success('change_password', 'user', (int)$userId, $_SESSION['admin_username'] ?? null);
    } else {
        audit_admin_failure('change_password', 'invalid_password', 'user', (int)$userId, $_SESSION['admin_username'] ?? null);
    }
    jsonResponse($result['success'], null, $result['message']);
}

function currentDbAdminUser(PDO $db): ?array {
    $userId = $_SESSION['admin_user_id'] ?? null;
    if ($userId === null) {
        return null;
    }
    $stmt = $db->prepare("SELECT * FROM admin_users WHERE id = :id AND is_active = 1");
    $stmt->execute([':id' => $userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function requireAdminTwofaSchema(): void {
    global $adminTwofaColumnsExist;
    if (!$adminTwofaColumnsExist) {
        jsonResponse(false, null, 'Admin 2FA migration required. Run setup.php or php tools/migrate-add-admin-2fa-columns.php');
    }
}

function verifyTwofaPassword(PDO $db, array $user, string $password): bool {
    return $password !== '' && password_verify($password, $user['password_hash']);
}

function verifyTwofaCredential(PDO $db, array $user, string $credential): array {
    $credential = trim($credential);
    if (!admin_user_has_enabled_twofa($user)) {
        return ['success' => false, 'message' => 'Two-factor authentication is not enabled'];
    }

    if (preg_match('/^\d{6}$/', $credential)) {
        $check = totp_verify($user['twofa_secret'], $credential, null, 1, 30, 6, isset($user['twofa_last_used_step']) ? intval($user['twofa_last_used_step']) : null);
        if (empty($check['valid'])) {
            return ['success' => false, 'message' => 'Invalid authentication code'];
        }
        $stmt = $db->prepare("UPDATE admin_users SET twofa_last_used_step = :step, updated_at = :now WHERE id = :id");
        $stmt->execute([':step' => $check['step'], ':now' => date('Y-m-d H:i:s'), ':id' => $user['id']]);
        return ['success' => true, 'message' => 'Verified'];
    }

    $backup = twofa_consume_backup_code($user['twofa_backup_codes'] ?? '[]', $credential);
    if (empty($backup['valid'])) {
        return ['success' => false, 'message' => 'Invalid recovery code'];
    }
    $stmt = $db->prepare("UPDATE admin_users SET twofa_backup_codes = :codes, updated_at = :now WHERE id = :id");
    $stmt->execute([':codes' => $backup['hashes_json'], ':now' => date('Y-m-d H:i:s'), ':id' => $user['id']]);
    return ['success' => true, 'message' => 'Verified'];
}

function getTwofaStatus() {
    global $db;
    requireAdminTwofaSchema();

    $user = currentDbAdminUser($db);
    if (!$user) {
        jsonResponse(true, ['managed' => false, 'enabled' => false, 'backup_codes_remaining' => 0]);
        return;
    }

    $backup = json_decode((string)($user['twofa_backup_codes'] ?? '[]'), true);
    jsonResponse(true, [
        'managed' => true,
        'enabled' => admin_user_has_enabled_twofa($user),
        'confirmed_at' => $user['twofa_confirmed_at'] ?? null,
        'backup_codes_remaining' => is_array($backup) ? count($backup) : 0,
    ]);
}

function beginTwofaSetup() {
    global $db;
    requireAdminTwofaSchema();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'POST method required');

    $user = currentDbAdminUser($db);
    if (!$user) jsonResponse(false, null, '2FA requires database-managed user');
    if (admin_user_has_enabled_twofa($user)) jsonResponse(false, null, '2FA is already enabled');

    $secret = totp_generate_secret();
    safe_session_start();
    $_SESSION['twofa_setup_secret'] = $secret;
    $_SESSION['twofa_setup_expires'] = time() + 600;

    $issuer = function_exists('get_site_title') ? get_site_title() : (defined('APP_NAME') ? APP_NAME : 'Admin');
    $account = $user['username'];
    $otpauth = totp_otpauth_uri($issuer, $account, $secret);

    jsonResponse(true, [
        'secret' => $secret,
        'otpauth_uri' => $otpauth,
        'qr_text' => $otpauth,
    ]);
}

function confirmTwofaSetup() {
    global $db;
    requireAdminTwofaSchema();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'POST method required');

    $user = currentDbAdminUser($db);
    if (!$user) jsonResponse(false, null, '2FA requires database-managed user');

    safe_session_start();
    $secret = $_SESSION['twofa_setup_secret'] ?? '';
    $expires = intval($_SESSION['twofa_setup_expires'] ?? 0);
    if ($secret === '' || $expires < time()) {
        jsonResponse(false, null, '2FA setup expired. Start again.');
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $code = trim($input['code'] ?? '');
    $check = totp_verify($secret, $code);
    if (empty($check['valid'])) {
        jsonResponse(false, null, 'Invalid authentication code');
    }

    $backupCodes = twofa_generate_backup_codes();
    $now = date('Y-m-d H:i:s');
    $stmt = $db->prepare("UPDATE admin_users
        SET twofa_enabled = 1, twofa_secret = :secret, twofa_backup_codes = :backup,
            twofa_confirmed_at = :now, twofa_last_used_step = :step, updated_at = :now2
        WHERE id = :id");
    $stmt->execute([
        ':secret' => $secret,
        ':backup' => twofa_hash_backup_codes($backupCodes),
        ':now' => $now,
        ':step' => $check['step'],
        ':now2' => $now,
        ':id' => $user['id'],
    ]);
    unset($_SESSION['twofa_setup_secret'], $_SESSION['twofa_setup_expires']);

    audit_admin_success('twofa_setup', 'user', (int)$user['id'], $user['username'] ?? null);
    jsonResponse(true, ['backup_codes' => $backupCodes], '2FA enabled successfully');
}

function disableTwofa() {
    global $db;
    requireAdminTwofaSchema();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'POST method required');

    $user = currentDbAdminUser($db);
    if (!$user) jsonResponse(false, null, '2FA requires database-managed user');

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    if (!verifyTwofaPassword($db, $user, $input['current_password'] ?? '')) {
        jsonResponse(false, null, 'Current password is incorrect');
    }
    $verified = verifyTwofaCredential($db, $user, $input['code'] ?? '');
    if (empty($verified['success'])) jsonResponse(false, null, $verified['message']);

    $stmt = $db->prepare("UPDATE admin_users
        SET twofa_enabled = 0, twofa_secret = NULL, twofa_backup_codes = NULL,
            twofa_confirmed_at = NULL, twofa_last_used_step = NULL, updated_at = :now
        WHERE id = :id");
    $stmt->execute([':now' => date('Y-m-d H:i:s'), ':id' => $user['id']]);
    audit_admin_success('twofa_disable', 'user', (int)$user['id'], $user['username'] ?? null);
    jsonResponse(true, null, '2FA disabled successfully');
}

function regenerateTwofaBackupCodes() {
    global $db;
    requireAdminTwofaSchema();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'POST method required');

    $user = currentDbAdminUser($db);
    if (!$user) jsonResponse(false, null, '2FA requires database-managed user');

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    if (!verifyTwofaPassword($db, $user, $input['current_password'] ?? '')) {
        jsonResponse(false, null, 'Current password is incorrect');
    }
    $verified = verifyTwofaCredential($db, $user, $input['code'] ?? '');
    if (empty($verified['success'])) jsonResponse(false, null, $verified['message']);

    $backupCodes = twofa_generate_backup_codes();
    $stmt = $db->prepare("UPDATE admin_users SET twofa_backup_codes = :backup, updated_at = :now WHERE id = :id");
    $stmt->execute([':backup' => twofa_hash_backup_codes($backupCodes), ':now' => date('Y-m-d H:i:s'), ':id' => $user['id']]);
    audit_admin_success('twofa_regenerate_backup_codes', 'user', (int)$user['id'], $user['username'] ?? null);
    jsonResponse(true, ['backup_codes' => $backupCodes], 'Backup codes regenerated');
}

function resetUserTwofa() {
    global $db;
    requireAdminTwofaSchema();
    require_api_admin_role();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, null, 'POST method required');

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = intval($input['id'] ?? ($_GET['id'] ?? 0));
    if (!$id) jsonResponse(false, null, 'User ID required');

    $stmt = $db->prepare("UPDATE admin_users
        SET twofa_enabled = 0, twofa_secret = NULL, twofa_backup_codes = NULL,
            twofa_confirmed_at = NULL, twofa_last_used_step = NULL, updated_at = :now
        WHERE id = :id");
    $stmt->execute([':now' => date('Y-m-d H:i:s'), ':id' => $id]);
    if ($stmt->rowCount() === 0) jsonResponse(false, null, 'User not found');
    audit_log([
        'action'        => 'twofa_reset_user',
        'outcome'       => 'success',
        'actor_user_id' => isset($_SESSION['admin_user_id']) ? (int)$_SESSION['admin_user_id'] : null,
        'actor_username'=> $_SESSION['admin_username'] ?? null,
        'actor_role'    => $_SESSION['admin_role'] ?? null,
        'entity_type'   => 'user',
        'entity_id'     => $id,
        'entity_label'  => null,
    ]);
    jsonResponse(true, null, '2FA reset successfully');
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
        if (isOrganizerRequest() && !can_manage_event($eventId)) {
            apiForbidden('You do not have permission to view this event');
        }
        $where[] = "event_id = :event_id";
        $params[':event_id'] = $eventId;
    }

    if (isOrganizerRequest()) {
        $where[] = organizerEventWhere('event_id');
        $params[':organizer_user_id'] = organizerUserId() ?? 0;
    }

    // FTS5 search: when ?search= present and FTS available, use MATCH
    $ftsIds = null;
    if ($search && mb_strlen($search) >= 3 && fts5_available($db)) {
        $ftsRows = fts5_search_programs($db, $search, $eventId ?: null, 2000);
        $ftsIds  = array_column($ftsRows, 'id');
        if (empty($ftsIds)) $ftsIds = [-1]; // no results
        $in = implode(',', array_map('intval', $ftsIds));
        $where[] = "id IN ($in)";
    } elseif ($search) {
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
            $type = $key === ':organizer_user_id' ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
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
        if (isOrganizerRequest() && (empty($event['event_id']) || !can_manage_event((int)$event['event_id']))) {
            apiForbidden('You do not have permission to view this program');
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
        if (isOrganizerRequest()) {
            if (!$eventId) {
                apiForbidden('Organizer programs must be assigned to an event');
            }
            requireCanManageEventId($eventId);
        }

        $programType = isset($input['program_type']) && $input['program_type'] !== '' ? trim($input['program_type']) : null;
        $streamUrlRaw = isset($input['stream_url']) && $input['stream_url'] !== '' ? trim($input['stream_url']) : null;
        // Only allow http/https schemes to prevent javascript: URI XSS
        $streamUrl = ($streamUrlRaw !== null && preg_match('/^https?:\/\//i', $streamUrlRaw)) ? $streamUrlRaw : null;
        if (isOrganizerRequest()) {
            $missingArtists = missingProgramArtistReferences($db, $input['categories'] ?? '');
            if ($missingArtists) {
                jsonResponse(false, null, 'Organizer can only reference existing artists: ' . implode(', ', $missingArtists));
                return;
            }
        }

        // Normalise location to canonical venue name (auto-register new venues for admin/agent)
        $canonLocation = venue_resolve_canonical($db, $input['location'] ?? '', !isOrganizerRequest());

        $stmt = $db->prepare("
            INSERT INTO programs (uid, title, start, end, location, organizer, description, categories, program_type, stream_url, event_id, created_at, updated_at)
            VALUES (:uid, :title, :start, :end, :location, :organizer, :description, :categories, :program_type, :stream_url, :event_id, :created_at, :updated_at)
        ");

        $stmt->execute([
            ':uid' => $uid,
            ':title' => $input['title'],
            ':start' => $input['start'],
            ':end' => $input['end'],
            ':location' => $canonLocation,
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

        syncProgramArtists($db, (int)$id, $input['categories'] ?? '', !isOrganizerRequest());

        invalidate_data_version_cache();
        invalidate_feed_cache();
        invalidate_query_cache();
        invalidate_artist_query_cache();
        invalidate_venue_query_cache();
        audit_admin_success('program_create', 'program', (int)$id, $input['title']);
        jsonResponse(true, ['id' => $id, 'uid' => $uid], 'Event created successfully');
    } catch (PDOException $e) {
        audit_admin_failure('program_create', 'database_error', 'program', null, $input['title'] ?? null);
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
        if (isOrganizerRequest()) {
            requireCanManageProgramId($db, $id);
        }
        $updateEventId = array_key_exists('event_id', $input) ? (isset($input['event_id']) ? intval($input['event_id']) : null) : null;
        if (isOrganizerRequest()) {
            if (!$updateEventId) {
                apiForbidden('Organizer programs must stay assigned to an event');
            }
            requireCanManageEventId($updateEventId);
        }

        $programType = array_key_exists('program_type', $input)
            ? (($input['program_type'] !== '' && $input['program_type'] !== null) ? trim($input['program_type']) : null)
            : null;
        $streamUrlRaw = array_key_exists('stream_url', $input)
            ? (($input['stream_url'] !== '' && $input['stream_url'] !== null) ? trim($input['stream_url']) : null)
            : null;
        // Only allow http/https schemes to prevent javascript: URI XSS
        $streamUrl = ($streamUrlRaw !== null && preg_match('/^https?:\/\//i', $streamUrlRaw)) ? $streamUrlRaw : null;
        if (isOrganizerRequest()) {
            $missingArtists = missingProgramArtistReferences($db, $input['categories'] ?? '');
            if ($missingArtists) {
                jsonResponse(false, null, 'Organizer can only reference existing artists: ' . implode(', ', $missingArtists));
                return;
            }
        }

        // Normalise location to canonical venue name (auto-register new venues for admin/agent)
        $canonLocation = venue_resolve_canonical($db, $input['location'] ?? '', !isOrganizerRequest());

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
            ':location' => $canonLocation,
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

        syncProgramArtists($db, $id, $input['categories'] ?? '', !isOrganizerRequest());

        invalidate_data_version_cache();
        invalidate_feed_cache();
        invalidate_query_cache();
        invalidate_artist_query_cache();
        invalidate_venue_query_cache();
        audit_admin_success('program_update', 'program', $id, $input['title']);
        jsonResponse(true, ['id' => $id], 'Event updated successfully');
    } catch (PDOException $e) {
        audit_admin_failure('program_update', 'database_error', 'program', $id, $input['title'] ?? null);
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
        if (isOrganizerRequest()) {
            requireCanManageProgramId($db, $id);
        }
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
        invalidate_venue_query_cache();
        audit_admin_success('program_delete', 'program', $id, null);
        jsonResponse(true, null, 'Event deleted successfully');
    } catch (PDOException $e) {
        audit_admin_failure('program_delete', 'database_error', 'program', $id, null);
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
        requireCanManageProgramIds($db, $ids);
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
        invalidate_venue_query_cache();
        audit_admin_success('program_bulk_delete', 'program', null, null, ['count' => $deletedCount, 'ids' => array_values($ids)]);
        jsonResponse(true, [
            'deleted_count' => $deletedCount,
            'failed_count' => $failedCount,
            'requested_count' => count($ids)
        ], "Deleted $deletedCount events successfully");

    } catch (PDOException $e) {
        $db->rollBack();
        audit_admin_failure('program_bulk_delete', 'database_error', 'program', null, null);
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
        requireCanManageProgramIds($db, $ids);
        $db->beginTransaction();

        // Build dynamic UPDATE
        $setClauses = [];
        $params = [];

        if ($location !== null) {
            $setClauses[] = "location = :location";
            $params[':location'] = venue_resolve_canonical($db, $location, !isOrganizerRequest());
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
        invalidate_venue_query_cache();
        audit_admin_success('program_bulk_update', 'program', null, null, ['count' => $updatedCount, 'ids' => array_values($ids)]);
        jsonResponse(true, [
            'updated_count' => $updatedCount,
            'failed_count' => $failedCount,
            'requested_count' => count($ids)
        ], "Updated $updatedCount events successfully");

    } catch (PDOException $e) {
        $db->rollBack();
        audit_admin_failure('program_bulk_update', 'database_error', 'program', null, null);
        jsonResponse(false, null, safe_error_message('Failed to update events', $e->getMessage()));
    }
}

/**
 * Get all venues
 */
function getVenues() {
    global $db;

    try {
        $sql = "
            SELECT DISTINCT location
            FROM programs
            WHERE location IS NOT NULL AND location != ''
            ORDER BY location ASC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute();

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
        $sql = "
            SELECT DISTINCT program_type
            FROM programs
            WHERE program_type IS NOT NULL AND program_type != ''
            " . (isOrganizerRequest() ? "AND " . organizerEventWhere('event_id') : "") . "
            ORDER BY program_type ASC
        ";
        $stmt = $db->prepare($sql);
        if (isOrganizerRequest()) {
            bindOrganizerUser($stmt);
        }
        $stmt->execute();

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

        // Alias renamed columns so admin JS (which uses .type, .title, .requester_note) still works
        $sql = "SELECT *, request_type AS type, summary AS title, note AS requester_note FROM program_requests $where ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fields ที่ต้อง escape เพื่อป้องกัน XSS
        $fieldsToEscape = ['title', 'summary', 'location', 'organizer', 'description', 'categories',
                          'requester_name', 'requester_email', 'requester_note', 'note', 'admin_note', 'reviewed_by'];

        // สำหรับ request ประเภท modify ให้ดึงข้อมูล event เดิมมาด้วย
        foreach ($requests as &$req) {
            if ($req['request_type'] === 'modify' && !empty($req['program_id'])) {
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

        // Alias renamed columns for consistent field access
        $stmt = $db->prepare("SELECT *, request_type AS type, summary AS title, note AS requester_note FROM program_requests WHERE id = :id AND status = 'pending'");
        $stmt->execute([':id' => $id]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$req) { $db->rollBack(); jsonResponse(false, null, 'Not found or processed'); }

        $now = date('Y-m-d H:i:s');

        if ($req['request_type'] === 'add') {
            $uid = uniqid('req-') . '@local';
            $reqEventId = $req['event_id'] ?? null;
            $stmt = $db->prepare("INSERT INTO programs (uid, title, start, end, location, organizer, description, categories, event_id, created_at, updated_at) VALUES (:uid, :title, :start, :end, :location, :organizer, :description, :categories, :event_id, :now, :now2)");
            $stmt->execute([':uid' => $uid, ':title' => $req['summary'], ':start' => $req['start'], ':end' => $req['end'], ':location' => $req['location'], ':organizer' => $req['organizer'], ':description' => $req['description'], ':categories' => $req['categories'], ':event_id' => $reqEventId, ':now' => $now, ':now2' => $now]);
            $programId = $db->lastInsertId();
        } else {
            $programId = $req['program_id'];
            $stmt = $db->prepare("UPDATE programs SET title = :title, start = :start, end = :end, location = :location, organizer = :organizer, description = :description, categories = :categories, updated_at = :now WHERE id = :id");
            $stmt->execute([':id' => $programId, ':title' => $req['summary'], ':start' => $req['start'], ':end' => $req['end'], ':location' => $req['location'], ':organizer' => $req['organizer'], ':description' => $req['description'], ':categories' => $req['categories'], ':now' => $now]);
        }

        $stmt = $db->prepare("UPDATE program_requests SET status = 'approved', admin_note = :note, reviewed_at = :now, reviewed_by = :by, updated_at = :now WHERE id = :id");
        $stmt->execute([':id' => $id, ':note' => mb_substr(trim($input['admin_note'] ?? ''), 0, 500), ':now' => $now, ':by' => $_SESSION['admin_username'] ?? 'admin']);

        $db->commit();
        audit_admin_success('program_request_approve', 'program_request', $id, null, ['program_id' => $programId]);
        jsonResponse(true, ['program_id' => $programId], 'Approved');
    } catch (PDOException $e) {
        $db->rollBack();
        audit_admin_failure('program_request_approve', 'database_error', 'program_request', $id, null);
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
        $stmt = $db->prepare("UPDATE program_requests SET status = 'rejected', admin_note = :note, reviewed_at = :now, reviewed_by = :by, updated_at = :now WHERE id = :id AND status = 'pending'");
        $stmt->execute([':id' => $id, ':note' => mb_substr(trim($input['admin_note'] ?? ''), 0, 500), ':now' => date('Y-m-d H:i:s'), ':by' => $_SESSION['admin_username'] ?? 'admin']);
        if ($stmt->rowCount() === 0) jsonResponse(false, null, 'Not found or processed');
        audit_admin_success('program_request_reject', 'program_request', $id, null);
        jsonResponse(true, null, 'Rejected');
    } catch (PDOException $e) {
        audit_admin_failure('program_request_reject', 'database_error', 'program_request', $id, null);
        jsonResponse(false, null, 'Failed');
    }
}

/**
 * Get pending count (program_requests + event_requests)
 */
function getPendingCount() {
    global $db;
    try {
        $progCount = intval($db->query("SELECT COUNT(*) FROM program_requests WHERE status = 'pending'")->fetchColumn());
        $evReqCount = 0;
        $evActiveReqCount = 0;
        $artistReqCount = 0;
        $evReqTable = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='event_requests'")->fetch();
        if ($evReqTable) {
            $evReqCount = intval($db->query("SELECT COUNT(*) FROM event_requests WHERE status = 'pending' AND request_type != 'activate'")->fetchColumn());
            $evActiveReqCount = intval($db->query("SELECT COUNT(*) FROM event_requests WHERE status = 'pending' AND request_type = 'activate'")->fetchColumn());
        }
        $artistReqTable = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='artist_requests'")->fetch();
        if ($artistReqTable) {
            $artistReqCount = intval($db->query("SELECT COUNT(*) FROM artist_requests WHERE status = 'pending'")->fetchColumn());
        }
        jsonResponse(true, [
            'count' => $progCount + $evReqCount + $evActiveReqCount + $artistReqCount,
            'program_requests' => $progCount,
            'event_requests' => $evReqCount,
            'event_active_requests' => $evActiveReqCount,
            'artist_requests' => $artistReqCount,
        ]);
    } catch (PDOException $e) {
        jsonResponse(false, null, 'Failed');
    }
}

function requireArtistRequestsTable(PDO $db): void {
    if (!adminTableExists($db, 'artist_requests')) {
        jsonResponse(false, null, 'artist_requests table not found. Run: php tools/migrate-add-artist-requests-table.php');
    }
}

function normalizeArtistRequestInput(PDO $db, array $input): array {
    $name = trim($input['name'] ?? '');
    if ($name === '') {
        jsonResponse(false, null, 'Name is required');
    }
    if (mb_strlen($name) > 200) {
        jsonResponse(false, null, 'Name is too long (max 200 characters)');
    }

    $isGroup = empty($input['is_group']) ? 0 : 1;
    $groupId = isset($input['group_id']) && $input['group_id'] !== '' ? intval($input['group_id']) : null;
    if ($isGroup) {
        $groupId = null;
    }

    if ($groupId !== null) {
        $groupStmt = $db->prepare("SELECT id FROM artists WHERE id = :id AND is_group = 1");
        $groupStmt->execute([':id' => $groupId]);
        if (!$groupStmt->fetchColumn()) {
            jsonResponse(false, null, 'Selected group was not found');
        }
    }

    return [
        'name' => $name,
        'is_group' => $isGroup,
        'group_id' => $groupId,
        'social_facebook' => sanitize_social_url($input['social_facebook'] ?? ''),
        'social_instagram' => sanitize_social_url($input['social_instagram'] ?? ''),
        'social_twitter' => sanitize_social_url($input['social_twitter'] ?? ''),
        'social_tiktok' => sanitize_social_url($input['social_tiktok'] ?? ''),
    ];
}

function artistNameExists(PDO $db, string $name): bool {
    $stmt = $db->prepare("SELECT id FROM artists WHERE LOWER(name) = LOWER(:name) LIMIT 1");
    $stmt->execute([':name' => $name]);
    return (bool)$stmt->fetchColumn();
}

function listArtistRequests() {
    global $db;
    requireApiAdminOrAgentRole();
    requireArtistRequestsTable($db);

    $status = $_GET['status'] ?? 'all';
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;

    $where = "1=1";
    $params = [];
    if ($status !== 'all' && in_array($status, ['pending', 'approved', 'rejected'], true)) {
        $where .= " AND ar.status = :status";
        $params[':status'] = $status;
    }

    try {
        $countStmt = $db->prepare("SELECT COUNT(*) FROM artist_requests ar WHERE $where");
        $countStmt->execute($params);
        $total = intval($countStmt->fetchColumn());

        $sql = "SELECT ar.*, g.name AS group_name
                FROM artist_requests ar
                LEFT JOIN artists g ON g.id = ar.group_id
                WHERE $where
                ORDER BY ar.created_at DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $rows = array_map(function($row) {
            return escapeOutputData($row, [
                'name', 'group_name', 'social_facebook', 'social_instagram', 'social_twitter',
                'social_tiktok', 'requester_name', 'requester_email', 'admin_note', 'reviewed_by',
            ]);
        }, $rows);

        jsonResponse(true, [
            'requests' => $rows,
            'pagination' => [
                'page' => $page,
                'total' => $total,
                'totalPages' => max(1, (int)ceil($total / $limit)),
            ],
        ]);
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to fetch artist requests', $e->getMessage()));
    }
}

function createArtistRequest() {
    global $db;
    requireApiOrganizerRole();
    requireArtistRequestsTable($db);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST method required');
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $data = normalizeArtistRequestInput($db, $input);
    if (artistNameExists($db, $data['name'])) {
        jsonResponse(false, null, 'Artist name already exists');
    }

    try {
        $pending = $db->prepare("SELECT id FROM artist_requests WHERE LOWER(name) = LOWER(:name) AND status = 'pending' LIMIT 1");
        $pending->execute([':name' => $data['name']]);
        if ($pending->fetchColumn()) {
            jsonResponse(false, null, 'Artist request is already pending');
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare("
            INSERT INTO artist_requests (
                name, is_group, group_id, social_facebook, social_instagram, social_twitter, social_tiktok,
                requester_user_id, requester_name, requester_email, created_at, updated_at
            ) VALUES (
                :name, :is_group, :group_id, :social_facebook, :social_instagram, :social_twitter, :social_tiktok,
                :requester_user_id, :requester_name, :requester_email, :created_at, :updated_at
            )
        ");
        $stmt->execute([
            ':name' => $data['name'],
            ':is_group' => $data['is_group'],
            ':group_id' => $data['group_id'],
            ':social_facebook' => $data['social_facebook'],
            ':social_instagram' => $data['social_instagram'],
            ':social_twitter' => $data['social_twitter'],
            ':social_tiktok' => $data['social_tiktok'],
            ':requester_user_id' => organizerUserId(),
            ':requester_name' => $_SESSION['admin_display_name'] ?? $_SESSION['admin_username'] ?? 'organizer',
            ':requester_email' => null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        $newId = (int)$db->lastInsertId();
        audit_admin_success('artist_request_create', 'artist_request', $newId, $data['name']);
        jsonResponse(true, ['id' => $newId], 'Artist request submitted');
    } catch (PDOException $e) {
        audit_admin_failure('artist_request_create', 'database_error', 'artist_request', null, $data['name'] ?? null);
        jsonResponse(false, null, safe_error_message('Failed to submit artist request', $e->getMessage()));
    }
}

function approveArtistRequest() {
    global $db;
    requireApiAdminOrAgentRole();
    requireArtistRequestsTable($db);

    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        jsonResponse(false, null, 'PUT required');
    }

    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        jsonResponse(false, null, 'ID required');
    }
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $adminNote = mb_substr(trim($input['admin_note'] ?? ''), 0, 1000) ?: null;

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("SELECT * FROM artist_requests WHERE id = :id AND status = 'pending'");
        $stmt->execute([':id' => $id]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$req) {
            $db->rollBack();
            jsonResponse(false, null, 'Request not found or already reviewed');
        }
        if (artistNameExists($db, $req['name'])) {
            $db->rollBack();
            jsonResponse(false, null, 'Artist name already exists');
        }

        $now = date('Y-m-d H:i:s');
        $insert = $db->prepare("
            INSERT INTO artists (name, is_group, group_id,
                social_facebook, social_instagram, social_twitter, social_tiktok,
                created_at, updated_at)
            VALUES (:name, :is_group, :group_id,
                :social_facebook, :social_instagram, :social_twitter, :social_tiktok,
                :created_at, :updated_at)
        ");
        $insert->execute([
            ':name' => $req['name'],
            ':is_group' => (int)$req['is_group'],
            ':group_id' => !empty($req['is_group']) ? null : ($req['group_id'] ?: null),
            ':social_facebook' => $req['social_facebook'],
            ':social_instagram' => $req['social_instagram'],
            ':social_twitter' => $req['social_twitter'],
            ':social_tiktok' => $req['social_tiktok'],
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $artistId = (int)$db->lastInsertId();

        $update = $db->prepare("UPDATE artist_requests SET status = 'approved', admin_note = :note, reviewed_at = :now, reviewed_by = :by, updated_at = :now2 WHERE id = :id");
        $update->execute([
            ':note' => $adminNote,
            ':now' => $now,
            ':by' => $_SESSION['admin_username'] ?? 'admin',
            ':now2' => $now,
            ':id' => $id,
        ]);

        $db->commit();
        invalidate_data_version_cache();
        invalidate_artist_query_cache();
        invalidate_sitemap_cache();
        audit_admin_success('artist_request_approve', 'artist_request', $id, $req['name'] ?? null, ['artist_id' => $artistId]);
        jsonResponse(true, ['artist_id' => $artistId], 'Approved');
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        audit_admin_failure('artist_request_approve', 'database_error', 'artist_request', $id, null);
        jsonResponse(false, null, safe_error_message('Failed to approve artist request', $e->getMessage()));
    }
}

function rejectArtistRequest() {
    global $db;
    requireApiAdminOrAgentRole();
    requireArtistRequestsTable($db);

    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        jsonResponse(false, null, 'PUT required');
    }

    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        jsonResponse(false, null, 'ID required');
    }
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $adminNote = mb_substr(trim($input['admin_note'] ?? ''), 0, 1000) ?: null;

    try {
        $stmt = $db->prepare("UPDATE artist_requests SET status = 'rejected', admin_note = :note, reviewed_at = :now, reviewed_by = :by, updated_at = :now2 WHERE id = :id AND status = 'pending'");
        $stmt->execute([
            ':id' => $id,
            ':note' => $adminNote,
            ':now' => date('Y-m-d H:i:s'),
            ':by' => $_SESSION['admin_username'] ?? 'admin',
            ':now2' => date('Y-m-d H:i:s'),
        ]);
        if ($stmt->rowCount() === 0) {
            jsonResponse(false, null, 'Request not found or already reviewed');
        }
        audit_admin_success('artist_request_reject', 'artist_request', $id, null);
        jsonResponse(true, null, 'Rejected');
    } catch (PDOException $e) {
        audit_admin_failure('artist_request_reject', 'database_error', 'artist_request', $id, null);
        jsonResponse(false, null, safe_error_message('Failed to reject artist request', $e->getMessage()));
    }
}

/**
 * List event requests with pagination + status filter
 */
function listEventRequests() {
    global $db;
    $status   = $_GET['status'] ?? 'all';
    $requestGroup = $_GET['request_group'] ?? 'all';
    $page     = max(1, intval($_GET['page'] ?? 1));
    $pageSize = 20;
    $offset   = ($page - 1) * $pageSize;

    $where  = "1=1";
    $params = [];
    if ($requestGroup === 'guest') {
        $where .= " AND er.request_type != 'activate'";
    } elseif ($requestGroup === 'active') {
        $where .= " AND er.request_type = 'activate'";
    }
    if ($status !== 'all') {
        $where  .= " AND er.status = :status";
        $params[':status'] = $status;
    }

    try {
        // Verify table exists
        $chk = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='event_requests'")->fetch();
        if (!$chk) {
            jsonResponse(true, ['requests' => [], 'pagination' => ['total' => 0, 'page' => 1, 'pageSize' => $pageSize, 'totalPages' => 0]]);
        }

        $countSql = "SELECT COUNT(*) FROM event_requests er WHERE $where";
        $countStmt = $db->prepare($countSql);
        $countStmt->execute($params);
        $total = intval($countStmt->fetchColumn());

        $sql = "SELECT er.*, e.name AS existing_event_name
                FROM event_requests er
                LEFT JOIN events e ON e.id = er.event_id
                WHERE $where
                ORDER BY er.created_at DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit',  $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,   PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        jsonResponse(true, [
            'requests'   => $rows,
            'pagination' => [
                'total'      => $total,
                'page'       => $page,
                'pageSize'   => $pageSize,
                'totalPages' => max(1, ceil($total / $pageSize)),
            ],
        ]);
    } catch (PDOException $e) {
        jsonResponse(false, null, 'Failed to fetch event requests');
    }
}

/**
 * Approve an event request
 * - type=add: INSERT new event with auto slug
 * - type=modify: UPDATE non-empty fields on the existing event
 * - type=activate: set the existing event active
 */
function approveEventRequest() {
    global $db;
    requireApiAdminOrAgentRole();

    $id    = intval($_GET['id'] ?? 0);
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $adminNote = mb_substr(trim($input['admin_note'] ?? ''), 0, 1000) ?: null;

    if (!$id) jsonResponse(false, null, 'Invalid ID');

    try {
        $stmt = $db->prepare("SELECT * FROM event_requests WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$req) jsonResponse(false, null, 'Request not found');
        if ($req['status'] !== 'pending') jsonResponse(false, null, 'Request already reviewed');

        $now = date('Y-m-d H:i:s');
        $reviewer = $_SESSION['admin_username'] ?? 'admin';

        if ($req['request_type'] === 'add') {
            // Generate unique slug from name
            $baseName  = !empty($req['name']) ? $req['name'] : 'event';
            $slug      = preg_replace('/[^a-z0-9]+/', '-', strtolower($baseName));
            $slug      = trim($slug, '-') ?: 'event';
            $slug     .= '-' . substr(uniqid(), -6);

            $ins = $db->prepare("INSERT INTO events (slug, name, description, start_date, end_date, is_active, venue_mode, created_at, updated_at)
                                  VALUES (:slug, :name, :desc, :start, :end, :is_active, 'multi', :now, :now2)");
            $ins->execute([
                ':slug'      => $slug,
                ':name'      => $req['name'] ?? $baseName,
                ':desc'      => $req['description'] ?? null,
                ':start'     => $req['start_date'] ?? null,
                ':end'       => $req['end_date'] ?? null,
                ':is_active' => 0,
                ':now'       => $now,
                ':now2'      => $now,
            ]);
            invalidate_query_cache(null);

        } elseif ($req['request_type'] === 'modify' && !empty($req['event_id'])) {
            $sets   = [];
            $params = [':id' => $req['event_id'], ':now' => $now];
            if (!empty($req['name']))        { $sets[] = "name = :name";        $params[':name']  = $req['name']; }
            if (!empty($req['description'])) { $sets[] = "description = :desc"; $params[':desc']  = $req['description']; }
            if (!empty($req['start_date']))  { $sets[] = "start_date = :start"; $params[':start'] = $req['start_date']; }
            if (!empty($req['end_date']))    { $sets[] = "end_date = :end";     $params[':end']   = $req['end_date']; }

            if (!empty($sets)) {
                $sets[] = "updated_at = :now";
                $upd = $db->prepare("UPDATE events SET " . implode(', ', $sets) . " WHERE id = :id");
                $upd->execute($params);
                invalidate_query_cache(intval($req['event_id']));
            }
        } elseif ($req['request_type'] === 'activate' && !empty($req['event_id'])) {
            $updEvent = $db->prepare("UPDATE events SET is_active = 1, updated_at = :now WHERE id = :id");
            $updEvent->execute([':now' => $now, ':id' => (int)$req['event_id']]);
            invalidate_query_cache((int)$req['event_id']);
            invalidate_sitemap_cache();
        }

        $upd = $db->prepare("UPDATE event_requests SET status='approved', admin_note=:note, reviewed_at=:now, reviewed_by=:by, updated_at=:now2 WHERE id=:id");
        $upd->execute([':note' => $adminNote, ':now' => $now, ':by' => $reviewer, ':now2' => $now, ':id' => $id]);

        audit_admin_success('event_request_approve', 'event_request', $id, $req['name'] ?? null, ['request_type' => $req['request_type'] ?? null]);
        jsonResponse(true, null, 'Approved');
    } catch (PDOException $e) {
        audit_admin_failure('event_request_approve', 'database_error', 'event_request', $id, null);
        jsonResponse(false, null, 'Failed to approve');
    }
}

/**
 * Reject an event request
 */
function rejectEventRequest() {
    global $db;
    requireApiAdminOrAgentRole();

    $id    = intval($_GET['id'] ?? 0);
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $adminNote = mb_substr(trim($input['admin_note'] ?? ''), 0, 1000) ?: null;

    if (!$id) jsonResponse(false, null, 'Invalid ID');

    try {
        $stmt = $db->prepare("SELECT id, status FROM event_requests WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$req) jsonResponse(false, null, 'Request not found');
        if ($req['status'] !== 'pending') jsonResponse(false, null, 'Request already reviewed');

        $now = date('Y-m-d H:i:s');
        $reviewer = $_SESSION['admin_username'] ?? 'admin';
        $upd = $db->prepare("UPDATE event_requests SET status='rejected', admin_note=:note, reviewed_at=:now, reviewed_by=:by, updated_at=:now2 WHERE id=:id");
        $upd->execute([':note' => $adminNote, ':now' => $now, ':by' => $reviewer, ':now2' => $now, ':id' => $id]);

        audit_admin_success('event_request_reject', 'event_request', $id, null);
        jsonResponse(true, null, 'Rejected');
    } catch (PDOException $e) {
        audit_admin_failure('event_request_reject', 'database_error', 'event_request', $id, null);
        jsonResponse(false, null, 'Failed to reject');
    }
}

/**
 * Get pending count for event_requests only
 */
function getEventRequestPendingCount() {
    global $db;
    try {
        $chk = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='event_requests'")->fetch();
        if (!$chk) { jsonResponse(true, ['count' => 0]); }
        $count = intval($db->query("SELECT COUNT(*) FROM event_requests WHERE status='pending'")->fetchColumn());
        jsonResponse(true, ['count' => $count]);
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
                    ':location' => venue_resolve_canonical($db, $event['location'] ?? '', true),
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
        invalidate_venue_query_cache();
        fts5_rebuild_all($db); // rebuild FTS index after bulk import
        audit_admin_success('ics_import_confirm', 'program', null, null, [
            'affected_count'     => $stats['inserted'] + $stats['updated'],
            'affected_breakdown' => ['inserted' => $stats['inserted'], 'updated' => $stats['updated'], 'skipped' => $stats['skipped']],
        ]);
        jsonResponse(true, [
            'saved_filename' => $savedFilename,
            'stats' => $stats,
            'errors' => $errors
        ], 'Import completed successfully');

    } catch (Exception $e) {
        $db->rollBack();
        audit_admin_failure('ics_import_confirm', 'database_error', 'program', null, null);
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
function syncProgramArtists(PDO $db, int $programId, string $categories, bool $allowCreate = true): void {
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

            // 3) auto-create new artist if still not found.
            // Organizer users may reference existing artists only.
            if (!$artistId && $allowCreate) {
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
 * Resolve a raw location string to its canonical venue name.
 * Mirror of the artist canonicalisation in syncProgramArtists():
 *   1) exact name match (case-insensitive) in venues  → canonical name
 *   2) variant lookup (case-insensitive) in venue_variants → owning venue name
 *   3) not found + $allowCreate → INSERT a new venue, return its name
 *   4) tables missing / not found → return the raw value unchanged (graceful)
 *
 * Used before binding programs.location on create/update/bulk/ICS import so that
 * known aliases auto-normalise and brand-new venues self-register.
 *
 * @return string canonical location string (may equal the raw input)
 */
function venue_resolve_canonical(PDO $db, string $rawLocation, bool $allowCreate = true): string {
    $raw = trim($rawLocation);
    if ($raw === '') return '';

    // venues table must exist (v16.0.0+)
    $hasVenues = (bool)$db->query(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='venues'"
    )->fetch();
    if (!$hasVenues) return $raw;

    // 1) exact name match (case-insensitive)
    $s = $db->prepare('SELECT name FROM venues WHERE LOWER(name) = LOWER(?)');
    $s->execute([$raw]);
    $name = $s->fetchColumn();
    if ($name) return $name;

    // 2) variant lookup
    $hasVariants = (bool)$db->query(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='venue_variants'"
    )->fetch();
    if ($hasVariants) {
        $sv = $db->prepare('
            SELECT v.name FROM venue_variants vv
            JOIN venues v ON v.id = vv.venue_id
            WHERE LOWER(vv.variant) = LOWER(?)
            LIMIT 1
        ');
        $sv->execute([$raw]);
        $vname = $sv->fetchColumn();
        if ($vname) return $vname;
    }

    // 3) auto-create new venue
    if ($allowCreate) {
        $now = date('Y-m-d H:i:s');
        $ins = $db->prepare('INSERT OR IGNORE INTO venues (name, created_at, updated_at) VALUES (?, ?, ?)');
        $ins->execute([$raw, $now, $now]);
    }

    // 4) return raw (canonical now == raw)
    return $raw;
}

// ============================================================================
// VENUES API FUNCTIONS (v16.0.0)
// ============================================================================

/** Guard: ensure venues table exists, else JSON error. */
function _venuesTableReady(PDO $db): bool {
    return (bool)$db->query(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='venues'"
    )->fetch();
}

/**
 * List venues with pagination, search, sort + program_count + variant_count
 */
function listVenues() {
    global $db;
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(false, null, 'GET method required');
        return;
    }
    if (!_venuesTableReady($db)) {
        jsonResponse(false, null, 'venues table not found. Run: php tools/migrate-add-venues-table.php');
        return;
    }

    $page   = max(1, intval($_GET['page'] ?? 1));
    $limit  = max(1, min(100, intval($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;
    $search = substr($_GET['search'] ?? '', 0, 200);

    $allowedSort = ['id', 'name', 'created_at', 'program_count'];
    $sortColumn  = in_array($_GET['sort'] ?? '', $allowedSort) ? $_GET['sort'] : 'name';
    $sortOrder   = ($_GET['order'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

    $where = [];
    $params = [];
    if ($search !== '') {
        $escaped = str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $search);
        $where[] = "v.name LIKE :search ESCAPE '\\'";
        $params[':search'] = '%' . $escaped . '%';
    }
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    try {
        $countStmt = $db->prepare("SELECT COUNT(*) FROM venues v $whereClause");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $hasVariants = (bool)$db->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='venue_variants'"
        )->fetch();
        $variantCountExpr = $hasVariants
            ? "(SELECT COUNT(*) FROM venue_variants vv WHERE vv.venue_id = v.id)"
            : "0";

        // program_count: programs whose location = name OR matches one of this venue's variants
        $programCountExpr = $hasVariants
            ? "(SELECT COUNT(*) FROM programs p
                 WHERE p.location = v.name
                    OR p.location IN (SELECT vv2.variant FROM venue_variants vv2 WHERE vv2.venue_id = v.id))"
            : "(SELECT COUNT(*) FROM programs p WHERE p.location = v.name)";

        $orderExpr = $sortColumn === 'program_count' ? $programCountExpr : "v.$sortColumn";

        $hasIsOnline = in_array('is_online', $db->query("PRAGMA table_info(venues)")->fetchAll(PDO::FETCH_COLUMN, 1), true);
        $isOnlineExpr = $hasIsOnline ? 'v.is_online' : '0 AS is_online';
        $sql = "SELECT v.id, v.name, v.description, v.map_url, v.created_at,
                       $isOnlineExpr,
                       $variantCountExpr AS variant_count,
                       $programCountExpr AS program_count
                FROM venues v
                $whereClause
                ORDER BY $orderExpr $sortOrder
                LIMIT :limit OFFSET :offset";
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $vv) $stmt->bindValue($k, $vv);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $venues = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $venues = array_map(fn($v) => escapeOutputData($v, ['name', 'description', 'map_url']), $venues);

        jsonResponse(true, [
            'venues'     => $venues,
            'pagination' => [
                'page' => $page, 'limit' => $limit, 'total' => $total,
                'totalPages' => (int)ceil($total / $limit),
            ],
        ]);
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to fetch venues', $e->getMessage()));
    }
}

/** Lightweight venue autocomplete (id, name) matching name OR variant */
function autocompleteVenues() {
    global $db;
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(false, null, 'GET method required');
        return;
    }
    if (!_venuesTableReady($db)) { jsonResponse(true, []); return; }

    $q = substr(trim($_GET['q'] ?? ''), 0, 200);
    try {
        if ($q === '') {
            $stmt = $db->query("SELECT id, name FROM venues ORDER BY name ASC LIMIT 50");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $escaped = str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $q);
            $stmt = $db->prepare("
                SELECT DISTINCT v.id, v.name
                FROM venues v
                LEFT JOIN venue_variants vv ON vv.venue_id = v.id
                WHERE v.name LIKE :q ESCAPE '\\' OR vv.variant LIKE :q ESCAPE '\\'
                ORDER BY v.name ASC LIMIT 20
            ");
            $stmt->execute([':q' => '%' . $escaped . '%']);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $data = array_map(fn($r) => ['id' => (int)$r['id'], 'name' => $r['name']], $rows);
        jsonResponse(true, $data);
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Autocomplete failed', $e->getMessage()));
    }
}

/** Get single venue by ID (with variants) */
function getVenue() {
    global $db;
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') { jsonResponse(false, null, 'GET method required'); return; }
    if (!_venuesTableReady($db)) { jsonResponse(false, null, 'venues table not found'); return; }

    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) { jsonResponse(false, null, 'Valid venue ID required'); return; }

    try {
        $hasIsOnline = in_array('is_online', $db->query("PRAGMA table_info(venues)")->fetchAll(PDO::FETCH_COLUMN, 1), true);
        $cols = "id, name, description, map_url, created_at" . ($hasIsOnline ? ", is_online" : "");
        $stmt = $db->prepare("SELECT $cols FROM venues WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $venue = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$venue) { jsonResponse(false, null, 'Venue not found'); return; }
        if (!$hasIsOnline) $venue['is_online'] = 0;
        $venue = escapeOutputData($venue, ['name', 'description', 'map_url']);
        jsonResponse(true, $venue);
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to fetch venue', $e->getMessage()));
    }
}

/** Create new venue */
function createVenue() {
    global $db;
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonResponse(false, null, 'POST method required'); return; }
    if (!_venuesTableReady($db)) { jsonResponse(false, null, 'venues table not found. Run: php tools/migrate-add-venues-table.php'); return; }

    $input = json_decode(file_get_contents('php://input'), true);
    $name  = trim($input['name'] ?? '');
    if ($name === '') { jsonResponse(false, null, 'Name is required'); return; }
    if (mb_strlen($name, 'UTF-8') > 300) { jsonResponse(false, null, 'Name is too long (max 300 characters)'); return; }
    $description = trim($input['description'] ?? '');
    $mapUrlRaw   = trim($input['map_url'] ?? '');
    $mapUrl      = ($mapUrlRaw !== '' && preg_match('/^https?:\/\//i', $mapUrlRaw)) ? $mapUrlRaw : null;
    $isOnline    = !empty($input['is_online']) ? 1 : 0;

    try {
        $hasIsOnline = in_array('is_online', $db->query("PRAGMA table_info(venues)")->fetchAll(PDO::FETCH_COLUMN, 1), true);
        $now  = date('Y-m-d H:i:s');
        if ($hasIsOnline) {
            $stmt = $db->prepare("INSERT INTO venues (name, description, map_url, is_online, created_at, updated_at)
                                  VALUES (:name, :description, :map_url, :is_online, :now, :now2)");
            $stmt->execute([
                ':name' => $name,
                ':description' => $description !== '' ? $description : null,
                ':map_url' => $mapUrl,
                ':is_online' => $isOnline,
                ':now' => $now, ':now2' => $now,
            ]);
        } else {
            $stmt = $db->prepare("INSERT INTO venues (name, description, map_url, created_at, updated_at)
                                  VALUES (:name, :description, :map_url, :now, :now2)");
            $stmt->execute([
                ':name' => $name,
                ':description' => $description !== '' ? $description : null,
                ':map_url' => $mapUrl,
                ':now' => $now, ':now2' => $now,
            ]);
        }
        $id = (int)$db->lastInsertId();
        invalidate_venue_query_cache();
        invalidate_query_cache();
        audit_admin_success('venue_create', 'venue', $id, $name);
        jsonResponse(true, ['id' => $id], 'Venue created successfully');
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'UNIQUE') !== false) {
            jsonResponse(false, null, 'Venue name already exists');
        } else {
            audit_admin_failure('venue_create', 'database_error', 'venue', null, $name);
            jsonResponse(false, null, safe_error_message('Failed to create venue', $e->getMessage()));
        }
    }
}

/** Update venue (rename also rewrites programs.location for consistency) */
function updateVenue() {
    global $db;
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') { jsonResponse(false, null, 'PUT method required'); return; }
    if (!_venuesTableReady($db)) { jsonResponse(false, null, 'venues table not found'); return; }

    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) { jsonResponse(false, null, 'Valid venue ID required'); return; }

    $input = json_decode(file_get_contents('php://input'), true);
    $name  = trim($input['name'] ?? '');
    if ($name === '') { jsonResponse(false, null, 'Name is required'); return; }
    if (mb_strlen($name, 'UTF-8') > 300) { jsonResponse(false, null, 'Name is too long (max 300 characters)'); return; }
    $description = trim($input['description'] ?? '');
    $mapUrlRaw   = trim($input['map_url'] ?? '');
    $mapUrl      = ($mapUrlRaw !== '' && preg_match('/^https?:\/\//i', $mapUrlRaw)) ? $mapUrlRaw : null;
    $isOnline    = !empty($input['is_online']) ? 1 : 0;

    try {
        $hasIsOnline = in_array('is_online', $db->query("PRAGMA table_info(venues)")->fetchAll(PDO::FETCH_COLUMN, 1), true);
        // Old name (to rewrite programs.location on rename)
        $s = $db->prepare("SELECT name FROM venues WHERE id = :id");
        $s->execute([':id' => $id]);
        $oldName = $s->fetchColumn();
        if ($oldName === false) { jsonResponse(false, null, 'Venue not found'); return; }

        $db->beginTransaction();
        if ($hasIsOnline) {
            $stmt = $db->prepare("UPDATE venues SET name = :name, description = :description, map_url = :map_url,
                                  is_online = :is_online, updated_at = :now WHERE id = :id");
            $stmt->execute([
                ':name' => $name,
                ':description' => $description !== '' ? $description : null,
                ':map_url' => $mapUrl,
                ':is_online' => $isOnline,
                ':now' => date('Y-m-d H:i:s'),
                ':id' => $id,
            ]);
        } else {
            $stmt = $db->prepare("UPDATE venues SET name = :name, description = :description, map_url = :map_url,
                                  updated_at = :now WHERE id = :id");
            $stmt->execute([
                ':name' => $name,
                ':description' => $description !== '' ? $description : null,
                ':map_url' => $mapUrl,
                ':now' => date('Y-m-d H:i:s'),
                ':id' => $id,
            ]);
        }
        // Rename → rewrite programs.location from old → new
        $rewritten = 0;
        if ($oldName !== $name) {
            $up = $db->prepare("UPDATE programs SET location = :new WHERE location = :old");
            $up->execute([':new' => $name, ':old' => $oldName]);
            $rewritten = $up->rowCount();
        }
        $db->commit();

        invalidate_venue_query_cache();
        invalidate_query_cache();
        if ($rewritten > 0) { invalidate_data_version_cache(); invalidate_feed_cache(); }
        audit_admin_success('venue_update', 'venue', $id, $name);
        jsonResponse(true, ['rewritten_programs' => $rewritten], 'Venue updated successfully');
    } catch (PDOException $e) {
        if ($db->inTransaction()) $db->rollBack();
        if (strpos($e->getMessage(), 'UNIQUE') !== false) {
            jsonResponse(false, null, 'Venue name already exists');
        } else {
            audit_admin_failure('venue_update', 'database_error', 'venue', $id, $name);
            jsonResponse(false, null, safe_error_message('Failed to update venue', $e->getMessage()));
        }
    }
}

/** Delete venue (variants cascade; programs.location text is left untouched) */
function deleteVenue() {
    global $db;
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') { jsonResponse(false, null, 'DELETE method required'); return; }
    if (!_venuesTableReady($db)) { jsonResponse(false, null, 'venues table not found'); return; }

    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) { jsonResponse(false, null, 'Valid venue ID required'); return; }

    try {
        $stmt = $db->prepare("DELETE FROM venues WHERE id = :id");
        $stmt->execute([':id' => $id]);
        if ($stmt->rowCount() === 0) { jsonResponse(false, null, 'Venue not found'); return; }
        invalidate_venue_query_cache();
        invalidate_query_cache();
        audit_admin_success('venue_delete', 'venue', $id, null);
        jsonResponse(true, null, 'Venue deleted successfully');
    } catch (PDOException $e) {
        audit_admin_failure('venue_delete', 'database_error', 'venue', $id, null);
        jsonResponse(false, null, safe_error_message('Failed to delete venue', $e->getMessage()));
    }
}

/** List variants for a venue */
function listVenueVariants() {
    global $db;
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') { jsonResponse(false, null, 'GET method required'); return; }
    $venueId = intval($_GET['venue_id'] ?? 0);
    if (!$venueId) { jsonResponse(false, null, 'venue_id required'); return; }
    try {
        $stmt = $db->prepare("SELECT id, variant, created_at FROM venue_variants WHERE venue_id = ? ORDER BY variant ASC");
        $stmt->execute([$venueId]);
        $variants = array_map(fn($v) => escapeOutputData($v, ['variant']), $stmt->fetchAll(PDO::FETCH_ASSOC));
        jsonResponse(true, ['variants' => $variants]);
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to fetch variants', $e->getMessage()));
    }
}

/** Add a variant to a venue */
function createVenueVariant() {
    global $db;
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonResponse(false, null, 'POST method required'); return; }
    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $venueId = intval($body['venue_id'] ?? 0);
    $variant = trim($body['variant'] ?? '');
    if (!$venueId) { jsonResponse(false, null, 'venue_id required'); return; }
    if ($variant === '') { jsonResponse(false, null, 'variant cannot be empty'); return; }
    if (mb_strlen($variant, 'UTF-8') > 300) { jsonResponse(false, null, 'variant too long (max 300 characters)'); return; }

    try {
        $check = $db->prepare("SELECT id FROM venues WHERE id = ?");
        $check->execute([$venueId]);
        if (!$check->fetch()) { jsonResponse(false, null, 'Venue not found'); return; }
        if (!$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='venue_variants'")->fetch()) {
            jsonResponse(false, null, 'venue_variants table not found. Run: php tools/migrate-add-venues-table.php');
            return;
        }
        $stmt = $db->prepare("INSERT OR IGNORE INTO venue_variants (venue_id, variant) VALUES (?, ?)");
        $stmt->execute([$venueId, $variant]);
        $newId = $db->lastInsertId();
        if (!$newId) { jsonResponse(false, null, 'Variant already exists for this venue'); return; }
        invalidate_venue_query_cache();
        jsonResponse(true, ['id' => (int)$newId, 'variant' => $variant], 'Variant added');
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to create variant', $e->getMessage()));
    }
}

/** Delete a venue variant */
function deleteVenueVariant() {
    global $db;
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') { jsonResponse(false, null, 'DELETE method required'); return; }
    $id = intval($_GET['id'] ?? 0);
    if (!$id) { jsonResponse(false, null, 'id required'); return; }
    try {
        $stmt = $db->prepare("DELETE FROM venue_variants WHERE id = ?");
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) { jsonResponse(false, null, 'Variant not found'); return; }
        invalidate_venue_query_cache();
        jsonResponse(true, null, 'Variant deleted');
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to delete variant', $e->getMessage()));
    }
}

/**
 * Merge source venues into a target venue.
 * Body: { target_id: int, source_ids: [int,...] }
 * - rewrite programs.location (source name + each source variant) → target name
 * - move source variants → target; add each source name as a target variant
 * - delete source venues (cascade removes leftover variants)
 */
function mergeVenues() {
    global $db;
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonResponse(false, null, 'POST method required'); return; }
    if (!_venuesTableReady($db)) { jsonResponse(false, null, 'venues table not found'); return; }

    $input    = json_decode(file_get_contents('php://input'), true) ?? [];
    $targetId = intval($input['target_id'] ?? 0);
    $sourceIds = $input['source_ids'] ?? [];
    if ($targetId <= 0) { jsonResponse(false, null, 'target_id required'); return; }
    if (!is_array($sourceIds) || empty($sourceIds)) { jsonResponse(false, null, 'source_ids required'); return; }
    $sourceIds = array_values(array_filter(array_map('intval', $sourceIds), fn($i) => $i > 0 && $i !== $targetId));
    if (empty($sourceIds)) { jsonResponse(false, null, 'No valid source venue IDs (cannot merge a venue into itself)'); return; }
    if (count($sourceIds) > 100) { jsonResponse(false, null, 'Too many source venues (max 100)'); return; }

    try {
        $ts = $db->prepare("SELECT name FROM venues WHERE id = :id");
        $ts->execute([':id' => $targetId]);
        $targetName = $ts->fetchColumn();
        if ($targetName === false) { jsonResponse(false, null, 'Target venue not found'); return; }

        $db->beginTransaction();

        $getName     = $db->prepare("SELECT name FROM venues WHERE id = :id");
        $getVariants = $db->prepare("SELECT variant FROM venue_variants WHERE venue_id = :id");
        $rewriteLoc  = $db->prepare("UPDATE programs SET location = :new WHERE location = :old");
        $moveVariant = $db->prepare("UPDATE OR IGNORE venue_variants SET venue_id = :tid WHERE venue_id = :sid");
        $addVariant  = $db->prepare("INSERT OR IGNORE INTO venue_variants (venue_id, variant) VALUES (:tid, :variant)");
        $delVenue    = $db->prepare("DELETE FROM venues WHERE id = :id");

        $rewritten = 0;
        foreach ($sourceIds as $sid) {
            $getName->execute([':id' => $sid]);
            $sourceName = $getName->fetchColumn();
            if ($sourceName === false) continue;

            // rewrite programs.location: source name → target name
            $rewriteLoc->execute([':new' => $targetName, ':old' => $sourceName]);
            $rewritten += $rewriteLoc->rowCount();

            // rewrite programs.location for each variant of the source → target name
            $getVariants->execute([':id' => $sid]);
            $srcVariants = $getVariants->fetchAll(PDO::FETCH_COLUMN);
            foreach ($srcVariants as $sv) {
                $rewriteLoc->execute([':new' => $targetName, ':old' => $sv]);
                $rewritten += $rewriteLoc->rowCount();
            }

            // move source variants → target (ignore dup), then add source name as target variant
            $moveVariant->execute([':tid' => $targetId, ':sid' => $sid]);
            if ($sourceName !== $targetName) {
                $addVariant->execute([':tid' => $targetId, ':variant' => $sourceName]);
            }

            // delete source venue (cascade removes any leftover variants)
            $delVenue->execute([':id' => $sid]);
        }

        $db->commit();

        invalidate_venue_query_cache();
        invalidate_query_cache();
        invalidate_data_version_cache();
        invalidate_feed_cache();
        audit_admin_success('venue_merge', 'venue', $targetId, $targetName, [
            'source_ids' => $sourceIds, 'rewritten_programs' => $rewritten,
        ]);
        jsonResponse(true, [
            'target_id' => $targetId,
            'merged_count' => count($sourceIds),
            'rewritten_programs' => $rewritten,
        ], 'Venues merged successfully');
    } catch (PDOException $e) {
        if ($db->inTransaction()) $db->rollBack();
        audit_admin_failure('venue_merge', 'database_error', 'venue', $targetId, null);
        jsonResponse(false, null, safe_error_message('Failed to merge venues', $e->getMessage()));
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
            if (isOrganizerRequest() && !can_manage_event($eventId)) {
                apiForbidden('You do not have permission to view this event');
            }
            $where[] = isOrganizerRequest() ? "event_id = :event_id" : "(event_id IS NULL OR event_id = :event_id)";
            $params[':event_id'] = $eventId;
        }

        if (isOrganizerRequest()) {
            $where[] = organizerEventWhere('event_id');
            $params[':organizer_user_id'] = organizerUserId() ?? 0;
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
            $type = $key === ':organizer_user_id' ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
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
        if (isOrganizerRequest() && (empty($credit['event_id']) || !can_manage_event((int)$credit['event_id']))) {
            apiForbidden('You do not have permission to view this credit');
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
        if (isOrganizerRequest()) {
            if (!$creditEventId) {
                apiForbidden('Organizer credits must be assigned to an event');
            }
            requireCanManageEventId($creditEventId);
        }
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

        audit_admin_success('credit_create', 'credit', (int)$id, $title);
        jsonResponse(true, ['id' => $id], 'Credit created successfully');

    } catch (PDOException $e) {
        audit_admin_failure('credit_create', 'database_error', 'credit', null, $title ?? null);
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
        if (isOrganizerRequest()) {
            requireCanManageCreditId($db, $id);
            if (!$creditEventId) {
                apiForbidden('Organizer credits must stay assigned to an event');
            }
            requireCanManageEventId($creditEventId);
        }
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

        audit_admin_success('credit_update', 'credit', $id, $title);
        jsonResponse(true, null, 'Credit updated successfully');

    } catch (PDOException $e) {
        audit_admin_failure('credit_update', 'database_error', 'credit', $id, $title ?? null);
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
        if (isOrganizerRequest()) {
            requireCanManageCreditId($db, $id);
        }
        $stmt = $db->prepare("DELETE FROM credits WHERE id = :id");
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() === 0) {
            jsonResponse(false, null, 'Credit not found');
            return;
        }

        // Invalidate cache
        invalidate_credits_cache();

        audit_admin_success('credit_delete', 'credit', $id, null);
        jsonResponse(true, null, 'Credit deleted successfully');

    } catch (PDOException $e) {
        audit_admin_failure('credit_delete', 'database_error', 'credit', $id, null);
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
        requireCanManageCreditIds($db, $ids);
        $db->beginTransaction();

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("DELETE FROM credits WHERE id IN ($placeholders)");
        $stmt->execute($ids);

        $deletedCount = $stmt->rowCount();
        $failedCount = count($ids) - $deletedCount;

        $db->commit();

        // Invalidate cache
        invalidate_credits_cache();

        audit_admin_success('credit_bulk_delete', 'credit', null, null, ['count' => $deletedCount, 'ids' => array_values($ids)]);
        jsonResponse(true, [
            'deleted_count' => $deletedCount,
            'failed_count' => $failedCount,
            'requested_count' => count($ids)
        ], "Deleted $deletedCount credits successfully");

    } catch (PDOException $e) {
        $db->rollBack();
        audit_admin_failure('credit_bulk_delete', 'database_error', 'credit', null, null);
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

        if ($search && mb_strlen($search) >= 3 && fts5_available($db)) {
            $ftsRows = fts5_search_events($db, $search, 2000);
            $ids     = array_column($ftsRows, 'id') ?: [-1];
            $whereClauses[] = 'e.id IN (' . implode(',', array_map('intval', $ids)) . ')';
        } elseif ($search) {
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

        if (isOrganizerRequest()) {
            $whereClauses[] = organizerEventWhere('e.id');
            $params[':organizer_user_id'] = organizerUserId() ?? 0;
        }

        $whereSQL = !empty($whereClauses) ? ' WHERE ' . implode(' AND ', $whereClauses) : '';

        // COUNT query first for pagination total
        $countQuery = "SELECT COUNT(*) as total FROM events e" . $whereSQL;
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
            $type = $key === ':organizer_user_id' ? PDO::PARAM_INT : PDO::PARAM_STR;
            $dataStmt->bindValue($key, $val, $type);
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
        if (isOrganizerRequest()) {
            requireCanManageEventId($id);
        }

        $fieldsToEscape = ['name', 'slug', 'description'];
        $meta = escapeOutputData($meta, $fieldsToEscape);

        // Attach pictures sub-list
        $stmtPics = $db->prepare(
            "SELECT id, filename, caption, display_order FROM event_pictures
             WHERE event_id = :id ORDER BY display_order ASC, id ASC"
        );
        $stmtPics->execute([':id' => $id]);
        $meta['pictures'] = $stmtPics->fetchAll(PDO::FETCH_ASSOC);

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

    $validThemes = ['sakura', 'ocean', 'forest', 'midnight', 'sunset', 'dark', 'gray', 'crimson', 'teal', 'rose', 'amber', 'indigo'];
    $validTemplates = ['grid1', 'grid2', 'grid3', 'masonry'];
    $slug = preg_replace('/[^a-zA-Z0-9\-_]/', '', trim($input['slug']));
    $name = mb_substr(trim($input['name']), 0, 200);
    $description = mb_substr(trim($input['description'] ?? ''), 0, 1000);
    $startDate = $input['start_date'] ?? null;
    $endDate = $input['end_date'] ?? null;
    $venueMode = in_array($input['venue_mode'] ?? '', ['multi', 'single', 'calendar']) ? $input['venue_mode'] : 'multi';
    $isActive = isset($input['is_active']) ? intval($input['is_active']) : 1;
    if (isOrganizerRequest()) {
        $isActive = 0;
    }
    $theme = (isset($input['theme']) && in_array($input['theme'], $validThemes)) ? $input['theme'] : null;
    $galleryTemplate = in_array($input['gallery_template'] ?? '', $validTemplates) ? $input['gallery_template'] : 'grid3';
    $emailRaw = trim($input['email'] ?? '');
    $email = ($emailRaw !== '' && filter_var($emailRaw, FILTER_VALIDATE_EMAIL)) ? $emailRaw : null;
    $timezoneRaw = trim($input['timezone'] ?? '');
    $timezone = defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 'Asia/Bangkok';
    if ($timezoneRaw !== '') {
        try { new DateTimeZone($timezoneRaw); $timezone = $timezoneRaw; } catch (Exception $e) {}
    }
    $ticketUrl = sanitize_social_url($input['ticket_url'] ?? '');

    try {
        if (isOrganizerRequest() && !eventOrganizerSchemaReady($db)) {
            jsonResponse(false, null, 'Organizer schema not ready. Run: php tools/migrate-add-organizer-role.php');
            return;
        }
        $creatorUserId = organizerUserId();
        $hasCreatedBy = adminColumnExists($db, 'events', 'created_by_user_id');
        // Check unique slug
        $check = $db->prepare("SELECT id FROM events WHERE slug = :slug");
        $check->execute([':slug' => $slug]);
        if ($check->fetch()) {
            jsonResponse(false, null, 'Slug already exists');
            return;
        }

        $now = date('Y-m-d H:i:s');
        $columns = "slug, name, description, start_date, end_date, venue_mode, is_active, theme, gallery_template, email, timezone, ticket_url, created_at, updated_at";
        $values = ":slug, :name, :description, :start_date, :end_date, :venue_mode, :is_active, :theme, :gallery_template, :email, :timezone, :ticket_url, :now, :now2";
        $params = [
            ':slug' => $slug,
            ':name' => $name,
            ':description' => $description,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':venue_mode' => $venueMode,
            ':is_active' => $isActive,
            ':theme' => $theme,
            ':gallery_template' => $galleryTemplate,
            ':email' => $email,
            ':timezone' => $timezone,
            ':ticket_url' => $ticketUrl,
            ':now' => $now,
            ':now2' => $now
        ];
        if ($hasCreatedBy) {
            $columns .= ", created_by_user_id";
            $values .= ", :created_by_user_id";
            $params[':created_by_user_id'] = $creatorUserId;
        }

        $stmt = $db->prepare("INSERT INTO events ($columns) VALUES ($values)");
        $stmt->execute($params);
        $newEventId = (int)$db->lastInsertId();

        if (isOrganizerRequest()) {
            $assign = $db->prepare("INSERT OR IGNORE INTO event_organizers (event_id, user_id, assigned_by, assigned_at) VALUES (:event_id, :user_id, :assigned_by, :assigned_at)");
            $assign->execute([
                ':event_id' => $newEventId,
                ':user_id' => $creatorUserId,
                ':assigned_by' => $creatorUserId,
                ':assigned_at' => $now,
            ]);
        }

        invalidate_query_cache();
        invalidate_sitemap_cache();
        audit_admin_success('event_create', 'event', $newEventId, $name);
        jsonResponse(true, ['id' => $newEventId], 'Convention created successfully');
    } catch (PDOException $e) {
        audit_admin_failure('event_create', 'database_error', 'event', null, $name ?? null);
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

    $validThemes = ['sakura', 'ocean', 'forest', 'midnight', 'sunset', 'dark', 'gray', 'crimson', 'teal', 'rose', 'amber', 'indigo'];
    $validTemplates = ['grid1', 'grid2', 'grid3', 'masonry'];
    $slug = preg_replace('/[^a-zA-Z0-9\-_]/', '', trim($input['slug']));
    $name = mb_substr(trim($input['name']), 0, 200);
    $description = mb_substr(trim($input['description'] ?? ''), 0, 1000);
    $startDate = $input['start_date'] ?? null;
    $endDate = $input['end_date'] ?? null;
    $venueMode = in_array($input['venue_mode'] ?? '', ['multi', 'single', 'calendar']) ? $input['venue_mode'] : 'multi';
    $isActive = isset($input['is_active']) ? intval($input['is_active']) : 1;
    if (isOrganizerRequest()) {
        requireCanManageEventId($id);
        $current = $db->prepare("SELECT is_active FROM events WHERE id = :id");
        $current->execute([':id' => $id]);
        $currentActive = $current->fetchColumn();
        if ($currentActive === false) {
            jsonResponse(false, null, 'Event meta not found');
            return;
        }
        if (!$currentActive && $isActive) {
            apiForbidden('Organizer role cannot activate events. Submit an active request instead.');
        }
        $isActive = intval($currentActive);
    }
    $theme = (isset($input['theme']) && in_array($input['theme'], $validThemes)) ? $input['theme'] : null;
    $galleryTemplate = in_array($input['gallery_template'] ?? '', $validTemplates) ? $input['gallery_template'] : 'grid3';
    $emailRaw = trim($input['email'] ?? '');
    $email = ($emailRaw !== '' && filter_var($emailRaw, FILTER_VALIDATE_EMAIL)) ? $emailRaw : null;
    $timezoneRaw = trim($input['timezone'] ?? '');
    $timezone = defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 'Asia/Bangkok';
    if ($timezoneRaw !== '') {
        try { new DateTimeZone($timezoneRaw); $timezone = $timezoneRaw; } catch (Exception $e) {}
    }
    $ticketUrl = sanitize_social_url($input['ticket_url'] ?? '');

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
                theme = :theme, gallery_template = :gallery_template,
                email = :email, timezone = :timezone, ticket_url = :ticket_url,
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
            ':gallery_template' => $galleryTemplate,
            ':email' => $email,
            ':timezone' => $timezone,
            ':ticket_url' => $ticketUrl,
            ':updated_at' => date('Y-m-d H:i:s'),
            ':id' => $id
        ]);

        if ($stmt->rowCount() === 0) {
            jsonResponse(false, null, 'Convention not found');
            return;
        }

        invalidate_query_cache();
        invalidate_sitemap_cache();
        audit_admin_success('event_update', 'event', $id, $name);
        jsonResponse(true, ['id' => $id], 'Convention updated successfully');
    } catch (PDOException $e) {
        audit_admin_failure('event_update', 'database_error', 'event', $id, $name ?? null);
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
        if (isOrganizerRequest()) {
            requireCanManageEventId($id);
        }
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
        invalidate_sitemap_cache();
        audit_admin_success('event_delete', 'event', $id, null);
        jsonResponse(true, null, 'Convention deleted successfully');
    } catch (PDOException $e) {
        audit_admin_failure('event_delete', 'database_error', 'event', $id, null);
        jsonResponse(false, null, safe_error_message('Failed to delete convention', $e->getMessage()));
    }
}

function missingProgramArtistReferences(PDO $db, string $categories): array {
    $names = array_values(array_filter(array_map('trim', explode(',', $categories)), fn($name) => $name !== ''));
    if (!$names) return [];

    $hasVTable = (bool)$db->query(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='artist_variants'"
    )->fetch();

    $missing = [];
    $seen = [];
    foreach ($names as $name) {
        $key = mb_strtolower($name, 'UTF-8');
        if (isset($seen[$key])) continue;
        $seen[$key] = true;

        $stmt = $db->prepare('SELECT id FROM artists WHERE LOWER(name) = LOWER(?) LIMIT 1');
        $stmt->execute([$name]);
        if ($stmt->fetchColumn()) continue;

        if ($hasVTable) {
            $stmt = $db->prepare('SELECT artist_id FROM artist_variants WHERE LOWER(variant) = LOWER(?) LIMIT 1');
            $stmt->execute([$name]);
            if ($stmt->fetchColumn()) continue;
        }

        $missing[] = $name;
    }

    return $missing;
}

function requestActivateEvent() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST method required');
        return;
    }

    if (!isOrganizerRequest()) {
        jsonResponse(false, null, 'Only organizer users need to request event activation');
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $eventId = intval($input['event_id'] ?? ($_GET['event_id'] ?? 0));
    $note = mb_substr(trim($input['note'] ?? ''), 0, 1000) ?: null;

    if ($eventId <= 0) {
        jsonResponse(false, null, 'Valid event_id required');
        return;
    }
    requireCanManageEventId($eventId);

    try {
        if (!adminTableExists($db, 'event_requests')) {
            jsonResponse(false, null, 'event_requests table not found. Run setup.php migrations.');
            return;
        }

        $eventStmt = $db->prepare("SELECT id, name, description, start_date, end_date, is_active FROM events WHERE id = :id");
        $eventStmt->execute([':id' => $eventId]);
        $event = $eventStmt->fetch(PDO::FETCH_ASSOC);
        if (!$event) {
            jsonResponse(false, null, 'Event not found');
            return;
        }
        if ((int)$event['is_active'] === 1) {
            jsonResponse(false, null, 'Event is already active');
            return;
        }

        $pending = $db->prepare("SELECT id FROM event_requests WHERE request_type = 'activate' AND event_id = :event_id AND status = 'pending' LIMIT 1");
        $pending->execute([':event_id' => $eventId]);
        if ($pending->fetchColumn()) {
            jsonResponse(false, null, 'Activation request is already pending');
            return;
        }

        $now = date('Y-m-d H:i:s');
        $requesterName = $_SESSION['admin_display_name'] ?? $_SESSION['admin_username'] ?? 'Organizer';
        $stmt = $db->prepare("
            INSERT INTO event_requests (request_type, event_id, name, description, start_date, end_date, requester_name, requester_email, note, created_at, updated_at)
            VALUES ('activate', :event_id, :name, :description, :start_date, :end_date, :requester_name, NULL, :note, :created_at, :updated_at)
        ");
        $stmt->execute([
            ':event_id' => $eventId,
            ':name' => $event['name'],
            ':description' => $event['description'],
            ':start_date' => $event['start_date'],
            ':end_date' => $event['end_date'],
            ':requester_name' => $requesterName,
            ':note' => $note,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        $newReqId = (int)$db->lastInsertId();
        audit_admin_success('event_request_activate', 'event', $eventId, $event['name'] ?? null, ['request_id' => $newReqId]);
        jsonResponse(true, ['id' => $newReqId], 'Activation request submitted');
    } catch (PDOException $e) {
        audit_admin_failure('event_request_activate', 'database_error', 'event', $eventId, null);
        jsonResponse(false, null, safe_error_message('Failed to submit activation request', $e->getMessage()));
    }
}

function listEventOrganizers() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(false, null, 'GET method required');
        return;
    }

    $eventId = intval($_GET['event_id'] ?? 0);
    if ($eventId <= 0) {
        jsonResponse(false, null, 'Valid event_id required');
        return;
    }
    if (isOrganizerRequest()) {
        requireCanManageEventId($eventId);
    }

    try {
        if (!eventOrganizerSchemaReady($db)) {
            jsonResponse(false, null, 'Organizer schema not ready. Run: php tools/migrate-add-organizer-role.php');
            return;
        }

        $event = $db->prepare("SELECT id FROM events WHERE id = :id");
        $event->execute([':id' => $eventId]);
        if (!$event->fetchColumn()) {
            jsonResponse(false, null, 'Event not found');
            return;
        }

        $users = $db->query("SELECT id, username, display_name, role, is_active FROM admin_users WHERE role = 'organizer' AND is_active = 1 ORDER BY display_name ASC, username ASC")->fetchAll(PDO::FETCH_ASSOC);
        $assignedStmt = $db->prepare("
            SELECT eo.user_id, eo.assigned_by, eo.assigned_at, au.username, au.display_name
            FROM event_organizers eo
            JOIN admin_users au ON au.id = eo.user_id
            WHERE eo.event_id = :event_id
            ORDER BY au.display_name ASC, au.username ASC
        ");
        $assignedStmt->execute([':event_id' => $eventId]);

        jsonResponse(true, [
            'organizers' => array_map(fn($u) => escapeOutputData($u, ['username', 'display_name']), $users),
            'assigned' => array_map(fn($u) => escapeOutputData($u, ['username', 'display_name']), $assignedStmt->fetchAll(PDO::FETCH_ASSOC)),
        ]);
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to load event organizers', $e->getMessage()));
    }
}

function updateEventOrganizers() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST method required');
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $eventId = intval($input['event_id'] ?? 0);
    $userIds = $input['user_ids'] ?? ($input['organizer_ids'] ?? []);

    if ($eventId <= 0 || !is_array($userIds)) {
        jsonResponse(false, null, 'event_id and user_ids array are required');
        return;
    }

    $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), fn($id) => $id > 0)));

    try {
        if (!eventOrganizerSchemaReady($db)) {
            jsonResponse(false, null, 'Organizer schema not ready. Run: php tools/migrate-add-organizer-role.php');
            return;
        }

        $event = $db->prepare("SELECT id FROM events WHERE id = :id");
        $event->execute([':id' => $eventId]);
        if (!$event->fetchColumn()) {
            jsonResponse(false, null, 'Event not found');
            return;
        }

        if (!empty($userIds)) {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $validStmt = $db->prepare("SELECT id FROM admin_users WHERE id IN ($placeholders) AND role = 'organizer' AND is_active = 1");
            $validStmt->execute($userIds);
            $validIds = array_map('intval', $validStmt->fetchAll(PDO::FETCH_COLUMN));
            sort($validIds);
            $requested = $userIds;
            sort($requested);
            if ($validIds !== $requested) {
                jsonResponse(false, null, 'All assigned users must be active organizer users');
                return;
            }
        }

        $db->beginTransaction();
        $db->prepare("DELETE FROM event_organizers WHERE event_id = :event_id")->execute([':event_id' => $eventId]);
        $insert = $db->prepare("INSERT OR IGNORE INTO event_organizers (event_id, user_id, assigned_by, assigned_at) VALUES (:event_id, :user_id, :assigned_by, :assigned_at)");
        $now = date('Y-m-d H:i:s');
        $assignedBy = organizerUserId();
        foreach ($userIds as $userId) {
            $insert->execute([
                ':event_id' => $eventId,
                ':user_id' => $userId,
                ':assigned_by' => $assignedBy,
                ':assigned_at' => $now,
            ]);
        }
        $db->commit();

        jsonResponse(true, ['event_id' => $eventId, 'assigned_count' => count($userIds)], 'Event organizers updated');
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        jsonResponse(false, null, safe_error_message('Failed to update event organizers', $e->getMessage()));
    }
}

// ============================================================================
// USER MANAGEMENT FUNCTIONS (admin only)
// ============================================================================

/**
 * List all admin users
 */
function listUsers() {
    global $db, $adminTwofaColumnsExist;

    try {
        $twofaSelect = $adminTwofaColumnsExist
            ? 'twofa_enabled, twofa_confirmed_at'
            : '0 AS twofa_enabled, NULL AS twofa_confirmed_at';
        $stmt = $db->query("SELECT id, username, display_name, role, is_active, created_at, updated_at, last_login_at, {$twofaSelect} FROM admin_users ORDER BY id ASC");
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
    global $db, $adminTwofaColumnsExist;

    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        jsonResponse(false, null, 'User ID required');
        return;
    }

    try {
        $twofaSelect = $adminTwofaColumnsExist
            ? 'twofa_enabled, twofa_confirmed_at'
            : '0 AS twofa_enabled, NULL AS twofa_confirmed_at';
        $stmt = $db->prepare("SELECT id, username, display_name, role, is_active, created_at, updated_at, last_login_at, {$twofaSelect} FROM admin_users WHERE id = :id");
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

    if (!in_array($role, ['admin', 'agent', 'organizer'])) {
        jsonResponse(false, null, 'Invalid role. Must be admin, agent, or organizer');
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

        $newUserId = (int)$db->lastInsertId();
        audit_admin_success('user_create', 'user', $newUserId, $username, ['role' => $role]);
        jsonResponse(true, ['id' => $newUserId], 'User created successfully');
    } catch (PDOException $e) {
        audit_admin_failure('user_create', 'database_error', 'user', null, $username ?? null);
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
    if (!in_array($role, ['admin', 'agent', 'organizer'])) {
        jsonResponse(false, null, 'Invalid role. Must be admin, agent, or organizer');
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

        audit_admin_success('user_update', 'user', $id, $displayName ?: null, ['role' => $role]);
        jsonResponse(true, ['id' => $id], 'User updated successfully');
    } catch (PDOException $e) {
        audit_admin_failure('user_update', 'database_error', 'user', $id, null);
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

        audit_admin_success('user_delete', 'user', $id, $userData['role'] ?? null);
        jsonResponse(true, null, 'User deleted successfully');
    } catch (PDOException $e) {
        audit_admin_failure('user_delete', 'database_error', 'user', $id, null);
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

    $timestamp = date('Ymd_His');
    $backupFilename = "backup_{$timestamp}.db";
    $backupPath = $backupDir . '/' . $backupFilename;

    // Close DB connection first to release file lock (important on Windows)
    $db = null;

    $success = copy($dbPath, $backupPath);

    // Reopen DB connection
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (!$success) {
        audit_admin_failure('backup_create', 'io_error', 'backup', null, null);
        jsonResponse(false, null, 'Failed to create backup');
        return;
    }

    audit_admin_success('backup_create', 'backup', null, $backupFilename, ['size' => filesize($backupPath)]);
    jsonResponse(true, [
        'filename' => $backupFilename,
        'size' => filesize($backupPath),
        'created_at' => date('Y-m-d H:i:s')
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
            'created_at' => date('Y-m-d H:i:s', filemtime($file))
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

    audit_admin_success('backup_download', 'backup', null, $filename);
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
        audit_admin_failure('backup_delete', 'io_error', 'backup', null, $filename);
        jsonResponse(false, null, 'Failed to delete backup file');
        return;
    }

    audit_admin_success('backup_delete', 'backup', null, $filename);
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
    $autoBackupName = 'auto_before_restore_' . date('Ymd_His') . '.db';
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
        audit_admin_failure('backup_restore', 'io_error', 'backup', null, $filename);
        jsonResponse(false, null, 'Failed to restore database');
        return;
    }

    // Invalidate all caches
    invalidate_all_caches();

    audit_admin_success('backup_restore', 'backup', null, $filename, ['auto_backup' => $autoBackupName]);
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
    $autoBackupName = 'auto_before_restore_' . date('Ymd_His') . '.db';
    if (!copy($dbPath, $backupDir . '/' . $autoBackupName)) {
        jsonResponse(false, null, 'Failed to create auto-backup before restore. Restore aborted.');
        return;
    }

    // Close current DB connection
    $db = null;

    // Move uploaded file to database location
    if (!move_uploaded_file($file['tmp_name'], $dbPath)) {
        audit_admin_failure('backup_upload_restore', 'io_error', 'backup', null, $file['name'] ?? null);
        jsonResponse(false, null, 'Failed to restore database');
        return;
    }

    // Invalidate all caches
    invalidate_all_caches();

    audit_admin_success('backup_upload_restore', 'backup', null, $file['name'] ?? null, ['auto_backup' => $autoBackupName]);
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
    $validThemes = ['sakura', 'ocean', 'forest', 'midnight', 'sunset', 'dark', 'gray', 'crimson', 'teal', 'rose', 'amber', 'indigo'];
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
        audit_admin_success('settings_update', 'settings', null, 'theme', ['value' => $theme]);
        jsonResponse(true, ['theme' => $theme], 'Theme saved');
    } else {
        audit_admin_failure('settings_update', 'io_error', 'settings', null, 'theme');
        jsonResponse(false, null, 'Failed to save theme setting');
    }
}

function getTitleSetting() {
    require_api_admin_role();
    $settingsFile = dirname(__DIR__) . '/cache/site-settings.json';
    $title = defined('APP_NAME') ? APP_NAME : 'Idol Stage Timetable';
    $coverBg = '';
    if (file_exists($settingsFile)) {
        $data = json_decode(file_get_contents($settingsFile), true);
        if (!empty($data['site_title'])) $title = $data['site_title'];
        if (!empty($data['site_cover_bg'])) $coverBg = $data['site_cover_bg'];
    }
    jsonResponse(true, [
        'site_title'    => htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        'site_cover_bg' => $coverBg,
    ]);
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
        audit_admin_success('settings_update', 'settings', null, 'title');
        jsonResponse(true, ['site_title' => $title], 'Title saved');
    } else {
        audit_admin_failure('settings_update', 'io_error', 'settings', null, 'title');
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
        audit_admin_success('settings_update', 'settings', null, 'disclaimer');
        jsonResponse(true, null, 'Disclaimer saved');
    } else {
        audit_admin_failure('settings_update', 'io_error', 'settings', null, 'disclaimer');
        jsonResponse(false, null, 'Failed to save disclaimer');
    }
}

// =============================================================================
// SITE COVER BACKGROUND IMAGE
// =============================================================================

function uploadSiteCoverBg() {
    require_api_admin_role();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST required');
        return;
    }
    if (empty($_FILES['cover_bg']) || $_FILES['cover_bg']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(false, null, 'No file uploaded');
        return;
    }
    $file = $_FILES['cover_bg'];
    if ($file['size'] > 5 * 1024 * 1024) {
        jsonResponse(false, null, 'File too large (max 5 MB)');
        return;
    }
    $imgInfo = @getimagesize($file['tmp_name']);
    if (!$imgInfo) {
        jsonResponse(false, null, 'Invalid image file');
        return;
    }
    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($imgInfo['mime'], $allowedMimes)) {
        jsonResponse(false, null, 'Unsupported image type');
        return;
    }
    $imgType = $imgInfo[2];

    $uploadDir = dirname(__DIR__) . '/uploads/site/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $destFilename = 'cover_bg_' . uniqid() . '.jpg';
    $destPath     = $uploadDir . $destFilename;
    $relativePath = 'uploads/site/' . $destFilename;

    if (!processAndSaveImage($file['tmp_name'], $destPath, 1920, 480, $imgType, 'fit')) {
        jsonResponse(false, null, 'Failed to process image');
        return;
    }

    // Delete old file if any
    $settingsFile = dirname(__DIR__) . '/cache/site-settings.json';
    $existing = file_exists($settingsFile) ? (json_decode(file_get_contents($settingsFile), true) ?? []) : [];
    if (!empty($existing['site_cover_bg'])) {
        $oldPath = dirname(__DIR__) . '/' . ltrim($existing['site_cover_bg'], '/');
        if (file_exists($oldPath)) @unlink($oldPath);
    }

    $existing['site_cover_bg'] = $relativePath;
    $existing['updated_at']    = time();
    if (file_put_contents($settingsFile, json_encode($existing)) === false) {
        jsonResponse(false, null, 'Failed to save settings');
        return;
    }

    jsonResponse(true, ['path' => $relativePath], 'Cover background saved');
}

function deleteSiteCoverBg() {
    require_api_admin_role();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST required');
        return;
    }
    $settingsFile = dirname(__DIR__) . '/cache/site-settings.json';
    $existing = file_exists($settingsFile) ? (json_decode(file_get_contents($settingsFile), true) ?? []) : [];
    if (!empty($existing['site_cover_bg'])) {
        $oldPath = dirname(__DIR__) . '/' . ltrim($existing['site_cover_bg'], '/');
        if (file_exists($oldPath)) @unlink($oldPath);
    }
    $existing['site_cover_bg'] = '';
    $existing['updated_at']    = time();
    file_put_contents($settingsFile, json_encode($existing));
    audit_admin_success('site_cover_delete', 'settings', null, 'site_cover_bg');
    jsonResponse(true, null, 'Cover background deleted');
}

// =============================================================================
// GOOGLE CONFIG (Google Analytics + Google AdSense)
// =============================================================================

function getAnalyticsConfig() {
    require_api_admin_role();
    $configFile = __DIR__ . '/../config/google-config.json';

    $default = [
        'ga_id'               => '',
        'ads_client'          => '',
        'ads_slot_leaderboard'=> '',
        'ads_slot_rectangle'  => '',
        'ads_slot_responsive' => '',
        'updated_at'          => '',
    ];

    $escFields = function(array &$cfg): void {
        foreach (['ga_id', 'ads_client', 'ads_slot_leaderboard', 'ads_slot_rectangle', 'ads_slot_responsive'] as $f) {
            if (isset($cfg[$f]) && is_string($cfg[$f])) {
                $cfg[$f] = htmlspecialchars($cfg[$f], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
        }
    };

    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        if (is_array($config)) {
            $config = array_merge($default, $config);
            $escFields($config);
            jsonResponse(true, $config);
            return;
        }
    }

    $escFields($default);
    jsonResponse(true, $default);
}

function saveAnalyticsConfig() {
    require_api_admin_role();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST required');
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $configFile = __DIR__ . '/../config/google-config.json';
    $configDir  = dirname($configFile);

    if (!is_dir($configDir)) {
        mkdir($configDir, 0755, true);
    }

    $existing = [];
    if (file_exists($configFile)) {
        $existing = json_decode(file_get_contents($configFile), true) ?? [];
    }

    $existing['ga_id']                = trim($input['ga_id']                ?? '');
    $existing['ads_client']           = trim($input['ads_client']           ?? '');
    $existing['ads_slot_leaderboard'] = trim($input['ads_slot_leaderboard'] ?? '');
    $existing['ads_slot_rectangle']   = trim($input['ads_slot_rectangle']   ?? '');
    $existing['ads_slot_responsive']  = trim($input['ads_slot_responsive']  ?? '');
    $existing['updated_at']           = date('c');

    $ok = file_put_contents($configFile, json_encode($existing, JSON_PRETTY_PRINT), LOCK_EX);
    if ($ok !== false) {
        audit_admin_success('config_update', 'config', null, 'analytics');
        jsonResponse(true, null, 'Analytics settings saved');
    } else {
        audit_admin_failure('config_update', 'io_error', 'config', null, 'analytics');
        jsonResponse(false, null, 'Failed to save analytics settings');
    }
}

// =============================================================================
// EMAIL CONFIG
// =============================================================================

function ensureEmailHelpersLoaded(): void {
    if (!function_exists('email_parse_recipients') || !function_exists('email_send')) {
        $emailHelper = __DIR__ . '/../functions/email.php';
        if (is_file($emailHelper)) {
            require_once $emailHelper;
        }
    }

    if (!function_exists('email_parse_recipients') || !function_exists('email_send')) {
        jsonResponse(false, null, 'Email helper is not available. Please deploy functions/email.php and config.php from v9.6.0.');
    }
}

function emailConfigDefaults(): array {
    $siteName = function_exists('email_site_name')
        ? email_site_name()
        : (defined('APP_NAME') ? APP_NAME : 'Idol Stage Timetable');

    return [
        'enabled' => false,
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_encryption' => 'tls',
        'smtp_username' => '',
        'smtp_password' => '',
        'from_email' => '',
        'from_name' => $siteName,
        'recipients' => '',
        'updated_at' => '',
    ];
}

function getEmailConfig() {
    require_api_admin_role();
    ensureEmailHelpersLoaded();
    $configFile = __DIR__ . '/../config/email-config.json';
    $default = emailConfigDefaults();

    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        if (is_array($config)) {
            $config = array_merge($default, $config);
        } else {
            $config = $default;
        }
    } else {
        $config = $default;
    }

    foreach (['smtp_host', 'smtp_username', 'smtp_password', 'from_email', 'from_name', 'recipients'] as $field) {
        if (isset($config[$field]) && is_string($config[$field])) {
            $config[$field] = htmlspecialchars($config[$field], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
    }

    jsonResponse(true, $config);
}

function normalizeEmailConfigInput(array $input, array $existing = []): array {
    ensureEmailHelpersLoaded();

    $encryption = strtolower(trim($input['smtp_encryption'] ?? ($existing['smtp_encryption'] ?? 'tls')));
    if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
        $encryption = 'tls';
    }

    $port = intval($input['smtp_port'] ?? ($existing['smtp_port'] ?? 587));
    if ($port <= 0 || $port > 65535) {
        $port = 587;
    }

    $fromEmail = trim($input['from_email'] ?? ($existing['from_email'] ?? ''));
    $recipients = trim($input['recipients'] ?? ($existing['recipients'] ?? ''));
    $smtpHost = trim($input['smtp_host'] ?? ($existing['smtp_host'] ?? ''));

    if (($input['enabled'] ?? false) && $smtpHost === '') {
        jsonResponse(false, null, 'SMTP host is required');
    }

    if (($input['enabled'] ?? false) && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(false, null, 'Valid from email is required');
    }

    if (($input['enabled'] ?? false) && empty(email_parse_recipients($recipients))) {
        jsonResponse(false, null, 'At least one valid recipient email is required');
    }

    return [
        'enabled' => (bool)($input['enabled'] ?? false),
        'smtp_host' => $smtpHost,
        'smtp_port' => $port,
        'smtp_encryption' => $encryption,
        'smtp_username' => trim($input['smtp_username'] ?? ($existing['smtp_username'] ?? '')),
        'smtp_password' => (string)($input['smtp_password'] ?? ($existing['smtp_password'] ?? '')),
        'from_email' => $fromEmail,
        'from_name' => mb_substr(trim($input['from_name'] ?? ($existing['from_name'] ?? email_site_name())), 0, 100),
        'recipients' => $recipients,
        'updated_at' => date('c'),
    ];
}

function saveEmailConfig() {
    require_api_admin_role();
    ensureEmailHelpersLoaded();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST required');
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        jsonResponse(false, null, 'Invalid JSON');
        return;
    }

    $configFile = __DIR__ . '/../config/email-config.json';
    $existing = file_exists($configFile) ? (json_decode(file_get_contents($configFile), true) ?? []) : [];
    $config = normalizeEmailConfigInput($input, $existing);

    $ok = file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    if ($ok !== false) {
        audit_admin_success('config_update', 'config', null, 'email');
        jsonResponse(true, null, 'Email settings saved');
    }

    audit_admin_failure('config_update', 'io_error', 'config', null, 'email');
    jsonResponse(false, null, 'Failed to save email settings');
}

function sendEmailTest() {
    require_api_admin_role();
    ensureEmailHelpersLoaded();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST required');
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        jsonResponse(false, null, 'Invalid JSON');
        return;
    }

    $configFile = __DIR__ . '/../config/email-config.json';
    $existing = file_exists($configFile) ? (json_decode(file_get_contents($configFile), true) ?? []) : [];
    $config = normalizeEmailConfigInput(array_merge($input, ['enabled' => true]), $existing);
    $recipients = email_parse_recipients($config['recipients']);
    $siteName = email_site_name();

    $html = '<p>Email notification test from <strong>' . email_escape($siteName) . '</strong>.</p>';
    $text = 'Email notification test from ' . $siteName . '.';
    $ok = email_send('[' . $siteName . '] Email Notification Test', $html, $text, [
        'smtp_host' => $config['smtp_host'],
        'smtp_port' => $config['smtp_port'],
        'smtp_encryption' => $config['smtp_encryption'],
        'smtp_username' => $config['smtp_username'],
        'smtp_password' => $config['smtp_password'],
        'from_email' => $config['from_email'],
        'from_name' => $config['from_name'],
        'recipients' => $recipients,
        'force' => true,
    ]);

    if ($ok) {
        jsonResponse(true, null, 'Test email sent');
    }
    jsonResponse(false, null, 'Test email failed. Check cache/logs/email.log');
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
        audit_admin_success('config_update', 'config', null, 'telegram');
        jsonResponse(true, $existing, 'Telegram config saved');
    } else {
        audit_admin_failure('config_update', 'io_error', 'config', null, 'telegram');
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
    require_api_admin_role();
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
// WEB PUSH LOG VIEWER
// =============================================================================

function getWebPushLog() {
    require_login();
    require_api_admin_role();
    $logDir = __DIR__ . '/../cache/logs';
    $files  = [];

    if (file_exists($logDir . '/webpush-cron.log')) {
        $files[] = ['key' => 'current', 'label' => 'webpush-cron.log (current)', 'path' => $logDir . '/webpush-cron.log'];
    }
    $archives = glob($logDir . '/webpush-cron-[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9].log') ?: [];
    if (!empty($archives)) {
        rsort($archives);
        foreach ($archives as $f) {
            $basename = basename($f);
            $files[] = ['key' => $basename, 'label' => $basename, 'path' => $f];
        }
    }

    $requestedKey = get_sanitized_param('file', '');
    $selectedPath = null;
    foreach ($files as $f) {
        if ($f['key'] === $requestedKey) { $selectedPath = $f['path']; break; }
    }
    if (!$selectedPath && !empty($files)) {
        $selectedPath = $files[0]['path'];
        $requestedKey = $files[0]['key'];
    }

    $content = ''; $totalLines = 0;
    if ($selectedPath && file_exists($selectedPath)) {
        $lines      = @file($selectedPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $totalLines = count($lines);
        $content    = implode("\n", array_slice($lines, -500));
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success'       => true,
        'files'         => array_map(fn($f) => ['key' => $f['key'], 'label' => $f['label']], $files),
        'selected'      => $requestedKey,
        'content'       => $content,
        'total_lines'   => $totalLines,
        'showing_lines' => min($totalLines, 500),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function downloadWebPushLog() {
    require_login();
    require_api_admin_role();
    $logDir        = __DIR__ . '/../cache/logs';
    $requestedFile = get_sanitized_param('file', 'webpush-cron.log');
    $isValid = $requestedFile === 'webpush-cron.log' ||
               preg_match('/^webpush-cron-\d{4}-\d{2}-\d{2}\.log$/', $requestedFile);
    if (!$isValid) { jsonResponse(false, null, 'Invalid filename'); return; }
    $filePath = $logDir . '/' . $requestedFile;
    if (!file_exists($filePath)) { jsonResponse(false, null, 'Log file not found'); return; }
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $requestedFile . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, must-revalidate');
    readfile($filePath);
    exit;
}

// =============================================================================
// EMAIL LOG VIEWER
// =============================================================================

function getEmailLog() {
    require_login();
    require_api_admin_role();
    $logDir = __DIR__ . '/../cache/logs';
    $files  = [];

    if (file_exists($logDir . '/email.log')) {
        $files[] = ['key' => 'current', 'label' => 'email.log (current)', 'path' => $logDir . '/email.log'];
    }
    $archives = glob($logDir . '/email-[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9].log') ?: [];
    if (!empty($archives)) {
        rsort($archives);
        foreach ($archives as $f) {
            $basename = basename($f);
            $files[] = ['key' => $basename, 'label' => $basename, 'path' => $f];
        }
    }

    $requestedKey = get_sanitized_param('file', '');
    $selectedPath = null;
    foreach ($files as $f) {
        if ($f['key'] === $requestedKey) { $selectedPath = $f['path']; break; }
    }
    if (!$selectedPath && !empty($files)) {
        $selectedPath = $files[0]['path'];
        $requestedKey = $files[0]['key'];
    }

    $content = ''; $totalLines = 0;
    if ($selectedPath && file_exists($selectedPath)) {
        $lines      = @file($selectedPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $totalLines = count($lines);
        $content    = implode("\n", array_slice($lines, -500));
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success'       => true,
        'files'         => array_map(fn($f) => ['key' => $f['key'], 'label' => $f['label']], $files),
        'selected'      => $requestedKey,
        'content'       => $content,
        'total_lines'   => $totalLines,
        'showing_lines' => min($totalLines, 500),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function downloadEmailLog() {
    require_login();
    require_api_admin_role();
    $logDir        = __DIR__ . '/../cache/logs';
    $requestedFile = get_sanitized_param('file', 'email.log');
    $isValid = $requestedFile === 'email.log' ||
               preg_match('/^email-\d{4}-\d{2}-\d{2}\.log$/', $requestedFile);
    if (!$isValid) { jsonResponse(false, null, 'Invalid filename'); return; }
    $filePath = $logDir . '/' . $requestedFile;
    if (!file_exists($filePath)) { jsonResponse(false, null, 'Log file not found'); return; }
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $requestedFile . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, must-revalidate');
    readfile($filePath);
    exit;
}

// =============================================================================
// WEB PUSH CONFIG
// =============================================================================

function getWebPushConfig() {
    require_api_admin_role();

    $configFile = __DIR__ . '/../config/webpush-config.json';
    $default = [
        'enabled'               => false,
        'vapid_public_key'      => '',
        'vapid_subject'         => 'mailto:admin@stageidol.local',
        'site_url'              => '',
        'notify_before_minutes' => 60,
        'max_subs_per_token'    => 5,
        'updated_at'            => null,
    ];

    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        if (is_array($config)) {
            // Never expose private key to admin UI
            unset($config['vapid_private_key_pem']);
            $config = array_merge($default, $config);
            $config['vapid_public_key']  = htmlspecialchars($config['vapid_public_key'] ?? '',  ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $config['vapid_subject']     = htmlspecialchars($config['vapid_subject']     ?? '',  ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $config['site_url']          = htmlspecialchars($config['site_url']          ?? '',  ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $config['has_private_key']   = !empty($config['vapid_public_key']);
            jsonResponse(true, $config);
            return;
        }
    }

    $default['has_private_key'] = false;
    jsonResponse(true, $default);
}

function saveWebPushConfig() {
    require_api_admin_role();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST required');
        return;
    }

    $input      = json_decode(file_get_contents('php://input'), true) ?? [];
    $configFile = __DIR__ . '/../config/webpush-config.json';
    $configDir  = dirname($configFile);

    if (!is_dir($configDir)) {
        mkdir($configDir, 0755, true);
    }

    $existing = [];
    if (file_exists($configFile)) {
        $existing = json_decode(file_get_contents($configFile), true) ?? [];
    }

    $existing['enabled']               = (bool)($input['enabled']               ?? false);
    $existing['vapid_subject']         = trim($input['vapid_subject']             ?? 'mailto:admin@stageidol.local');
    $existing['site_url']              = rtrim(trim($input['site_url']            ?? ''), '/');
    $existing['notify_before_minutes'] = max(5, min(1440, intval($input['notify_before_minutes'] ?? 60)));
    $existing['max_subs_per_token']    = max(1, min(20,   intval($input['max_subs_per_token']    ?? 5)));
    $existing['updated_at']            = date('c');

    // Validate subject format
    if (!preg_match('/^(mailto:.+|https?:\/\/.+)$/', $existing['vapid_subject'])) {
        jsonResponse(false, null, 'vapid_subject must start with mailto: or https://');
        return;
    }

    // Validate site_url (optional but must be http/https if provided)
    if (!empty($existing['site_url']) && !preg_match('/^https?:\/\/.+/', $existing['site_url'])) {
        jsonResponse(false, null, 'site_url must start with http:// or https://');
        return;
    }

    $json = json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (file_put_contents($configFile, $json, LOCK_EX) === false) {
        audit_admin_failure('config_update', 'io_error', 'config', null, 'webpush');
        jsonResponse(false, null, 'Failed to save Web Push config');
        return;
    }

    audit_log([
        'action'        => 'config_update',
        'outcome'       => 'success',
        'actor_user_id' => isset($_SESSION['admin_user_id']) ? (int)$_SESSION['admin_user_id'] : null,
        'actor_name'    => $_SESSION['admin_display_name'] ?? $_SESSION['admin_username'] ?? 'admin',
        'entity_type'   => 'config',
        'entity_id'     => null,
        'entity_name'   => 'webpush',
    ]);
    jsonResponse(true, null, 'Web Push config saved');
}

function generateWebPushVapidKeys() {
    require_api_admin_role();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST required');
        return;
    }

    if (!function_exists('webpush_generate_vapid_keys')) {
        jsonResponse(false, null, 'Web Push functions not loaded');
        return;
    }

    $keys = webpush_generate_vapid_keys();
    if (!$keys) {
        jsonResponse(false, null, 'Failed to generate VAPID keys. OpenSSL EC P-256 support required.');
        return;
    }

    $configFile = __DIR__ . '/../config/webpush-config.json';
    $configDir  = dirname($configFile);
    if (!is_dir($configDir)) {
        mkdir($configDir, 0755, true);
    }

    $existing = [];
    if (file_exists($configFile)) {
        $existing = json_decode(file_get_contents($configFile), true) ?? [];
    }

    $existing['vapid_public_key']     = $keys['public_key'];
    $existing['vapid_private_key_pem'] = $keys['private_key_pem'];
    $existing['updated_at']           = date('c');

    $json = json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (file_put_contents($configFile, $json, LOCK_EX) === false) {
        jsonResponse(false, null, 'Failed to save VAPID keys');
        return;
    }

    audit_log([
        'action'        => 'config_update',
        'outcome'       => 'success',
        'actor_user_id' => isset($_SESSION['admin_user_id']) ? (int)$_SESSION['admin_user_id'] : null,
        'actor_name'    => $_SESSION['admin_display_name'] ?? $_SESSION['admin_username'] ?? 'admin',
        'entity_type'   => 'config',
        'entity_id'     => null,
        'entity_name'   => 'webpush_vapid_keys',
    ]);

    // Return public key only (never expose private key)
    jsonResponse(true, ['vapid_public_key' => $keys['public_key']], 'VAPID keys generated');
}

// =============================================================================
// ADMIN AUDIT LOG VIEWER
// =============================================================================

function getAdminAuditLog() {
    require_api_admin_role();
    // GET endpoint - no CSRF needed

    $logDir = _audit_log_dir();
    $files  = [];

    // Dated log files, newest first
    $archived = glob($logDir . '/admin-audit-[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9].log') ?: [];
    if (!empty($archived)) {
        rsort($archived);
        foreach ($archived as $f) {
            $basename = basename($f);
            $files[] = ['key' => $basename, 'label' => $basename, 'path' => $f];
        }
    }

    // Determine which file to read
    $requestedKey = get_sanitized_param('file', '');
    $selectedPath = null;
    foreach ($files as $f) {
        if ($f['key'] === $requestedKey) { $selectedPath = $f['path']; break; }
    }
    if (!$selectedPath && !empty($files)) {
        $selectedPath = $files[0]['path'];
        $requestedKey = $files[0]['key'];
    }

    $content    = '';
    $totalLines = 0;
    if ($selectedPath && file_exists($selectedPath)) {
        $lines      = @file($selectedPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $totalLines = count($lines);
        $lastLines  = array_slice($lines, -500);
        $content    = implode("\n", $lastLines);
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success'       => true,
        'files'         => array_map(fn($f) => ['key' => $f['key'], 'label' => $f['label']], $files),
        'selected'      => $requestedKey,
        'content'       => $content,
        'total_lines'   => $totalLines,
        'showing_lines' => min($totalLines, 500),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function downloadAdminAuditLog() {
    require_api_admin_role();
    // GET endpoint

    $logDir       = _audit_log_dir();
    $requestedFile = get_sanitized_param('file', '');

    // Only allow admin-audit-YYYY-MM-DD.log
    if (!preg_match('/^admin-audit-\d{4}-\d{2}-\d{2}\.log$/', $requestedFile)) {
        jsonResponse(false, null, 'Invalid filename');
        return;
    }

    $filePath = $logDir . '/' . $requestedFile;
    if (!file_exists($filePath)) {
        jsonResponse(false, null, 'Log file not found');
        return;
    }

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

    if ($search !== '' && mb_strlen($search) >= 3 && fts5_available($db)) {
        $ftsRows = fts5_search_artists($db, $search, 2000);
        $ids     = array_column($ftsRows, 'id') ?: [-1];
        $where[] = 'a.id IN (' . implode(',', array_map('intval', $ids)) . ')';
    } elseif ($search !== '') {
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
                   a.display_picture, a.cover_picture,
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
            return escapeOutputData($a, ['name', 'group_name', 'display_picture', 'cover_picture']);
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
                   a.display_picture, a.cover_picture,
                   a.social_facebook, a.social_instagram, a.social_twitter, a.social_tiktok,
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

        $artist = escapeOutputData($artist, ['name', 'group_name', 'display_picture', 'cover_picture',
            'social_facebook', 'social_instagram', 'social_twitter', 'social_tiktok']);
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

    $socialFacebook  = sanitize_social_url($input['social_facebook'] ?? '');
    $socialInstagram = sanitize_social_url($input['social_instagram'] ?? '');
    $socialTwitter   = sanitize_social_url($input['social_twitter'] ?? '');
    $socialTiktok    = sanitize_social_url($input['social_tiktok'] ?? '');

    try {
        $now  = date('Y-m-d H:i:s');
        $stmt = $db->prepare("
            INSERT INTO artists (name, is_group, group_id,
                social_facebook, social_instagram, social_twitter, social_tiktok,
                created_at, updated_at)
            VALUES (:name, :is_group, :group_id,
                :social_facebook, :social_instagram, :social_twitter, :social_tiktok,
                :created_at, :updated_at)
        ");
        $stmt->execute([
            ':name'            => $name,
            ':is_group'        => $isGroup,
            ':group_id'        => $groupId,
            ':social_facebook' => $socialFacebook,
            ':social_instagram'=> $socialInstagram,
            ':social_twitter'  => $socialTwitter,
            ':social_tiktok'   => $socialTiktok,
            ':created_at'      => $now,
            ':updated_at'      => $now,
        ]);

        $id = (int)$db->lastInsertId();
        invalidate_data_version_cache();
        invalidate_artist_query_cache();
        invalidate_sitemap_cache();
        audit_admin_success('artist_create', 'artist', $id, $name);
        jsonResponse(true, ['id' => $id], 'Artist created successfully');
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'UNIQUE') !== false) {
            jsonResponse(false, null, 'Artist name already exists');
        } else {
            audit_admin_failure('artist_create', 'database_error', 'artist', null, $name);
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

    $socialFacebook  = sanitize_social_url($input['social_facebook'] ?? '');
    $socialInstagram = sanitize_social_url($input['social_instagram'] ?? '');
    $socialTwitter   = sanitize_social_url($input['social_twitter'] ?? '');
    $socialTiktok    = sanitize_social_url($input['social_tiktok'] ?? '');

    try {
        $stmt = $db->prepare("
            UPDATE artists
            SET name = :name, is_group = :is_group, group_id = :group_id,
                social_facebook = :social_facebook, social_instagram = :social_instagram,
                social_twitter = :social_twitter, social_tiktok = :social_tiktok,
                updated_at = :updated_at
            WHERE id = :id
        ");
        $stmt->execute([
            ':name'            => $name,
            ':is_group'        => $isGroup,
            ':group_id'        => $groupId,
            ':social_facebook' => $socialFacebook,
            ':social_instagram'=> $socialInstagram,
            ':social_twitter'  => $socialTwitter,
            ':social_tiktok'   => $socialTiktok,
            ':updated_at'      => date('Y-m-d H:i:s'),
            ':id'              => $id,
        ]);

        if ($stmt->rowCount() === 0) {
            jsonResponse(false, null, 'Artist not found or no changes made');
            return;
        }

        invalidate_data_version_cache();
        invalidate_artist_query_cache();
        invalidate_sitemap_cache();
        audit_admin_success('artist_update', 'artist', $id, $name);
        jsonResponse(true, null, 'Artist updated successfully');
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'UNIQUE') !== false) {
            jsonResponse(false, null, 'Artist name already exists');
        } else {
            audit_admin_failure('artist_update', 'database_error', 'artist', $id, $name);
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
        invalidate_sitemap_cache();
        audit_admin_success('artist_delete', 'artist', $id, null);
        jsonResponse(true, null, 'Artist deleted successfully');
    } catch (PDOException $e) {
        audit_admin_failure('artist_delete', 'database_error', 'artist', $id, null);
        jsonResponse(false, null, safe_error_message('Failed to delete artist', $e->getMessage()));
    }
}

/**
 * Upload display_picture or cover_picture for an artist
 * POST multipart/form-data: artist_id, picture_type (display|cover), picture (file)
 * Resizes: display → 400×400 center-crop JPEG 85%; cover → 1200×400 center-crop JPEG 85%
 */
function uploadArtistPicture() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST method required');
        return;
    }

    $artistId    = intval($_POST['artist_id'] ?? 0);
    $pictureType = trim($_POST['picture_type'] ?? '');

    if ($artistId <= 0) {
        jsonResponse(false, null, 'Valid artist_id required');
        return;
    }
    if (!in_array($pictureType, ['display', 'cover'])) {
        jsonResponse(false, null, 'picture_type must be "display" or "cover"');
        return;
    }
    if (!isset($_FILES['picture']) || $_FILES['picture']['error'] !== UPLOAD_ERR_OK) {
        $errCode = $_FILES['picture']['error'] ?? -1;
        jsonResponse(false, null, 'File upload error (code: ' . $errCode . ')');
        return;
    }

    $file = $_FILES['picture'];

    // Size limit: 5 MB
    if ($file['size'] > 5 * 1024 * 1024) {
        jsonResponse(false, null, 'File too large (max 5 MB)');
        return;
    }

    // Validate image via getimagesize (checks actual file content)
    $imgInfo = @getimagesize($file['tmp_name']);
    if (!$imgInfo) {
        jsonResponse(false, null, 'Invalid image file');
        return;
    }
    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($imgInfo['mime'], $allowedMimes)) {
        jsonResponse(false, null, 'Unsupported image type. Allowed: JPG, PNG, GIF, WEBP');
        return;
    }
    if (!function_exists('imagecreatefromjpeg')) {
        jsonResponse(false, null, 'GD extension not available on this server');
        return;
    }

    // Target dimensions
    if ($pictureType === 'display') {
        $targetW = 400; $targetH = 400;
    } else {
        $targetW = 1200; $targetH = 400;
    }

    // Destination path
    $uploadsDir = defined('ROOT_DIR') ? ROOT_DIR . '/uploads/artists' : dirname(__DIR__) . '/uploads/artists';
    if (!is_dir($uploadsDir)) {
        @mkdir($uploadsDir, 0755, true);
    }
    $filename = $artistId . '_' . $pictureType . '_' . uniqid() . '.jpg';
    $destPath = $uploadsDir . '/' . $filename;

    // Process image with GD
    if (!processAndSaveArtistImage($file['tmp_name'], $destPath, $targetW, $targetH, $imgInfo[2])) {
        jsonResponse(false, null, 'Failed to process image');
        return;
    }

    // Relative URL for storing
    $column   = ($pictureType === 'display') ? 'display_picture' : 'cover_picture';
    $relPath  = 'uploads/artists/' . $filename;

    try {
        // Fetch old picture path to delete it
        $stmtOld = $db->prepare("SELECT $column FROM artists WHERE id = :id");
        $stmtOld->execute([':id' => $artistId]);
        $old = $stmtOld->fetchColumn();

        // Save new path to DB
        $stmt = $db->prepare("UPDATE artists SET $column = :path, updated_at = :now WHERE id = :id");
        $stmt->execute([':path' => $relPath, ':now' => date('Y-m-d H:i:s'), ':id' => $artistId]);

        if ($stmt->rowCount() === 0) {
            @unlink($destPath);
            jsonResponse(false, null, 'Artist not found');
            return;
        }

        // Delete old file if it exists and differs
        if ($old && $old !== $relPath) {
            $oldFull = dirname(__DIR__) . '/' . $old;
            if (file_exists($oldFull)) {
                @unlink($oldFull);
            }
        }

        invalidate_artist_query_cache();
        invalidate_query_cache();

        // Return relative path only — JS uses APP_ROOT to build the full URL
        jsonResponse(true, ['path' => $relPath], 'Picture uploaded successfully');
    } catch (PDOException $e) {
        @unlink($destPath);
        jsonResponse(false, null, safe_error_message('Failed to save picture', $e->getMessage()));
    }
}

/**
 * Process and save image as JPEG 85%.
 * mode='crop' → center-crop to exact targetW×targetH
 * mode='fit'  → scale to fit within maxW×maxH preserving aspect ratio (no upscale)
 */
function processAndSaveImage(string $srcPath, string $destPath, int $maxW, int $maxH, int $imgType, string $mode = 'crop'): bool {
    switch ($imgType) {
        case IMAGETYPE_JPEG:
            $src = @imagecreatefromjpeg($srcPath);
            break;
        case IMAGETYPE_PNG:
            $src = @imagecreatefrompng($srcPath);
            break;
        case IMAGETYPE_GIF:
            $src = @imagecreatefromgif($srcPath);
            break;
        case IMAGETYPE_WEBP:
            $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($srcPath) : false;
            break;
        default:
            return false;
    }
    if (!$src) return false;

    $srcW = imagesx($src);
    $srcH = imagesy($src);

    if ($mode === 'fit') {
        // Scale to fit within maxW×maxH, no upscale
        $scale   = min($maxW / $srcW, $maxH / $srcH, 1.0);
        $dstW    = max(1, (int)round($srcW * $scale));
        $dstH    = max(1, (int)round($srcH * $scale));
        $cropX   = 0;
        $cropY   = 0;
        $cropW   = $srcW;
        $cropH   = $srcH;
        $targetW = $dstW;
        $targetH = $dstH;
    } else {
        // Center-crop to exact maxW×maxH
        $srcRatio    = $srcW / $srcH;
        $targetRatio = $maxW / $maxH;

        if ($srcRatio > $targetRatio) {
            $cropH = $srcH;
            $cropW = (int)round($srcH * $targetRatio);
            $cropX = (int)round(($srcW - $cropW) / 2);
            $cropY = 0;
        } else {
            $cropW = $srcW;
            $cropH = (int)round($srcW / $targetRatio);
            $cropX = 0;
            $cropY = (int)round(($srcH - $cropH) / 2);
        }
        $targetW = $maxW;
        $targetH = $maxH;
    }

    $dst = imagecreatetruecolor($targetW, $targetH);
    if (!$dst) { imagedestroy($src); return false; }

    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);

    imagecopyresampled($dst, $src, 0, 0, $cropX, $cropY, $targetW, $targetH, $cropW, $cropH);
    imagedestroy($src);

    $result = imagejpeg($dst, $destPath, 85);
    imagedestroy($dst);
    return $result;
}

/**
 * Center-crop and resize an image, save as JPEG 85% (thin wrapper for backward compat).
 */
function processAndSaveArtistImage(string $srcPath, string $destPath, int $targetW, int $targetH, int $imgType): bool {
    return processAndSaveImage($srcPath, $destPath, $targetW, $targetH, $imgType, 'crop');
}

// =============================================================================
// EVENT COVER IMAGE (Hero Carousel / Card 16:9)
// =============================================================================

/**
 * Upload a cover image for an event (pre-cropped by Cropper.js on client).
 * POST multipart/form-data: file "cover", GET params event_id, cover_type (hero|card)
 *   hero → cover_image      (16:9, 1600×900)
 *   card → cover_image_card (4:3,   800×600)
 */
function uploadEventCover() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST method required');
        return;
    }

    $eventId   = intval($_GET['event_id'] ?? 0);
    $coverType = in_array($_GET['cover_type'] ?? '', ['hero', 'card']) ? $_GET['cover_type'] : 'hero';

    if ($eventId <= 0) {
        jsonResponse(false, null, 'Valid event_id required');
        return;
    }
    if (isOrganizerRequest()) {
        requireCanManageEventId($eventId);
    }

    $dbCol    = $coverType === 'card' ? 'cover_image_card' : 'cover_image';
    $maxW     = $coverType === 'card' ? 800  : 1600;
    $maxH     = $coverType === 'card' ? 600  : 900;
    $prefix   = $coverType === 'card' ? 'card_' : 'cover_';

    $chk = $db->prepare("SELECT id, cover_image, cover_image_card FROM events WHERE id = :id");
    $chk->execute([':id' => $eventId]);
    $existingEvent = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$existingEvent) {
        jsonResponse(false, null, 'Event not found');
        return;
    }

    if (!isset($_FILES['cover']) || $_FILES['cover']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(false, null, 'File upload error');
        return;
    }

    $file = $_FILES['cover'];
    if ($file['size'] > 5 * 1024 * 1024) {
        jsonResponse(false, null, 'File exceeds 5 MB limit');
        return;
    }

    $imgInfo = @getimagesize($file['tmp_name']);
    if (!$imgInfo) {
        jsonResponse(false, null, 'Invalid image file');
        return;
    }

    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($imgInfo['mime'], $allowedMimes)) {
        jsonResponse(false, null, 'Unsupported image type');
        return;
    }

    if (!extension_loaded('gd')) {
        jsonResponse(false, null, 'GD extension not available');
        return;
    }

    $imgType    = $imgInfo[2];
    $uploadsDir = dirname(__DIR__) . '/uploads/events/' . $eventId;
    if (!is_dir($uploadsDir)) {
        @mkdir($uploadsDir, 0755, true);
    }

    $filename = $prefix . uniqid() . '.jpg';
    $destPath = $uploadsDir . '/' . $filename;
    $relPath  = 'uploads/events/' . $eventId . '/' . $filename;

    if (!processAndSaveImage($file['tmp_name'], $destPath, $maxW, $maxH, $imgType, 'fit')) {
        jsonResponse(false, null, 'Failed to process image');
        return;
    }

    try {
        $oldCover = $existingEvent[$dbCol] ?? '';
        if ($oldCover) {
            $oldPath = dirname(__DIR__) . '/' . $oldCover;
            if (file_exists($oldPath)) @unlink($oldPath);
        }

        $upd = $db->prepare("UPDATE events SET {$dbCol} = :path WHERE id = :id");
        $upd->execute([':path' => $relPath, ':id' => $eventId]);

        invalidate_query_cache($eventId);

        jsonResponse(true, ['path' => $relPath, 'cover_type' => $coverType], 'Cover image uploaded');
    } catch (PDOException $e) {
        @unlink($destPath);
        jsonResponse(false, null, 'Database error: ' . $e->getMessage());
    }
}

/**
 * Delete a cover image for an event.
 * POST JSON: { event_id, cover_type }  cover_type = hero|card
 */
function deleteEventCover() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST method required');
        return;
    }

    $input     = json_decode(file_get_contents('php://input'), true);
    $eventId   = intval($input['event_id'] ?? 0);
    $coverType = in_array($input['cover_type'] ?? '', ['hero', 'card']) ? $input['cover_type'] : 'hero';

    if ($eventId <= 0) {
        jsonResponse(false, null, 'Valid event_id required');
        return;
    }
    if (isOrganizerRequest()) {
        requireCanManageEventId($eventId);
    }

    $dbCol = $coverType === 'card' ? 'cover_image_card' : 'cover_image';

    $chk = $db->prepare("SELECT id, cover_image, cover_image_card FROM events WHERE id = :id");
    $chk->execute([':id' => $eventId]);
    $row = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        jsonResponse(false, null, 'Event not found');
        return;
    }

    $oldCover = $row[$dbCol] ?? '';
    if ($oldCover) {
        $oldPath = dirname(__DIR__) . '/' . $oldCover;
        if (file_exists($oldPath)) @unlink($oldPath);
    }

    try {
        $upd = $db->prepare("UPDATE events SET {$dbCol} = NULL WHERE id = :id");
        $upd->execute([':id' => $eventId]);

        invalidate_query_cache($eventId);

        audit_admin_success('event_cover_delete', 'event', $eventId, null, ['cover_type' => $coverType]);
        jsonResponse(true, null, 'Cover image deleted');
    } catch (PDOException $e) {
        audit_admin_failure('event_cover_delete', 'database_error', 'event', $eventId, null);
        jsonResponse(false, null, 'Database error: ' . $e->getMessage());
    }
}

// =============================================================================
// EVENT HEADER COVER IMAGE (Banner 4:1, 1920×480)
// =============================================================================

/**
 * Upload a header cover image for an event (pre-cropped 4:1 by Cropper.js on client).
 * POST multipart/form-data: file "cover", GET param event_id
 * Stores to uploads/events/{event_id}/hdr_{uniqid}.jpg → events.header_cover_image
 */
function uploadEventHeaderCover() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST method required');
        return;
    }

    $eventId = intval($_GET['event_id'] ?? 0);
    if ($eventId <= 0) {
        jsonResponse(false, null, 'Valid event_id required');
        return;
    }
    if (isOrganizerRequest()) {
        requireCanManageEventId($eventId);
    }

    $chk = $db->prepare("SELECT id, header_cover_image FROM events WHERE id = :id");
    $chk->execute([':id' => $eventId]);
    $existingEvent = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$existingEvent) {
        jsonResponse(false, null, 'Event not found');
        return;
    }

    if (!isset($_FILES['cover']) || $_FILES['cover']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(false, null, 'File upload error');
        return;
    }

    $file = $_FILES['cover'];
    if ($file['size'] > 5 * 1024 * 1024) {
        jsonResponse(false, null, 'File exceeds 5 MB limit');
        return;
    }

    $imgInfo = @getimagesize($file['tmp_name']);
    if (!$imgInfo) {
        jsonResponse(false, null, 'Invalid image file');
        return;
    }

    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($imgInfo['mime'], $allowedMimes)) {
        jsonResponse(false, null, 'Unsupported image type');
        return;
    }

    if (!extension_loaded('gd')) {
        jsonResponse(false, null, 'GD extension not available');
        return;
    }

    $imgType    = $imgInfo[2];
    $uploadsDir = dirname(__DIR__) . '/uploads/events/' . $eventId;
    if (!is_dir($uploadsDir)) {
        @mkdir($uploadsDir, 0755, true);
    }

    $filename = 'hdr_' . uniqid() . '.jpg';
    $destPath = $uploadsDir . '/' . $filename;
    $relPath  = 'uploads/events/' . $eventId . '/' . $filename;

    if (!processAndSaveImage($file['tmp_name'], $destPath, 1920, 480, $imgType, 'fit')) {
        jsonResponse(false, null, 'Failed to process image');
        return;
    }

    try {
        $oldCover = $existingEvent['header_cover_image'] ?? '';
        if ($oldCover) {
            $oldPath = dirname(__DIR__) . '/' . $oldCover;
            if (file_exists($oldPath)) @unlink($oldPath);
        }

        $upd = $db->prepare("UPDATE events SET header_cover_image = :path WHERE id = :id");
        $upd->execute([':path' => $relPath, ':id' => $eventId]);

        invalidate_query_cache($eventId);

        jsonResponse(true, ['path' => $relPath], 'Header cover uploaded');
    } catch (PDOException $e) {
        @unlink($destPath);
        jsonResponse(false, null, 'Database error: ' . $e->getMessage());
    }
}

/**
 * Delete a header cover image for an event.
 * POST JSON: { event_id }
 */
function deleteEventHeaderCover() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST method required');
        return;
    }

    $input   = json_decode(file_get_contents('php://input'), true);
    $eventId = intval($input['event_id'] ?? 0);
    if ($eventId <= 0) {
        jsonResponse(false, null, 'Valid event_id required');
        return;
    }
    if (isOrganizerRequest()) {
        requireCanManageEventId($eventId);
    }

    $chk = $db->prepare("SELECT id, header_cover_image FROM events WHERE id = :id");
    $chk->execute([':id' => $eventId]);
    $row = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        jsonResponse(false, null, 'Event not found');
        return;
    }

    $oldCover = $row['header_cover_image'] ?? '';
    if ($oldCover) {
        $oldPath = dirname(__DIR__) . '/' . $oldCover;
        if (file_exists($oldPath)) @unlink($oldPath);
    }

    try {
        $upd = $db->prepare("UPDATE events SET header_cover_image = NULL WHERE id = :id");
        $upd->execute([':id' => $eventId]);

        invalidate_query_cache($eventId);

        audit_admin_success('event_header_cover_delete', 'event', $eventId, null);
        jsonResponse(true, null, 'Header cover deleted');
    } catch (PDOException $e) {
        audit_admin_failure('event_header_cover_delete', 'database_error', 'event', $eventId, null);
        jsonResponse(false, null, 'Database error: ' . $e->getMessage());
    }
}

/**
 * Delete display_picture or cover_picture for an artist
 * POST JSON: { artist_id, picture_type }
 */
function deleteArtistPicture() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST method required');
        return;
    }

    $input       = json_decode(file_get_contents('php://input'), true);
    $artistId    = intval($input['artist_id'] ?? 0);
    $pictureType = trim($input['picture_type'] ?? '');

    if ($artistId <= 0) {
        jsonResponse(false, null, 'Valid artist_id required');
        return;
    }
    if (!in_array($pictureType, ['display', 'cover'])) {
        jsonResponse(false, null, 'picture_type must be "display" or "cover"');
        return;
    }

    $column = ($pictureType === 'display') ? 'display_picture' : 'cover_picture';

    try {
        $stmtGet = $db->prepare("SELECT $column FROM artists WHERE id = :id");
        $stmtGet->execute([':id' => $artistId]);
        $filePath = $stmtGet->fetchColumn();

        $stmt = $db->prepare("UPDATE artists SET $column = NULL, updated_at = :now WHERE id = :id");
        $stmt->execute([':now' => date('Y-m-d H:i:s'), ':id' => $artistId]);

        if ($stmt->rowCount() === 0) {
            jsonResponse(false, null, 'Artist not found');
            return;
        }

        // Delete physical file
        if ($filePath) {
            $fullPath = dirname(__DIR__) . '/' . $filePath;
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }

        invalidate_artist_query_cache();
        invalidate_query_cache();
        audit_admin_success('artist_picture_delete', 'artist', $artistId, null, ['picture_type' => $pictureType]);
        jsonResponse(true, null, 'Picture deleted successfully');
    } catch (PDOException $e) {
        audit_admin_failure('artist_picture_delete', 'database_error', 'artist', $artistId, null);
        jsonResponse(false, null, safe_error_message('Failed to delete picture', $e->getMessage()));
    }
}

// ============================================================
// Event Pictures
// ============================================================

/**
 * List pictures for an event
 * GET ?action=event_pictures_list&event_id=N
 */
function listEventPictures() {
    global $db;

    $eventId = intval($_GET['event_id'] ?? 0);
    if ($eventId <= 0) {
        jsonResponse(false, null, 'Valid event_id required');
        return;
    }
    if (isOrganizerRequest()) {
        requireCanManageEventId($eventId);
    }

    try {
        $stmt = $db->prepare(
            "SELECT id, filename, caption, display_order FROM event_pictures
             WHERE event_id = :eid ORDER BY display_order ASC, id ASC"
        );
        $stmt->execute([':eid' => $eventId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            if ($r['caption'] !== null) {
                $r['caption'] = htmlspecialchars($r['caption'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
        }
        unset($r);

        jsonResponse(true, ['pictures' => $rows]);
    } catch (PDOException $e) {
        jsonResponse(false, null, safe_error_message('Failed to list pictures', $e->getMessage()));
    }
}

/**
 * Upload a picture for an event
 * POST multipart/form-data with file field "picture", GET param event_id
 */
function uploadEventPicture() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST method required');
        return;
    }

    $eventId = intval($_GET['event_id'] ?? 0);
    if ($eventId <= 0) {
        jsonResponse(false, null, 'Valid event_id required');
        return;
    }
    if (isOrganizerRequest()) {
        requireCanManageEventId($eventId);
    }

    // Verify event exists
    $chk = $db->prepare("SELECT id FROM events WHERE id = :id");
    $chk->execute([':id' => $eventId]);
    if (!$chk->fetch()) {
        jsonResponse(false, null, 'Event not found');
        return;
    }

    if (!isset($_FILES['picture']) || $_FILES['picture']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(false, null, 'File upload error');
        return;
    }

    $file = $_FILES['picture'];

    if ($file['size'] > 5 * 1024 * 1024) {
        jsonResponse(false, null, 'File exceeds 5 MB limit');
        return;
    }

    $imgInfo = @getimagesize($file['tmp_name']);
    if (!$imgInfo) {
        jsonResponse(false, null, 'Invalid image file');
        return;
    }

    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($imgInfo['mime'], $allowedMimes)) {
        jsonResponse(false, null, 'Unsupported image type');
        return;
    }

    if (!extension_loaded('gd')) {
        jsonResponse(false, null, 'GD extension not available');
        return;
    }

    $imgType    = $imgInfo[2];
    $uploadsDir = dirname(__DIR__) . '/uploads/events/' . $eventId;
    if (!is_dir($uploadsDir)) {
        @mkdir($uploadsDir, 0755, true);
    }

    $filename = uniqid() . '.jpg';
    $destPath = $uploadsDir . '/' . $filename;
    $relPath  = 'uploads/events/' . $eventId . '/' . $filename;

    if (!processAndSaveImage($file['tmp_name'], $destPath, 1200, 900, $imgType, 'fit')) {
        jsonResponse(false, null, 'Failed to process image');
        return;
    }

    try {
        // Auto-increment display_order
        $maxOrd = $db->prepare("SELECT COALESCE(MAX(display_order), 0) FROM event_pictures WHERE event_id = :eid");
        $maxOrd->execute([':eid' => $eventId]);
        $nextOrder = intval($maxOrd->fetchColumn()) + 1;

        $ins = $db->prepare(
            "INSERT INTO event_pictures (event_id, filename, caption, display_order, created_at)
             VALUES (:eid, :fn, NULL, :ord, :now)"
        );
        $ins->execute([
            ':eid' => $eventId,
            ':fn'  => $relPath,
            ':ord' => $nextOrder,
            ':now' => date('Y-m-d H:i:s'),
        ]);

        invalidate_query_cache($eventId);
        jsonResponse(true, ['id' => $db->lastInsertId(), 'filename' => $relPath, 'caption' => null]);
    } catch (PDOException $e) {
        @unlink($destPath);
        jsonResponse(false, null, safe_error_message('Failed to save picture', $e->getMessage()));
    }
}

/**
 * Delete a picture from an event
 * POST JSON: { id, event_id }
 */
function deleteEventPicture() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST method required');
        return;
    }

    $input   = json_decode(file_get_contents('php://input'), true);
    $picId   = intval($input['id'] ?? 0);
    $eventId = intval($input['event_id'] ?? 0);

    if ($picId <= 0 || $eventId <= 0) {
        jsonResponse(false, null, 'Valid id and event_id required');
        return;
    }
    if (isOrganizerRequest()) {
        requireCanManageEventId($eventId);
    }

    try {
        $stmtGet = $db->prepare("SELECT filename FROM event_pictures WHERE id = :id AND event_id = :eid");
        $stmtGet->execute([':id' => $picId, ':eid' => $eventId]);
        $row = $stmtGet->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            jsonResponse(false, null, 'Picture not found');
            return;
        }

        $del = $db->prepare("DELETE FROM event_pictures WHERE id = :id AND event_id = :eid");
        $del->execute([':id' => $picId, ':eid' => $eventId]);

        // Delete physical file
        $fullPath = dirname(__DIR__) . '/' . $row['filename'];
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }

        invalidate_query_cache($eventId);
        audit_admin_success('event_picture_delete', 'event', $eventId, null, ['picture_id' => $picId]);
        jsonResponse(true, null, 'Picture deleted');
    } catch (PDOException $e) {
        audit_admin_failure('event_picture_delete', 'database_error', 'event', $eventId, null);
        jsonResponse(false, null, safe_error_message('Failed to delete picture', $e->getMessage()));
    }
}

/**
 * Reorder pictures for an event
 * POST JSON: { event_id, order: [id1, id2, ...] }
 */
function reorderEventPictures() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST method required');
        return;
    }

    $input   = json_decode(file_get_contents('php://input'), true);
    $eventId = intval($input['event_id'] ?? 0);
    $order   = $input['order'] ?? [];

    if ($eventId <= 0 || !is_array($order) || empty($order)) {
        jsonResponse(false, null, 'Valid event_id and order array required');
        return;
    }
    if (isOrganizerRequest()) {
        requireCanManageEventId($eventId);
    }

    // Validate all IDs belong to this event
    $placeholders = implode(',', array_fill(0, count($order), '?'));
    $stmtCheck = $db->prepare(
        "SELECT COUNT(*) FROM event_pictures WHERE id IN ($placeholders) AND event_id = ?"
    );
    $stmtCheck->execute(array_merge(array_map('intval', $order), [$eventId]));
    if (intval($stmtCheck->fetchColumn()) !== count($order)) {
        jsonResponse(false, null, 'Invalid picture IDs for this event');
        return;
    }

    try {
        $db->beginTransaction();
        $upd = $db->prepare("UPDATE event_pictures SET display_order = :ord WHERE id = :id AND event_id = :eid");
        foreach ($order as $idx => $picId) {
            $upd->execute([':ord' => $idx + 1, ':id' => intval($picId), ':eid' => $eventId]);
        }
        $db->commit();

        invalidate_query_cache($eventId);
        jsonResponse(true, null, 'Order updated');
    } catch (PDOException $e) {
        $db->rollBack();
        jsonResponse(false, null, safe_error_message('Failed to reorder pictures', $e->getMessage()));
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
