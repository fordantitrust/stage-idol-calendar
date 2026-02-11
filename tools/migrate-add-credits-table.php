<?php
/**
 * Migration Script: Add Credits Table
 * Idol Stage Timetable v1.0.0
 *
 * Creates credits table for storing credit/reference information
 */

require_once __DIR__ . '/../config.php';

$dbPath = __DIR__ . '/../calendar.db';

echo "==========================================\n";
echo "Migration: Create Credits Table\n";
echo "==========================================\n\n";

try {
    echo "Connecting to database: $dbPath\n";
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Connected successfully\n\n";

    // Create credits table
    echo "Creating credits table...\n";
    $sql = "
    CREATE TABLE IF NOT EXISTS credits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        link TEXT,
        description TEXT,
        display_order INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE INDEX IF NOT EXISTS idx_credits_order ON credits(display_order);
    ";

    $db->exec($sql);
    echo "✓ Credits table created successfully!\n\n";

    // Display table structure
    echo "Table structure:\n";
    echo "----------------\n";
    $stmt = $db->query("PRAGMA table_info(credits)");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $notNull = $row['notnull'] ? 'NOT NULL' : 'NULL';
        $default = $row['dflt_value'] ? "DEFAULT {$row['dflt_value']}" : '';
        echo sprintf("  %-15s %-12s %-10s %s\n",
            $row['name'],
            $row['type'],
            $notNull,
            $default
        );
    }
    echo "\n";

    // Display indexes
    echo "Indexes:\n";
    echo "--------\n";
    $stmt = $db->query("SELECT name, sql FROM sqlite_master WHERE type='index' AND tbl_name='credits' AND sql IS NOT NULL");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - {$row['name']}\n";
    }
    echo "\n";

    // Check if table exists and show row count
    $stmt = $db->query("SELECT COUNT(*) as count FROM credits");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Current records: $count\n\n";

    echo "==========================================\n";
    echo "✓ Migration completed successfully!\n";
    echo "==========================================\n";

} catch (PDOException $e) {
    echo "\n";
    echo "==========================================\n";
    echo "✗ Migration failed!\n";
    echo "==========================================\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
