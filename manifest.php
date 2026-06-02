<?php
require_once __DIR__ . '/config.php';
$base = rtrim(get_base_path(), '/');

header('Content-Type: application/manifest+json');
header('Cache-Control: no-cache');

echo json_encode([
    'name'             => get_site_title(),
    'short_name'       => 'Idol Track',
    'description'      => 'ตารางกิจกรรม Idol Stage — ติดตามศิลปินและรับแจ้งเตือนก่อนโปรแกรมเริ่ม',
    'start_url'        => $base . '/',
    'display'          => 'standalone',
    'background_color' => '#fff0f5',
    'theme_color'      => '#E91E63',
    'lang'             => 'th',
    'orientation'      => 'any',
    'categories'       => ['entertainment', 'lifestyle'],
    'icons'            => [
        [
            'src'     => $base . '/icon/icon-72.png',
            'sizes'   => '72x72',
            'type'    => 'image/png',
            'purpose' => 'badge',
        ],
        [
            'src'   => $base . '/icon/icon-192.png',
            'sizes' => '192x192',
            'type'  => 'image/png',
        ],
        [
            'src'     => $base . '/icon/icon-512.png',
            'sizes'   => '512x512',
            'type'    => 'image/png',
            'purpose' => 'maskable any',
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
