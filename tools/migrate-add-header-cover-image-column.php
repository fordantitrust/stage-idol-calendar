<?php
/**
 * Migration: Add header_cover_image column to events table
 * Idempotent — safe to run multiple times.
 */

require_once dirname(__DIR__) . '/config.php';

$db = new PDO('sqlite:' . DB_PATH);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$cols = $db->query("PRAGMA table_info(events)")->fetchAll(PDO::FETCH_ASSOC);
$colNames = array_column($cols, 'name');

if (!in_array('header_cover_image', $colNames)) {
    $db->exec("ALTER TABLE events ADD COLUMN header_cover_image TEXT DEFAULT NULL");
    echo "✅ Added header_cover_image column to events table.\n";
} else {
    echo "ℹ️  header_cover_image column already exists — skipped.\n";
}

echo "Done.\n";
