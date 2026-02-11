<?php
/**
 * Migration: Add event_requests table
 */

echo "=== Migration: Add event_requests table ===\n\n";

$dbPath = __DIR__ . '/../calendar.db';

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected to: $dbPath\n\n";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage() . "\n");
}

$sql = "
CREATE TABLE IF NOT EXISTS event_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    type TEXT NOT NULL CHECK(type IN ('add', 'modify')),
    event_id INTEGER,
    title TEXT NOT NULL,
    start DATETIME NOT NULL,
    end DATETIME NOT NULL,
    location TEXT,
    organizer TEXT,
    description TEXT,
    categories TEXT,
    requester_name TEXT NOT NULL,
    requester_email TEXT,
    requester_note TEXT,
    status TEXT DEFAULT 'pending' CHECK(status IN ('pending', 'approved', 'rejected')),
    admin_note TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME,
    reviewed_by TEXT,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_requests_status ON event_requests(status);
CREATE INDEX IF NOT EXISTS idx_requests_created ON event_requests(created_at);
";

try {
    $db->exec($sql);
    echo "Table created!\n\n";

    $stmt = $db->query("PRAGMA table_info(event_requests)");
    echo "Columns:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - {$row['name']} ({$row['type']})\n";
    }
    echo "\nDone!\n";
} catch (PDOException $e) {
    die("Failed: " . $e->getMessage() . "\n");
}
