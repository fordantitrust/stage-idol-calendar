<?php
/**
 * Migration: Add cover_image_card column to events table
 *
 * Separate card cover (4:3) from hero cover (16:9):
 * - cover_image      = Hero Carousel cover (1600x900, 16:9)
 * - cover_image_card = Event Card cover    (800x600,  4:3)
 *
 * NULL = inherit hero cover as fallback, then event_pictures, then gradient
 */

require_once __DIR__ . '/../config.php';

$dbPath = __DIR__ . '/../data/calendar.db';

echo "=======================================================\n";
echo "Migration: Add cover_image_card Column to events Table\n";
echo "=======================================================\n\n";

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected successfully\n\n";

    $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='events'");
    if (!$tableCheck->fetch()) {
        echo "Error: events table does not exist.\n"; exit(1);
    }

    echo "Step 1: Checking for cover_image_card column...\n";
    $columns = $db->query("PRAGMA table_info(events)")->fetchAll(PDO::FETCH_ASSOC);
    $hasCol  = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'cover_image_card') { $hasCol = true; break; }
    }

    if ($hasCol) {
        echo "  cover_image_card already exists, skipping\n\n";
    } else {
        $db->exec("ALTER TABLE events ADD COLUMN cover_image_card TEXT DEFAULT NULL");
        echo "  cover_image_card added (default: NULL)\n\n";
    }

    echo "=======================================================\n";
    echo "Migration completed successfully!\n";
    echo "=======================================================\n";
    echo "\nCover image priority:\n";
    echo "  Hero Carousel : events.cover_image       (16:9 — 1600x900)\n";
    echo "  Event Card    : events.cover_image_card  (4:3  — 800x600)\n";
    echo "                  fallback → cover_image → event_pictures → gradient\n";

} catch (PDOException $e) {
    echo "\nMigration failed!\nError: " . $e->getMessage() . "\n"; exit(1);
}
