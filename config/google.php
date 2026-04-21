<?php
/**
 * Google Services Configuration
 * Idol Stage Timetable
 *
 * Loads configuration from config/google-config.json (editable via Admin UI)
 * Falls back to empty defaults if file doesn't exist.
 *
 * Kept separate from config/app.php so version update scripts
 * (tools/update-version.php) do not overwrite site-specific settings.
 */

// Default configuration
$defaultGoogleConfig = [
    'ga_id'               => '',
    'ads_client'          => '',
    'ads_slot_leaderboard'=> '',
    'ads_slot_rectangle'  => '',
    'ads_slot_responsive' => '',
];

// Load from JSON file (editable via Admin UI)
$googleConfigFile = __DIR__ . '/google-config.json';
$googleConfig = $defaultGoogleConfig;

if (file_exists($googleConfigFile)) {
    $jsonData = @json_decode(file_get_contents($googleConfigFile), true);
    if (is_array($jsonData)) {
        $googleConfig = array_merge($defaultGoogleConfig, $jsonData);
    }
}

// =============================================================================
// GOOGLE ANALYTICS
// =============================================================================

/**
 * Google Analytics Measurement ID
 * Set via Admin UI › Settings › Google, or directly in google-config.json.
 */
define('GOOGLE_ANALYTICS_ID', $googleConfig['ga_id'] ?? '');

// =============================================================================
// GOOGLE ADSENSE
// =============================================================================

/**
 * Google AdSense Publisher ID
 * Set via Admin UI › Settings › Google, or directly in google-config.json.
 */
define('GOOGLE_ADS_CLIENT',          $googleConfig['ads_client']           ?? '');
define('GOOGLE_ADS_SLOT_LEADERBOARD',$googleConfig['ads_slot_leaderboard'] ?? '');
define('GOOGLE_ADS_SLOT_RECTANGLE',  $googleConfig['ads_slot_rectangle']   ?? '');
define('GOOGLE_ADS_SLOT_RESPONSIVE', $googleConfig['ads_slot_responsive']  ?? '');
