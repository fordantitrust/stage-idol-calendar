<?php
/**
 * Migration: Add social link columns to artists table
 * Adds: social_facebook, social_instagram, social_twitter, social_tiktok
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
$stmt = $db->query("PRAGMA table_info(artists)");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $cols[] = $row['name'];
}

$added = 0;
$social = ['social_facebook', 'social_instagram', 'social_twitter', 'social_tiktok'];
foreach ($social as $col) {
    if (!in_array($col, $cols)) {
        $db->exec("ALTER TABLE artists ADD COLUMN $col TEXT DEFAULT NULL");
        echo "✅ Added column: $col\n";
        $added++;
    } else {
        echo "ℹ️  Column already exists: $col\n";
    }
}

echo $added > 0 ? "\n✅ Migration complete ($added columns added).\n" : "\n✅ No changes needed.\n";
