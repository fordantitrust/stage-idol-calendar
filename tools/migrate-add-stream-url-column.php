<?php
/**
 * Migration: Add stream_url column to programs table
 *
 * Adds stream_url TEXT DEFAULT NULL for IG Live / X Spaces / YouTube Live links.
 * Idempotent — safe to run multiple times.
 *
 * Usage:
 *   php tools/migrate-add-stream-url-column.php
 */

$dbPath = dirname(__DIR__) . '/data/calendar.db';

if (!file_exists($dbPath)) {
    echo "ERROR: Database not found at $dbPath\n";
    exit(1);
}

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if column already exists
    $cols = $db->query('PRAGMA table_info(programs)')->fetchAll(PDO::FETCH_COLUMN, 1);

    if (in_array('stream_url', $cols)) {
        echo "OK: stream_url column already exists — skipping.\n";
        exit(0);
    }

    $db->exec('ALTER TABLE programs ADD COLUMN stream_url TEXT DEFAULT NULL');
    echo "OK: stream_url column added to programs table.\n";

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
