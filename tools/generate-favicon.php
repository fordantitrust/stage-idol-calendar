<?php
/**
 * Generate favicon.ico — Sakura Theme
 *
 * Builds a multi-size favicon.ico (16×16, 32×32, 48×48) at the project root by
 * downscaling the existing PWA icon. Each entry is stored as a PNG stream
 * (PNG-compressed ICO entries are supported by all modern browsers), so we don't
 * need an external .ico encoder.
 *
 * Source priority: icon/icon-512.png → icon/icon-192.png
 * (run tools/generate-pwa-icons.php first if those don't exist yet)
 *
 * Usage:
 *   php tools/generate-favicon.php
 */

$projectRoot = dirname(__DIR__);
$out         = $projectRoot . '/favicon.ico';

if (!extension_loaded('gd')) {
    echo "ERROR: GD extension is not available.\n";
    exit(1);
}

$src = $projectRoot . '/icon/icon-512.png';
if (!is_file($src)) {
    $src = $projectRoot . '/icon/icon-192.png';
}
if (!is_file($src)) {
    echo "ERROR: source icon not found — run `php tools/generate-pwa-icons.php` first.\n";
    exit(1);
}

$source = @imagecreatefrompng($src);
if (!$source) {
    echo "ERROR: cannot read source icon: {$src}\n";
    exit(1);
}
$srcW = imagesx($source);
$srcH = imagesy($source);

$sizes = [16, 32, 48];
$pngs  = [];

foreach ($sizes as $s) {
    $img = imagecreatetruecolor($s, $s);
    imagealphablending($img, false);
    imagesavealpha($img, true);
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);
    imagecopyresampled($img, $source, 0, 0, 0, 0, $s, $s, $srcW, $srcH);

    ob_start();
    imagepng($img, null, 9);
    $pngs[$s] = ob_get_clean();
    imagedestroy($img);
}
imagedestroy($source);

// ── Assemble the ICO container ───────────────────────────────────────────────
// ICONDIR header: reserved(0), type(1 = icon), image count
$count  = count($pngs);
$header = pack('vvv', 0, 1, $count);

$offset    = 6 + $count * 16; // header (6) + one 16-byte directory entry per image
$directory = '';
$payload   = '';

foreach ($pngs as $s => $png) {
    $len = strlen($png);
    // width/height byte is 0 when the dimension is 256
    $dim = ($s >= 256) ? 0 : $s;
    $directory .= pack(
        'CCCCvvVV',
        $dim,    // width
        $dim,    // height
        0,       // palette color count (0 = no palette)
        0,       // reserved
        1,       // color planes
        32,      // bits per pixel
        $len,    // size of the image data
        $offset  // offset of the image data from the start of the file
    );
    $payload .= $png;
    $offset  += $len;
}

if (file_put_contents($out, $header . $directory . $payload) === false) {
    echo "ERROR: failed to write {$out}\n";
    exit(1);
}

echo "Generated: favicon.ico (" . $count . " sizes: " . implode(', ', array_keys($pngs)) . ") from " . basename($src) . "\n";
