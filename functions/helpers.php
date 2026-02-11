<?php
/**
 * Helper Functions
 * Idol Stage Timetable v1.0.0
 */

/**
 * Generate asset URL with cache busting
 */
function asset_url($path) {
    return $path . '?v=' . APP_VERSION;
}
