<?php
/**
 * Migration: Add artist_requests table (v12.3.0)
 * Idempotent - safe to run multiple times.
 */

require_once __DIR__ . '/../config.php';

echo "=== Migration: Add artist_requests table ===\n\n";

try {
    $db = get_db();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->exec("
        CREATE TABLE IF NOT EXISTS artist_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            is_group INTEGER DEFAULT 0,
            group_id INTEGER DEFAULT NULL,
            social_facebook TEXT DEFAULT NULL,
            social_instagram TEXT DEFAULT NULL,
            social_twitter TEXT DEFAULT NULL,
            social_tiktok TEXT DEFAULT NULL,
            requester_user_id INTEGER DEFAULT NULL,
            requester_name TEXT NOT NULL,
            requester_email TEXT DEFAULT NULL,
            status TEXT DEFAULT 'pending',
            admin_note TEXT DEFAULT NULL,
            reviewed_at DATETIME DEFAULT NULL,
            reviewed_by TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $db->exec("CREATE INDEX IF NOT EXISTS idx_artist_requests_status ON artist_requests(status)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_artist_requests_created_at ON artist_requests(created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_artist_requests_requester_user_id ON artist_requests(requester_user_id)");

    echo "OK: artist_requests table and indexes are ready.\n";
    exit(0);
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
