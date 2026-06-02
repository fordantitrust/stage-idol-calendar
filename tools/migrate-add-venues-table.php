<?php
/**
 * Migration: Add Venues + Venue Variants Tables
 * Idol Stage Timetable (v16.0.0)
 *
 * สร้างตาราง canonical สำหรับสถานที่ (venues) + ชื่อเรียกอื่น (venue_variants)
 * เพื่อใช้เป็นแหล่ง autocomplete + auto-canonicalize ตอน save/ICS import
 *
 * Schema:
 *   venues.name            TEXT UNIQUE NOT NULL  (canonical location string)
 *   venue_variants.venue_id → venues(id) ON DELETE CASCADE
 *   venue_variants.variant TEXT NOT NULL, UNIQUE per venue
 *
 * Seeding:
 *   1) venues          ← DISTINCT programs.location (ค่าปัจจุบันที่ canonical แล้ว)
 *   2) venue_variants  ← mapping ของ dedup ที่ทำไปแล้ว (ดู tools/dedup-locations.sql)
 *      เพื่อให้ ICS import / save form ที่เจอชื่อเก่า แปลงกลับเป็น canonical อัตโนมัติ
 *
 * NOTE: programs.location ยังเป็น TEXT canonical (ไม่มี venue_id FK) — lightweight by design
 *
 * สคริปต์นี้ idempotent (รันซ้ำได้ปลอดภัย)
 *
 * Usage:
 *   cd tools && php migrate-add-venues-table.php
 */

require_once __DIR__ . '/../config.php';

echo "=== Migration: Add Venues + Venue Variants Tables ===\n\n";

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

$steps = [];

