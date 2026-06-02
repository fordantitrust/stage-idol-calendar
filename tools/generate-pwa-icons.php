<?php
/**
 * Generate PWA Icons — Sakura Theme
 *
 * Creates 3 PNG icons for the Web App Manifest using PHP GD:
 *   icon/icon-72.png   (72×72  — badge/notification icon)
 *   icon/icon-192.png  (192×192 — standard PWA icon)
 *   icon/icon-512.png  (512×512 — high-res, maskable)
 *
 * Usage:
 *   php tools/generate-pwa-icons.php
 */

$projectRoot = dirname(__DIR__);
$iconsDir    = $projectRoot . '/icon';

if (!is_dir($iconsDir)) {
    mkdir($iconsDir, 0755, true);
    echo "Created directory: icon/\n";
}

if (!extension_loaded('gd')) {
    echo "ERROR: GD extension is not available.\n";
    exit(1);
}

$sizes = [72, 192, 512];

foreach ($sizes as $size) {
    $img = imagecreatetruecolor($size, $size);
    imagealphablending($img, true);
    imagesavealpha($img, true);

    // Transparent background
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);

    // Draw sakura gradient circle background
    // Gradient: #FFB7C5 (center) → #E91E63 (edge)
    $cx = $size / 2;
    $cy = $size / 2;
    $radius = $size / 2;

    // Draw filled gradient circle using concentric arcs
    $steps = (int)($radius);
    for ($r = $steps; $r >= 0; $r--) {
        $ratio = ($steps > 0) ? ($r / $steps) : 0;
        // Center color: #FFB7C5 = 255, 183, 197
        // Edge color:   #E91E63 = 233, 30, 99
        $red   = (int)(233 + ($ratio * (255 - 233)));
        $green = (int)(30  + ($ratio * (183 - 30)));
        $blue  = (int)(99  + ($ratio * (197 - 99)));
        $col   = imagecolorallocate($img, $red, $green, $blue);
        imagefilledellipse($img, (int)$cx, (int)$cy, $r * 2, $r * 2, $col);
    }

    // Add subtle petal pattern for larger icons
    if ($size >= 192) {
        // Draw 5 small petal suggestions as white semi-transparent arcs
        $petals = 5;
        for ($p = 0; $p < $petals; $p++) {
            $angle = ($p / $petals) * 2 * M_PI;
            $petalX = (int)($cx + cos($angle) * $radius * 0.45);
            $petalY = (int)($cy + sin($angle) * $radius * 0.45);
            $petalR = (int)($radius * 0.18);
            $white  = imagecolorallocatealpha($img, 255, 255, 255, 80);
            imagefilledellipse($img, $petalX, $petalY, $petalR * 2, $petalR * 2, $white);
        }
    }

    // Add "IS" text in white
    $textColor = imagecolorallocate($img, 255, 255, 255);
    $fontSize  = (int)($size * 0.30);
    $font      = 5; // GD built-in font

    if ($size >= 192) {
        // Use large built-in font
        $charW = imagefontwidth(5);
        $charH = imagefontheight(5);
        $text  = 'IS';
        $textW = strlen($text) * $charW;
        $textH = $charH;
        $tx    = (int)($cx - $textW / 2);
        $ty    = (int)($cy - $textH / 2);
        imagestring($img, 5, $tx, $ty, $text, $textColor);
    } else {
        // Use smaller font for 72px
        $charW = imagefontwidth(4);
        $charH = imagefontheight(4);
        $text  = 'IS';
        $textW = strlen($text) * $charW;
        $tx    = (int)($cx - $textW / 2);
        $ty    = (int)($cy - $charH / 2);
        imagestring($img, 4, $tx, $ty, $text, $textColor);
    }

    $outPath = $iconsDir . '/icon-' . $size . '.png';
    imagepng($img, $outPath);
    imagedestroy($img);
    echo "Generated: icon/icon-{$size}.png\n";
}

echo "\nDone! PWA icons created in icon/\n";
