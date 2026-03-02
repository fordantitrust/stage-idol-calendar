<?php
/**
 * Migration: Add theme column to events table
 *
 * Enables per-event theme support:
 * - NULL = use global theme from Settings (or 'dark' fallback)
 * - Valid theme name = use that theme for this event
 *
 * Valid themes: sakura, ocean, forest, midnight, sunset, dark, gray
 */

require_once __DIR__ . '/../config.php';

$dbPath = __DIR__ . '/../data/calendar.db';

echo "============================================\n";
echo "Migration: Add Theme Column to events Table\n";
echo "============================================\n\n";

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

    // Check if theme column already exists
    echo "Step 1: Checking for theme column...\n";
    $columns = $db->query("PRAGMA table_info(events)")->fetchAll(PDO::FETCH_ASSOC);
    $hasTheme = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'theme') {
            $hasTheme = true;
            break;
        }
    }

    if ($hasTheme) {
        echo "  theme column already exists, skipping ALTER TABLE\n\n";
    } else {
        echo "  Adding theme column...\n";
        $db->exec("ALTER TABLE events ADD COLUMN theme TEXT DEFAULT NULL");
        echo "  theme column added successfully (default: NULL = use global theme)\n\n";
    }

    // Display summary
    echo "============================================\n";
    echo "Migration Summary\n";
    echo "============================================\n\n";

    echo "events table (theme column):\n";
    $stmt = $db->query("SELECT id, slug, name, theme, is_active FROM events ORDER BY id");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $theme = $row['theme'] ?? 'NULL (global)';
        echo "  ID: {$row['id']}, Slug: {$row['slug']}, Name: {$row['name']}, ";
        echo "Theme: $theme, Active: {$row['is_active']}\n";
    }

    echo "\n============================================\n";
    echo "Migration completed successfully!\n";
    echo "============================================\n";
    echo "\nTheme priority:\n";
    echo "  1. Event-specific theme (this column)\n";
    echo "  2. Global theme (Settings tab in Admin)\n";
    echo "  3. Default fallback: dark\n";
    echo "\nValid theme values: sakura, ocean, forest, midnight, sunset, dark, gray\n";
    echo "Set to NULL to use global theme.\n";

} catch (PDOException $e) {
    echo "\n============================================\n";
    echo "Migration failed!\n";
    echo "============================================\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
