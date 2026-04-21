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

    // Fixed-size slots need explicit width/height in style; responsive uses auto format.
    $slotConfig = [
        'leaderboard' => ['id' => GOOGLE_ADS_SLOT_LEADERBOARD, 'style' => 'display:inline-block;width:728px;height:90px', 'extra' => ''],
        'rectangle'   => ['id' => GOOGLE_ADS_SLOT_RECTANGLE,   'style' => 'display:inline-block;width:300px;height:250px', 'extra' => ''],
        'responsive'  => ['id' => GOOGLE_ADS_SLOT_RESPONSIVE,  'style' => 'display:block', 'extra' => ' data-ad-format="auto" data-full-width-responsive="true"'],
    ];

    $cfg = $slotConfig[$slot] ?? null;
    if ($cfg === null || $cfg['id'] === '') return;

    $wrapClass = 'ads-unit ads-' . $slot . ($class ? ' ' . $class : '');
    echo '<div class="' . htmlspecialchars($wrapClass, ENT_QUOTES, 'UTF-8') . '">';
    echo '<ins class="adsbygoogle"';
    echo ' style="' . $cfg['style'] . '"';
    echo ' data-ad-client="' . htmlspecialchars(GOOGLE_ADS_CLIENT, ENT_QUOTES, 'UTF-8') . '"';
    echo ' data-ad-slot="' . htmlspecialchars($cfg['id'], ENT_QUOTES, 'UTF-8') . '"';
    echo $cfg['extra'] . '></ins>';
    echo '<script>(adsbygoogle = window.adsbygoogle || []).push({});</script>';
    echo '</div>';
}
