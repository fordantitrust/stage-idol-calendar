<?php
/**
 * Favorites API
 * Idol Stage Timetable
 *
 * POST ?action=create              → create new token (rate limited per IP)
 * GET  ?action=get&slug=X          → get artist IDs + optional details (&details=1)
 * POST ?action=add&slug=X          → add artist  (body: {"artist_id": N})
 * POST ?action=remove&slug=X       → remove artist (body: {"artist_id": N})
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions/favorites.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (FAVORITES_HMAC_SECRET === 'REPLACE_WITH_GENERATED_SECRET') {
    http_response_code(503);
    echo json_encode(['error' => 'Favorites not configured. Run: php tools/generate-favorites-secret.php']);
    exit;
}

$action = $_GET['action'] ?? '';
$ip     = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// ─── Create ───────────────────────────────────────────────────────────────────
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!fav_check_rate_limit($ip)) {
        http_response_code(429);
        echo json_encode(['error' => 'Rate limit exceeded. Max ' . FAVORITES_RATE_LIMIT . ' new favorites per ' . (FAVORITES_RATE_WINDOW / 3600) . ' hours.']);
        exit;
    }

    $token = fav_generate_uuid_v7();
    $slug  = fav_build_slug($token);
    $data  = [
        'token'       => $token,
        'artists'     => [],
        'created_at'  => date('c'),
        'last_access' => date('c'),
    ];

    if (!fav_write($data)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create favorites.']);
        exit;
    }

    $base = defined('BASE_PATH') ? rtrim(BASE_PATH, '/') : get_base_path();
    echo json_encode([
        'slug' => $slug,
        'url'  => $base . '/favorites/' . $slug,
    ]);
    exit;
}

// ─── Validate slug ────────────────────────────────────────────────────────────
$slugParam = $_GET['slug'] ?? ($_POST['slug'] ?? '');
$parsed    = $slugParam ? fav_parse_slug($slugParam) : null;

if (!$parsed) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or expired favorites token.']);
    exit;
}

$token = $parsed['token'];

// ─── Get ──────────────────────────────────────────────────────────────────────
if ($action === 'get' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = fav_read($token);
    if (!$data) {
        http_response_code(404);
        echo json_encode(['error' => 'Favorites not found or expired.']);
        exit;
    }

    fav_touch($data);
    fav_maybe_cleanup(200);

    $response = ['artists' => $data['artists']];

    // Include artist details when ?details=1
    if (!empty($_GET['details']) && !empty($data['artists'])) {
        try {
            $db   = new PDO('sqlite:' . DB_PATH);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pls  = implode(',', array_fill(0, count($data['artists']), '?'));
            $stmt = $db->prepare("
                SELECT a.id, a.name, a.is_group, a.group_id, g.name AS group_name
                FROM artists a
                LEFT JOIN artists g ON g.id = a.group_id
                WHERE a.id IN ($pls)
                ORDER BY a.name
            ");
            $stmt->execute($data['artists']);
            $response['details'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->closeCursor(); $stmt = null; $db = null;
        } catch (PDOException $e) {
            $response['details'] = [];
        }
    }

    echo json_encode($response);
    exit;
}

// ─── Add / Remove ─────────────────────────────────────────────────────────────
if (in_array($action, ['add', 'remove'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $artistId = isset($body['artist_id']) ? (int)$body['artist_id'] : 0;

    if ($artistId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid artist_id.']);
        exit;
    }

    $data = fav_read($token);
    if (!$data) {
        http_response_code(404);
        echo json_encode(['error' => 'Favorites not found or expired.']);
        exit;
    }

    if ($action === 'add') {
        if (!in_array($artistId, $data['artists'], true)) {
            if (count($data['artists']) >= FAVORITES_MAX_ARTISTS) {
                http_response_code(422);
                echo json_encode(['error' => 'Maximum ' . FAVORITES_MAX_ARTISTS . ' artists reached.']);
                exit;
            }
            $data['artists'][] = $artistId;
        }
    } else {
        $data['artists'] = array_values(array_filter($data['artists'], fn($id) => $id !== $artistId));
    }

    $data['last_access'] = date('c');
    fav_write($data);
    echo json_encode(['artists' => $data['artists']]);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Unknown action.']);
