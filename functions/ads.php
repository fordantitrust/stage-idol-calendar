<?php
/**
 * Google AdSense Helper
 * Idol Stage Timetable
 */

/**
 * Render a Google AdSense ad unit.
 * Does nothing if GOOGLE_ADS_CLIENT is empty or slot is empty.
 *
 * @param string $slot  'leaderboard' | 'rectangle' | 'responsive'
 * @param string $class Optional extra CSS class on wrapper div
 */
function render_ad_unit(string $slot, string $class = ''): void {
    if (!defined('GOOGLE_ADS_CLIENT') || GOOGLE_ADS_CLIENT === '') return;

    $slotMap = [
        'leaderboard' => GOOGLE_ADS_SLOT_LEADERBOARD,
        'rectangle'   => GOOGLE_ADS_SLOT_RECTANGLE,
        'responsive'  => GOOGLE_ADS_SLOT_RESPONSIVE,
    ];

    $slotId = $slotMap[$slot] ?? '';
    if ($slotId === '') return;

    $wrapClass = 'ads-unit ads-' . $slot . ($class ? ' ' . $class : '');
    echo '<div class="' . htmlspecialchars($wrapClass, ENT_QUOTES, 'UTF-8') . '">';
    echo '<ins class="adsbygoogle"';
    echo ' style="display:block"';
    echo ' data-ad-client="' . htmlspecialchars(GOOGLE_ADS_CLIENT, ENT_QUOTES, 'UTF-8') . '"';
    echo ' data-ad-slot="' . htmlspecialchars($slotId, ENT_QUOTES, 'UTF-8') . '"';
    echo ' data-ad-format="auto"';
    echo ' data-full-width-responsive="true"></ins>';
    echo '<script>(adsbygoogle = window.adsbygoogle || []).push({});</script>';
    echo '</div>';
}
