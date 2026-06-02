<?php
/**
 * Migration: Add ticket_url column to events table
 * Idempotent — safe to run multiple times.
 */
$dbPath = __DIR__ . '/../data/calendar.db';
if (!file_exists($dbPath)) {
    echo "❌ Database not found: $dbPath\n";
    exit(1);
}

$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$cols = [];
$stmt = $db->query("PRAGMA table_info(events)");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $cols[] = $row['name'];
}

if (!in_array('ticket_url', $cols)) {
    $db->exec("ALTER TABLE events ADD COLUMN ticket_url TEXT DEFAULT NULL");
    echo "✅ Added column: ticket_url to events table.\n";
} else {
    echo "ℹ️  Column ticket_url already exists in events table.\n";
}

echo "✅ Migration complete.\n";
