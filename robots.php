<?php
require_once __DIR__ . '/config.php';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $protocol . '://' . $host . get_base_path();

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=86400');
?>
User-agent: *
Disallow: /my/
Disallow: /my-favorites/

Sitemap: <?= $baseUrl ?>/sitemap.xml
