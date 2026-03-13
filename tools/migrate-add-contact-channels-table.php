<?php
/**
 * Migration: Add contact_channels table
 * Idempotent — safe to run multiple times
 *
 * Usage:
 *   cd tools
 *   php migrate-add-contact-channels-table.php
 */

$dbPath = __DIR__ . '/../data/calendar.db';

if (!file_exists($dbPath)) {
    echo "Database not found: $dbPath\n";
    exit(1);
}

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if table already exists
    $check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='contact_channels'");
    if ($check->fetch()) {
        echo "Table 'contact_channels' already exists — skipping.\n";
        exit(0);
    }

    $db->exec("CREATE TABLE contact_channels (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        icon TEXT DEFAULT '',
        title TEXT NOT NULL DEFAULT '',
        description TEXT DEFAULT '',
        url TEXT DEFAULT '',
        display_order INTEGER DEFAULT 0,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    echo "Created table 'contact_channels' successfully.\n";
    echo "You can now add contact channels through Admin › Contact tab.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
