<?php
/**
 * Migration: Add display_picture and cover_picture columns to artists table
 * v6.0.0 — Artist Cover & Display Picture System
 *
 * Idempotent — safe to run multiple times.
 *
 * Usage:
 *   php tools/migrate-add-artist-pictures-column.php
 */

define('ROOT_DIR', dirname(__DIR__));
require_once ROOT_DIR . '/config/database.php';

echo "Migration: add display_picture + cover_picture to artists\n";
echo str_repeat('-', 60) . "\n";

try {
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check existing columns
    $columns = $db->query("PRAGMA table_info(artists)")->fetchAll(PDO::FETCH_COLUMN, 1);

    $added = 0;

    if (!in_array('display_picture', $columns)) {
        $db->exec("ALTER TABLE artists ADD COLUMN display_picture TEXT DEFAULT NULL");
        echo "  ✅ เพิ่ม column display_picture เรียบร้อย\n";
        $added++;
    } else {
        echo "  ℹ️  column display_picture มีอยู่แล้ว\n";
    }

    if (!in_array('cover_picture', $columns)) {
        $db->exec("ALTER TABLE artists ADD COLUMN cover_picture TEXT DEFAULT NULL");
        echo "  ✅ เพิ่ม column cover_picture เรียบร้อย\n";
        $added++;
    } else {
        echo "  ℹ️  column cover_picture มีอยู่แล้ว\n";
    }

    // Create uploads directory
    $uploadsDir = ROOT_DIR . '/uploads/artists';
    if (!is_dir($uploadsDir)) {
        if (@mkdir($uploadsDir, 0755, true)) {
            echo "  ✅ สร้างโฟลเดอร์ uploads/artists/ เรียบร้อย\n";
        } else {
            echo "  ⚠️  ไม่สามารถสร้างโฟลเดอร์ uploads/artists/ ได้ (กรุณาสร้างเอง)\n";
        }
    } else {
        echo "  ℹ️  โฟลเดอร์ uploads/artists/ มีอยู่แล้ว\n";
    }

    echo str_repeat('-', 60) . "\n";
    if ($added > 0) {
        echo "✅ Migration สำเร็จ: เพิ่ม $added column(s)\n";
    } else {
        echo "✅ Migration ไม่จำเป็น: columns มีอยู่แล้ว (idempotent)\n";
    }

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
