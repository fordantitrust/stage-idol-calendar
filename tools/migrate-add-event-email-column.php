<?php
/**
 * Migration: เพิ่ม email column ใน events table
 *
 * ใช้สำหรับ existing install ที่สร้าง DB ก่อน v2.2.2
 * Script นี้ idempotent — รันซ้ำได้โดยไม่มีผลเสีย
 *
 * Usage:
 *   cd tools
 *   php migrate-add-event-email-column.php
 */

$dbPath = __DIR__ . '/../data/calendar.db';

if (!file_exists($dbPath)) {
    echo "❌ ไม่พบ database: $dbPath\n";
    exit(1);
}

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $cols = $db->query("PRAGMA table_info(events)")->fetchAll(PDO::FETCH_COLUMN, 1);

    if (in_array('email', $cols)) {
        echo "✅ email column มีอยู่แล้วใน events table — ไม่ต้อง migrate\n";
    } else {
        $db->exec("ALTER TABLE events ADD COLUMN email TEXT DEFAULT NULL");
        echo "✅ เพิ่ม email column ใน events table เรียบร้อย\n";
    }
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
