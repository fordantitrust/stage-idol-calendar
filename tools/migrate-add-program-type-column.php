<?php
/**
 * Migration: Add program_type column to programs table
 *
 * Enables program type support:
 * - NULL = no type (backward compatible, no badge shown)
 * - Free-text value: 'stage', 'booth', 'meet & greet', 'talk', etc.
 *
 * Types are derived from actual data per-event (not a fixed enum).
 */

require_once __DIR__ . '/../config.php';

$dbPath = __DIR__ . '/../data/calendar.db';

echo "============================================\n";
echo "Migration: Add program_type Column to programs Table\n";
echo "============================================\n\n";

try {
    echo "Connecting to database: $dbPath\n";
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected successfully\n\n";

    // Check if programs table exists
    $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='programs'");
    if (!$tableCheck->fetch()) {
        echo "Error: programs table does not exist.\n";
        echo "Please run import-ics-to-sqlite.php first.\n";
        exit(1);
    }

    // Check if program_type column already exists
    echo "Step 1: Checking for program_type column...\n";
    $columns = $db->query("PRAGMA table_info(programs)")->fetchAll(PDO::FETCH_ASSOC);
    $hasColumn = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'program_type') {
            $hasColumn = true;
            break;
        }
    }

    if ($hasColumn) {
        echo "  program_type column already exists, skipping ALTER TABLE\n\n";
    } else {
        echo "  Adding program_type column...\n";
        $db->exec("ALTER TABLE programs ADD COLUMN program_type TEXT DEFAULT NULL");
        echo "  program_type column added successfully (default: NULL = no type)\n\n";
    }

    // Display summary
    echo "============================================\n";
    echo "Migration Summary\n";
    echo "============================================\n\n";

    $stmt = $db->query("SELECT COUNT(*) as total FROM programs");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "Total programs: $total\n";

    $stmt = $db->query("SELECT COUNT(*) as total FROM programs WHERE program_type IS NOT NULL AND program_type != ''");
    $typed = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "Programs with type set: $typed\n\n";

    echo "============================================\n";
    echo "Migration completed successfully!\n";
    echo "============================================\n";
    echo "\nUsage:\n";
    echo "  Set program_type to any free-text value (e.g., 'stage', 'booth', 'meet & greet')\n";
    echo "  Leave NULL for backward compatibility (no type badge shown)\n";

} catch (PDOException $e) {
    echo "\n============================================\n";
    echo "Migration failed!\n";
    echo "============================================\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
