<?php
/**
 * Migration: Add event_requests table
 * Idempotent — safe to run multiple times
 */

require_once __DIR__ . '/../config.php';

echo "=== Migration: Add event_requests table ===\n\n";

try {
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if table already exists
    $check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='event_requests'");
    if ($check->fetch()) {
        echo "✅ Table 'event_requests' already exists — skipping.\n";
    } else {
        $db->exec("
            CREATE TABLE IF NOT EXISTS event_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                request_type TEXT NOT NULL,
                event_id INTEGER,
                name TEXT,
                description TEXT,
                start_date DATE,
                end_date DATE,
                requester_name TEXT NOT NULL,
                requester_email TEXT,
                note TEXT,
                status TEXT DEFAULT 'pending',
                admin_note TEXT,
                reviewed_at DATETIME,
                reviewed_by TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        echo "✅ Table 'event_requests' created successfully.\n";
    }

    echo "\n=== Migration complete ===\n";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
