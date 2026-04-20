<?php
require_once __DIR__ . '/config.php';

// --- Serve from cache if fresh ---
$cacheFile = SITEMAP_CACHE_FILE;
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < SITEMAP_CACHE_TTL) {
    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    header('X-Robots-Tag: noindex');
    readfile($cacheFile);
    exit;
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $protocol . '://' . $host . get_base_path();
$today = date('Y-m-d');

$urls = [];

// --- Static pages ---
$staticPages = [
    ['path' => '/',           'priority' => '1.0', 'changefreq' => 'daily'],
    ['path' => '/artists',    'priority' => '0.8', 'changefreq' => 'weekly'],
    ['path' => '/how-to-use', 'priority' => '0.5', 'changefreq' => 'monthly'],
    ['path' => '/contact',    'priority' => '0.4', 'changefreq' => 'monthly'],
    ['path' => '/credits',    'priority' => '0.4', 'changefreq' => 'monthly'],
];
foreach ($staticPages as $page) {
    $urls[] = $page + ['loc' => $baseUrl . $page['path'], 'lastmod' => $today];
}

// --- Active events (multi-event mode) ---
if (defined('MULTI_EVENT_MODE') && MULTI_EVENT_MODE) {
    foreach (get_all_active_events() as $ev) {
        $slug = $ev['slug'];
        if ($slug === DEFAULT_EVENT_SLUG) {
            continue; // maps to root '/' — already included above
        }
        $lastmod = !empty($ev['updated_at']) ? substr($ev['updated_at'], 0, 10) : $today;
        $encoded = rawurlencode($slug);
        $urls[] = [
            'loc'        => $baseUrl . '/event/' . $encoded,
            'priority'   => '0.9',
            'changefreq' => 'daily',
            'lastmod'    => $lastmod,
        ];
        $urls[] = [
            'loc'        => $baseUrl . '/event/' . $encoded . '/credits',
            'priority'   => '0.3',
            'changefreq' => 'monthly',
            'lastmod'    => $lastmod,
        ];
    }
}

// --- Artist profile pages ---
try {
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $db->query("SELECT id, updated_at FROM artists ORDER BY id ASC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $lastmod = !empty($row['updated_at']) ? substr($row['updated_at'], 0, 10) : $today;
        $urls[] = [
            'loc'        => $baseUrl . '/artist/' . (int)$row['id'],
            'priority'   => '0.7',
            'changefreq' => 'weekly',
            'lastmod'    => $lastmod,
        ];
    }
    $stmt->closeCursor();
    $stmt = null;
    $db = null;
} catch (PDOException $e) {
    // DB unavailable — artist URLs omitted silently
}

// --- Build XML ---
ob_start();
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $url) {
    echo '  <url>' . "\n";
    echo '    <loc>' . htmlspecialchars($url['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>' . "\n";
    echo '    <lastmod>' . htmlspecialchars($url['lastmod']) . '</lastmod>' . "\n";
    echo '    <changefreq>' . $url['changefreq'] . '</changefreq>' . "\n";
    echo '    <priority>' . $url['priority'] . '</priority>' . "\n";
    echo '  </url>' . "\n";
}
echo '</urlset>' . "\n";
$xml = ob_get_clean();

// --- Save to cache ---
$cacheDir = dirname($cacheFile);
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}
file_put_contents($cacheFile, $xml, LOCK_EX);

// --- Output ---
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
header('X-Robots-Tag: noindex');
echo $xml;
