<?php
/**
 * Migration: Add timezone column to events table
 * Idol Stage Timetable
 *
 * Idempotent — safe to run multiple times.
 * Adds: timezone TEXT DEFAULT 'Asia/Bangkok' to events table.
 */

$dbPath = __DIR__ . '/../data/calendar.db';

if (!file_exists($dbPath)) {
    echo "❌ Database not found: $dbPath\n";
    exit(1);
}

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check existing columns
    $cols = $db->query("PRAGMA table_info(events)")->fetchAll(PDO::FETCH_COLUMN, 1);

    if (in_array('timezone', $cols)) {
        echo "ℹ️  timezone column already exists in events table — nothing to do.\n";
        exit(0);
    }

    $db->exec("ALTER TABLE events ADD COLUMN timezone TEXT DEFAULT 'Asia/Bangkok'");
    echo "✅ Added timezone column to events table (default: 'Asia/Bangkok').\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
