<?php
/**
 * Import ICS files to SQLite Database
 * สคริปต์สำหรับ import ไฟล์ .ics ทั้งหมดจากโฟลเดอร์ ics/ ลงใน SQLite database
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../IcsParser.php';

echo "=== ICS to SQLite Import Script ===\n\n";

// Parse --event=slug argument
$eventSlug = null;
foreach ($argv ?? [] as $arg) {
    if (strpos($arg, '--event=') === 0) {
        $eventSlug = substr($arg, 8);
    }
}

if ($eventSlug) {
    echo "Event slug: $eventSlug\n";
}

// กำหนด path
$icsFolder = __DIR__ . '/../ics';
$dbPath = __DIR__ . '/../data/calendar.db';

// สร้างหรือเชื่อมต่อ SQLite database
try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Connected to database: $dbPath\n\n";
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Resolve event_id from slug (events table, formerly events_meta)
$eventMetaId = null;
if ($eventSlug) {
    $metaStmt = $db->prepare("SELECT id FROM programs WHERE slug = :slug");
    $metaStmt->execute([':slug' => $eventSlug]);
    $metaRow = $metaStmt->fetch(PDO::FETCH_ASSOC);
    if ($metaRow) {
        $eventMetaId = intval($metaRow['id']);
        echo "Event ID: $eventMetaId\n\n";
    } else {
        echo "Warning: Event slug '$eventSlug' not found in events table. Programs will be imported without event_id.\n\n";
    }
}

// สร้าง table structure
echo "📋 Creating table structure...\n";
$createTableSQL = "
CREATE TABLE IF NOT EXISTS programs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uid TEXT UNIQUE NOT NULL,
    title TEXT NOT NULL,
    start DATETIME NOT NULL,
    end DATETIME NOT NULL,
    location TEXT,
    organizer TEXT,
    description TEXT,
    categories TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_start ON programs(start);
CREATE INDEX IF NOT EXISTS idx_categories ON programs(categories);
CREATE INDEX IF NOT EXISTS idx_location ON programs(location);
";

try {
    $db->exec($createTableSQL);
    echo "✅ Table structure created/verified\n\n";
} catch (PDOException $e) {
    echo "❌ Failed to create table: " . $e->getMessage() . "\n";
    exit(1);
}

// หาไฟล์ .ics ทั้งหมด
$files = glob($icsFolder . '/*.ics');

if (empty($files)) {
    echo "⚠️  No .ics files found in folder: $icsFolder/\n";
    exit;
}

echo "📁 Found " . count($files) . " file(s)\n\n";

// สถิติการ import
$stats = [
    'inserted' => 0,
    'updated' => 0,
    'skipped' => 0,
    'errors' => 0
];

// เตรียม SQL statements
$insertSQL = "
INSERT INTO programs(uid, title, start, end, location, organizer, description, categories, event_id)
VALUES (:uid, :title, :start, :end, :location, :organizer, :description, :categories, :event_id)
";

$updateSQL = "
UPDATE programs
SET title = :title,
    start = :start,
    end = :end,
    location = :location,
    organizer = :organizer,
    description = :description,
    categories = :categories,
    updated_at = CURRENT_TIMESTAMP
WHERE uid = :uid
";

$insertStmt = $db->prepare($insertSQL);
$updateStmt = $db->prepare($updateSQL);

// Parse แต่ละไฟล์ (ใช้ file mode สำหรับ import)
$parser = new IcsParser($icsFolder, false); // false = ใช้ file mode

foreach ($files as $file) {
    echo "Processing: " . basename($file) . "\n";

    $content = file_get_contents($file);
    if ($content === false) {
        echo "  ❌ Error reading file\n";
        $stats['errors']++;
        continue;
    }

    // แยก VEVENT ออกมา
    preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $content, $matches);

    foreach ($matches[1] as $eventData) {
        // Parse event โดยใช้ method จาก IcsParser (public method)
        $event = $parser->parseEvent($eventData);

        if (!$event) {
            continue; // ข้าม event ที่ไม่สมบูรณ์
        }

        // ตรวจสอบว่ามี event นี้อยู่แล้วหรือไม่
        $checkStmt = $db->prepare("SELECT id FROM programs WHERE uid = :uid");
        $checkStmt->execute([':uid' => $event['uid']]);
        $exists = $checkStmt->fetch();

        try {
            if ($exists) {
                // Update existing event
                $updateStmt->execute([
                    ':uid' => $event['uid'],
                    ':title' => $event['title'],
                    ':start' => $event['start'],
                    ':end' => $event['end'],
                    ':location' => $event['location'],
                    ':organizer' => $event['organizer'],
                    ':description' => $event['description'],
                    ':categories' => $event['categories']
                ]);
                $stats['updated']++;
                echo "  🔄 Updated: " . $event['title'] . "\n";
            } else {
                // Insert new event
                $insertStmt->execute([
                    ':uid' => $event['uid'],
                    ':title' => $event['title'],
                    ':start' => $event['start'],
                    ':end' => $event['end'],
                    ':location' => $event['location'],
                    ':organizer' => $event['organizer'],
                    ':description' => $event['description'],
                    ':categories' => $event['categories'],
                    ':event_id' => $eventMetaId
                ]);
                $stats['inserted']++;
                echo "  ✅ Inserted: " . $event['title'] . "\n";
            }
        } catch (PDOException $e) {
            echo "  ❌ Error: " . $e->getMessage() . "\n";
            $stats['errors']++;
        }
    }

    echo "\n";
}

// แสดงสถิติ
echo "=== Import Summary ===\n";
echo "✅ Inserted: " . $stats['inserted'] . " event(s)\n";
echo "🔄 Updated: " . $stats['updated'] . " event(s)\n";
echo "⏭️  Skipped: " . $stats['skipped'] . " event(s)\n";
echo "❌ Errors: " . $stats['errors'] . " event(s)\n";

// แสดงจำนวนรวมใน database
$countStmt = $db->query("SELECT COUNT(*) as total FROM programs");
$total = $countStmt->fetch(PDO::FETCH_ASSOC);
echo "\n📊 Total events in database: " . $total['total'] . "\n";

echo "\n✅ Import completed!\n";
?>
