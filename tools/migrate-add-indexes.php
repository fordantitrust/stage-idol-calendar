<?php
/**
 * Migration: Add Database Indexes
 * Idol Stage Timetable — Performance Optimization
 *
 * เพิ่ม indexes บน columns ที่ query บ่อย เพื่อเพิ่มความเร็ว 2-5x
 * สคริปต์นี้ idempotent (รันซ้ำได้ปลอดภัย)
 *
 * Usage:
 *   cd tools && php migrate-add-indexes.php
 */

require_once __DIR__ . '/../config.php';

echo "=== Migration: Add Database Indexes ===\n\n";

if (!defined('DB_PATH') || !file_exists(DB_PATH)) {
    echo "ERROR: Database not found at: " . (defined('DB_PATH') ? DB_PATH : '(DB_PATH not defined)') . "\n";
    exit(1);
}

try {
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "ERROR: Cannot connect to database: " . $e->getMessage() . "\n";
    exit(1);
}

$indexes = [
    // programs table — ใช้บ่อยมากใน filtering, sorting, date queries
    'idx_programs_event_id'   => 'CREATE INDEX IF NOT EXISTS idx_programs_event_id ON programs(event_id)',
    'idx_programs_start'      => 'CREATE INDEX IF NOT EXISTS idx_programs_start ON programs(start)',
    'idx_programs_location'   => 'CREATE INDEX IF NOT EXISTS idx_programs_location ON programs(location)',
    'idx_programs_categories' => 'CREATE INDEX IF NOT EXISTS idx_programs_categories ON programs(categories)',

    // program_requests table — ใช้ใน admin panel filtering
    'idx_program_requests_status'   => 'CREATE INDEX IF NOT EXISTS idx_program_requests_status ON program_requests(status)',
    'idx_program_requests_event_id' => 'CREATE INDEX IF NOT EXISTS idx_program_requests_event_id ON program_requests(event_id)',

    // credits table — ใช้ใน per-event credits query
    'idx_credits_event_id' => 'CREATE INDEX IF NOT EXISTS idx_credits_event_id ON credits(event_id)',
];

$created = 0;
$skipped = 0;
$errors  = 0;

foreach ($indexes as $name => $sql) {
    // Check if index already exists
    $check = $db->query("SELECT name FROM sqlite_master WHERE type='index' AND name=" . $db->quote($name));
    $exists = $check && $check->fetch();

    try {
        $db->exec($sql);
        if ($exists) {
            echo "  [SKIP]  $name (already exists)\n";
            $skipped++;
        } else {
            echo "  [OK]    $name\n";
            $created++;
        }
    } catch (PDOException $e) {
        echo "  [ERROR] $name: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n--- Summary ---\n";
echo "Created : $created\n";
echo "Skipped : $skipped\n";
echo "Errors  : $errors\n";

if ($errors === 0) {
    echo "\nMigration completed successfully.\n";
    exit(0);
} else {
    echo "\nMigration completed with errors.\n";
    exit(1);
}
