<?php
/**
 * Web Push Subscription API
 * Idol Stage Timetable v15.0.0
 *
 * POST ?action=subscribe   body: {slug, subscription:{endpoint,keys:{p256dh,auth}}, lang}
 * POST ?action=unsubscribe body: {slug, endpoint}
 * GET  ?action=status      ?slug=...&endpoint_hash=...
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (!defined('WEBPUSH_VAPID_PUBLIC_KEY')) {
    http_response_code(503);
    echo json_encode(['error' => 'Web Push not configured.']);
    exit;
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$method = $_SERVER['REQUEST_METHOD'];

// Parse JSON body for POST requests
$body = [];
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $body = @json_decode($raw, true) ?: [];
    }
}

// ── Subscribe ─────────────────────────────────────────────────────────────────
if ($action === 'subscribe' && $method === 'POST') {
    $slug = $body['slug'] ?? '';
    $sub  = $body['subscription'] ?? null;
    $lang = $body['lang'] ?? 'th';
    $tz   = isset($body['tz']) ? trim((string)$body['tz']) : '';
    $tz   = is_valid_timezone($tz) ? $tz : null;

    if (!$slug) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing slug.']);
        exit;
    }
    if (!$sub || empty($sub['endpoint']) || empty($sub['keys']['p256dh']) || empty($sub['keys']['auth'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid subscription object.']);
        exit;
    }

    $parsed = fav_parse_slug($slug);
    if (!$parsed) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or expired favorites token.']);
        exit;
    }

    $data = fav_read($parsed['token']);
    if (!$data) {
        http_response_code(404);
        echo json_encode(['error' => 'Favorites not found.']);
        exit;
    }

    // Sanitize + allow-list endpoint (SSRF defense)
    $endpoint = webpush_validate_endpoint((string)$sub['endpoint']);
    if ($endpoint === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Endpoint host not allowed. Only known push services are accepted.']);
        exit;
    }
    $endpointHash = substr(hash('sha256', $endpoint), 0, 16);

    $cleanSub = [
        'endpoint'       => $endpoint,
        'keys'           => [
            'p256dh' => preg_replace('/[^A-Za-z0-9+\/=_-]/', '', $sub['keys']['p256dh']),
            'auth'   => preg_replace('/[^A-Za-z0-9+\/=_-]/', '', $sub['keys']['auth']),
        ],
        'lang'           => in_array($lang, ['th', 'en', 'ja']) ? $lang : 'th',
        'tz'             => $tz,
        'created_at'     => time(),
        'endpoint_hash'  => $endpointHash,
        'notified'       => [],
    ];

    $subs = $data['push_subscriptions'] ?? [];

    // Check for duplicate endpoint — refresh tz + lang so a device that changed
    // its timezone (e.g. the user travelled) keeps its notifications localized.
    foreach ($subs as $i => $existing) {
        if (($existing['endpoint'] ?? '') === $endpoint) {
            $subs[$i]['lang'] = $cleanSub['lang'];
            $subs[$i]['tz']   = $tz;
            $data['push_subscriptions'] = $subs;
            fav_touch($data);
            fav_write($data);
            echo json_encode(['success' => true, 'message' => 'Already subscribed.', 'endpoint_hash' => $endpointHash]);
            exit;
        }
    }

    // Add new subscription; enforce max limit (remove oldest if exceeded)
    $maxSubs = defined('WEBPUSH_MAX_SUBS_PER_TOKEN') ? WEBPUSH_MAX_SUBS_PER_TOKEN : 5;
    $subs[] = $cleanSub;
    while (count($subs) > $maxSubs) {
        array_shift($subs);
    }

    $data['push_subscriptions'] = $subs;
    fav_touch($data);

    if (!fav_write($data)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save subscription.']);
        exit;
    }

    echo json_encode(['success' => true, 'endpoint_hash' => $endpointHash]);
    exit;
}

// ── Unsubscribe ───────────────────────────────────────────────────────────────
if ($action === 'unsubscribe' && $method === 'POST') {
    $slug     = $body['slug'] ?? '';
    $endpoint = $body['endpoint'] ?? '';

    if (!$slug) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing slug.']);
        exit;
    }
    if (!$endpoint) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing endpoint.']);
        exit;
    }

    $parsed = fav_parse_slug($slug);
    if (!$parsed) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or expired favorites token.']);
        exit;
    }

    $data = fav_read($parsed['token']);
    if (!$data) {
        http_response_code(404);
        echo json_encode(['error' => 'Favorites not found.']);
        exit;
    }

    $subs = $data['push_subscriptions'] ?? [];
    $filtered = array_values(array_filter($subs, function($s) use ($endpoint) {
        return ($s['endpoint'] ?? '') !== $endpoint;
    }));

    $data['push_subscriptions'] = $filtered;
    fav_touch($data);

    if (!fav_write($data)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update subscription.']);
        exit;
    }

    echo json_encode(['success' => true]);
    exit;
}

// ── Status ────────────────────────────────────────────────────────────────────
if ($action === 'status' && $method === 'GET') {
    $slug         = $_GET['slug'] ?? '';
    $endpointHash = $_GET['endpoint_hash'] ?? '';

    if (!$slug) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing slug.']);
        exit;
    }

    $parsed = fav_parse_slug($slug);
    if (!$parsed) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid token.']);
        exit;
    }

    $data = fav_read($parsed['token']);
    if (!$data) {
        http_response_code(404);
        echo json_encode(['error' => 'Not found.']);
        exit;
    }

    $subs = $data['push_subscriptions'] ?? [];
    $subscribed = false;
    if ($endpointHash) {
        foreach ($subs as $s) {
            if (($s['endpoint_hash'] ?? '') === $endpointHash) {
                $subscribed = true;
                break;
            }
        }
    }

    echo json_encode([
        'subscribed'        => $subscribed,
        'subscription_count' => count($subs),
        'enabled'           => WEBPUSH_ENABLED,
    ]);
    exit;
}

// ── Unknown action ────────────────────────────────────────────────────────────
http_response_code(400);
echo json_encode(['error' => 'Unknown action.']);