// --- 1. Create venues table ---
$venuesExists = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='venues'")->fetch();
if (!$venuesExists) {
    $db->exec("
        CREATE TABLE venues (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            name        TEXT UNIQUE NOT NULL,
            description TEXT DEFAULT NULL,
            map_url     TEXT DEFAULT NULL,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $steps[] = "  [OK]    venues table created";
} else {
    $steps[] = "  [SKIP]  venues table (already exists)";
}

// --- 2. Create venue_variants table ---
$variantsExists = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='venue_variants'")->fetch();
if (!$variantsExists) {
    $db->exec("
        CREATE TABLE venue_variants (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            venue_id   INTEGER NOT NULL REFERENCES venues(id) ON DELETE CASCADE,
            variant    TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(venue_id, variant)
        )
    ");
    $steps[] = "  [OK]    venue_variants table created";
} else {
    $steps[] = "  [SKIP]  venue_variants table (already exists)";
}

// --- 3. is_online column (v16.0.1, idempotent) ---
$vcols = $db->query("PRAGMA table_info(venues)")->fetchAll(PDO::FETCH_COLUMN, 1);
if (!in_array('is_online', $vcols)) {
    $db->exec("ALTER TABLE venues ADD COLUMN is_online INTEGER DEFAULT 0");
    $steps[] = "  [OK]    venues.is_online column added (v16.0.1)";
} else {
    $steps[] = "  [SKIP]  venues.is_online column (already exists)";
}

// --- 4. Indexes ---
$db->exec("CREATE INDEX IF NOT EXISTS idx_venue_variants_venue_id ON venue_variants(venue_id)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_venues_name ON venues(name)");
$steps[] = "  [OK]    indexes ensured";

foreach ($steps as $step) {
    echo $step . "\n";
}

// --- 4. Seed venues from DISTINCT programs.location ---
echo "\n--- Seed venues from existing programs.location ---\n";
$insertVenue = $db->prepare("INSERT OR IGNORE INTO venues (name) VALUES (?)");
$seeded = 0;
$rows = $db->query("SELECT DISTINCT location FROM programs WHERE location IS NOT NULL AND location != ''")->fetchAll(PDO::FETCH_COLUMN);
$db->beginTransaction();
foreach ($rows as $loc) {
    $loc = trim($loc);
    if ($loc === '') continue;
    $insertVenue->execute([$loc]);
    if ($db->lastInsertId()) $seeded++;
}
$db->commit();
echo "  Venues seeded (new): $seeded\n";
echo "  Total venues       : " . (int)$db->query("SELECT COUNT(*) FROM venues")->fetchColumn() . "\n";

// --- 5. Seed venue_variants from dedup mapping ---
// canonical => [old strings ที่เคย merge ไป] — ดู tools/dedup-locations.sql
echo "\n--- Seed venue_variants from dedup mapping ---\n";
$dedupMap = [
    'Catsonic Livehouse, 2nd Fl. The Street Ratchada' => [
        'Catsolute Live House, The Street Ratchada 2nd Fl.',
        '@CATSONIC LIVEHOUSE 2nd Floor, The Street Ratchada',
        'Catsonic Live house, The Street Ratchada, 2nd Floor',
        'Catsonic Livehouse (2nd Fl. The Street Ratchada)',
        'Catsonic Livehouse, 2Fl. The Street Ratchada',
    ],
    'The Street Hall, 5th Fl. The Street Ratchada' => [
        'The Street Hall 5th Fl. The Street Ratchada',
        'THE STREET HALL, THE STREET RATCHADA',
        'The Street Ratchada, 5th Floor',
    ],
    'Lot of Live, 3rd Fl. Phenix Pratunam' => [
        'A Lot of Live, Phenix Pratunam, 3rd Floor',
        'Lot Of Live @ Phenix Pratunam',
        'Lot of Live @ Phenix Pratunam',
        'LOT OF LIVE, 3rd Floor Phenix Pratunam',
        'Lot of Live, Phenix Pratunam 3rd Floor',
        'Lot of Live, Phenix Pratunam, 3rd Floor',
        'Phenix Pratunam',
    ],
    'Donki Hall, 4th Fl. Donki Mall Thonglor' => [
        'DONKI HALL, DONKI MALL THONGLOR 4TH FLOOR',
        'DONKI HALL, DONKIMALL THONGLOR',
        'Donki Mall Thonglor',
    ],
    'ลานกิจกรรม ชั้น 1 ศูนย์การค้าเซ็นทรัล เชียงใหม่ แอร์พอร์ต' => [
        'เซ็นทรัล เชียงใหม่ แอร์พอร์ต',
    ],
    'LIVECUBE SUKHUMVIT 69' => [
        '𝐋𝐈𝐕𝐄𝐂𝐔𝐁𝐄 𝐒𝐔𝐊𝐇𝐔𝐌𝐖𝐈𝐓 𝟔𝟗',
    ],
];

$ensureVenue = $db->prepare("INSERT OR IGNORE INTO venues (name) VALUES (?)");
$findVenue   = $db->prepare("SELECT id FROM venues WHERE name = ?");
$insertVar   = $db->prepare("INSERT OR IGNORE INTO venue_variants (venue_id, variant) VALUES (?, ?)");

$varInserted = 0; $varSkipped = 0;
$db->beginTransaction();
foreach ($dedupMap as $canonical => $variants) {
    $ensureVenue->execute([$canonical]);
    $findVenue->execute([$canonical]);
    $vid = (int)$findVenue->fetchColumn();
    if (!$vid) continue;
    foreach ($variants as $variant) {
        $variant = trim($variant);
        if ($variant === '' || $variant === $canonical) continue;
        $insertVar->execute([$vid, $variant]);
        if ($db->lastInsertId()) $varInserted++; else $varSkipped++;
    }
}
$db->commit();
echo "  Variants inserted  : $varInserted\n";
echo "  Already existed    : $varSkipped\n";
echo "  Total variants     : " . (int)$db->query("SELECT COUNT(*) FROM venue_variants")->fetchColumn() . "\n";

// --- 6. Mark known online platforms (v16.0.1) ---
echo "\n--- Mark known online platforms (is_online=1) ---\n";
$onlineNames = ['YouTube', 'Instagram Live', 'X Spaces'];
$markStmt = $db->prepare("UPDATE venues SET is_online = 1 WHERE name = ? AND is_online = 0");
$markedCount = 0;
foreach ($onlineNames as $name) {
    $markStmt->execute([$name]);
    $markedCount += $markStmt->rowCount();
}
$markStmt->closeCursor(); $markStmt = null;
$totalOnline = (int)$db->query("SELECT COUNT(*) FROM venues WHERE is_online = 1")->fetchColumn();
echo "  Newly marked online: $markedCount\n";
echo "  Total online       : $totalOnline\n";

echo "\nMigration completed successfully.\n";
echo "Manage venues via Admin > Venues (list / variants / merge).\n";
exit(0);
