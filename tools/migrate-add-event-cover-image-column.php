<?php
/**
 * Migration: Add cover_image column to events table
 *
 * Enables per-event hero/card cover image:
 * - NULL = no cover (fallback to first event_picture, then theme gradient)
 * - Path string = relative path to uploaded cover image (1600x900 JPEG, 16:9)
 *
 * Storage: uploads/events/{event_id}/cover_{uniqid}.jpg
 */

require_once __DIR__ . '/../config.php';

$dbPath = __DIR__ . '/../data/calendar.db';

echo "=================================================\n";
echo "Migration: Add cover_image Column to events Table\n";
echo "=================================================\n\n";

try {
    echo "Connecting to database: $dbPath\n";
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected successfully\n\n";

    // Check if events table exists
    $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='events'");
    if (!$tableCheck->fetch()) {
        echo "Error: events table does not exist.\n";
        echo "Please run migrate-add-events-meta-table.php first.\n";
        exit(1);
    }

    // Check if cover_image column already exists
    echo "Step 1: Checking for cover_image column...\n";
    $columns = $db->query("PRAGMA table_info(events)")->fetchAll(PDO::FETCH_ASSOC);
    $hasCoverImage = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'cover_image') {
            $hasCoverImage = true;
            break;
        }
    }

    if ($hasCoverImage) {
        echo "  cover_image column already exists, skipping ALTER TABLE\n\n";
    } else {
        echo "  Adding cover_image column...\n";
        $db->exec("ALTER TABLE events ADD COLUMN cover_image TEXT DEFAULT NULL");
        echo "  cover_image column added successfully (default: NULL)\n\n";
    }

    // Ensure uploads/events directory exists
    echo "Step 2: Checking uploads/events directory...\n";
    $uploadsDir = __DIR__ . '/../uploads/events';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0777, true);
        echo "  Created: uploads/events/\n";
    } else {
        echo "  uploads/events/ already exists\n";
    }

    // Display summary
    echo "\n=================================================\n";
    echo "Migration Summary\n";
    echo "=================================================\n\n";

    echo "events table (cover_image column):\n";
    $stmt = $db->query("SELECT id, slug, name, cover_image FROM events ORDER BY id");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cover = $row['cover_image'] ?? 'NULL (no cover)';
        echo "  ID: {$row['id']}, Slug: {$row['slug']}, Name: {$row['name']}, Cover: $cover\n";
    }

    echo "\n=================================================\n";
    echo "Migration completed successfully!\n";
    echo "=================================================\n";
    echo "\nCover image priority (homepage hero/card):\n";
    echo "  1. events.cover_image (this column) — 1600x900 JPEG, 16:9\n";
    echo "  2. First event_picture (from event_pictures table)\n";
    echo "  3. Theme gradient fallback (no image)\n";
    echo "\nUpload cover via: Admin > Events > Edit > Cover Image section\n";

} catch (PDOException $e) {
    echo "\n=================================================\n";
    echo "Migration failed!\n";
    echo "=================================================\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
