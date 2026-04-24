<?php
/**
 * Migration: Create event_pictures table + Add gallery_template column to events
 * Idol Stage Timetable v6.6.0
 *
 * Idempotent — safe to run multiple times.
 */

$dbPath = __DIR__ . '/../data/calendar.db';

if (!file_exists($dbPath)) {
    echo "❌ Database not found: $dbPath\n";
    exit(1);
}

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Create event_pictures table
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('event_pictures', $tables)) {
        $db->exec("
            CREATE TABLE event_pictures (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id      INTEGER NOT NULL REFERENCES events(id) ON DELETE CASCADE,
                filename      TEXT NOT NULL,
                caption       TEXT DEFAULT NULL,
                display_order INTEGER DEFAULT 0,
                created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_event_pictures_event_id ON event_pictures(event_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_event_pictures_order    ON event_pictures(event_id, display_order)");
        echo "✅ Created event_pictures table + indexes.\n";
    } else {
        echo "ℹ️  event_pictures table already exists — skipping.\n";
    }

    // 2. Add gallery_template column to events table
    $ecols = $db->query("PRAGMA table_info(events)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('gallery_template', $ecols)) {
        $db->exec("ALTER TABLE events ADD COLUMN gallery_template TEXT DEFAULT 'grid3'");
        echo "✅ Added gallery_template column to events table (default: 'grid3').\n";
    } else {
        echo "ℹ️  gallery_template column already exists in events table — nothing to do.\n";
    }

    // 3. Create uploads/events directory
    $uploadsDir = __DIR__ . '/../uploads/events';
    if (!is_dir($uploadsDir)) {
        @mkdir($uploadsDir, 0755, true);
        echo "✅ Created uploads/events/ directory.\n";
    } else {
        echo "ℹ️  uploads/events/ directory already exists.\n";
    }

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✅ Migration complete.\n";
