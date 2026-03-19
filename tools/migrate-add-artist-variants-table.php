<?php
/**
 * Migration: Add Artist Variants Table
 * Idol Stage Timetable
 *
 * สร้างตาราง artist_variants สำหรับเก็บ variant names ของแต่ละศิลปิน
 * และ import variants จาก data/artists-mapping.json (ถ้ามี)
 *
 * Schema:
 *   artist_variants.artist_id  → artists(id) ON DELETE CASCADE
 *   artist_variants.variant    TEXT NOT NULL, UNIQUE per artist
 *
 * สคริปต์นี้ idempotent (รันซ้ำได้ปลอดภัย)
 *
 * Usage:
 *   cd tools && php migrate-add-artist-variants-table.php
 */

require_once __DIR__ . '/../config.php';

echo "=== Migration: Add Artist Variants Table ===\n\n";

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

// Check artists table exists
if (!$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='artists'")->fetch()) {
    echo "ERROR: 'artists' table not found. Run: php migrate-add-artists-table.php\n";
    exit(1);
}

$steps = [];

// --- 1. Create artist_variants table ---
$tableExists = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='artist_variants'")->fetch();

if (!$tableExists) {
    try {
        $db->exec("
            CREATE TABLE artist_variants (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                artist_id  INTEGER NOT NULL REFERENCES artists(id) ON DELETE CASCADE,
                variant    TEXT NOT NULL,
                created_at TEXT DEFAULT (datetime('now', 'localtime')),
                UNIQUE(artist_id, variant)
            )
        ");
        $steps[] = "  [OK]    artist_variants table created";
    } catch (PDOException $e) {
        echo "  [ERROR] artist_variants table: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    $steps[] = "  [SKIP]  artist_variants table (already exists)";
}

// --- 2. Create index ---
$indexName = 'idx_artist_variants_artist_id';
$indexExists = $db->query("SELECT name FROM sqlite_master WHERE type='index' AND name=" . $db->quote($indexName))->fetch();

try {
    $db->exec("CREATE INDEX IF NOT EXISTS $indexName ON artist_variants(artist_id)");
    $steps[] = $indexExists
        ? "  [SKIP]  index $indexName (already exists)"
        : "  [OK]    index $indexName created";
} catch (PDOException $e) {
    echo "  [ERROR] index $indexName: " . $e->getMessage() . "\n";
    exit(1);
}

foreach ($steps as $step) {
    echo $step . "\n";
}

// --- 3. Import variants from artists-mapping.json ---
echo "\n--- Import variants from data/artists-mapping.json ---\n";

$mappingPath = __DIR__ . '/../data/artists-mapping.json';
if (!file_exists($mappingPath)) {
    echo "  [SKIP]  artists-mapping.json not found — skipping variant import\n";
    echo "          (Run: php generate-artists-mapping.php to create it)\n";
    echo "\nMigration completed successfully.\n";
    exit(0);
}

$mapping = json_decode(file_get_contents($mappingPath), true);
if (!$mapping || !isset($mapping['artists']) || !is_array($mapping['artists'])) {
    echo "  [SKIP]  Invalid or empty artists-mapping.json\n";
    echo "\nMigration completed successfully.\n";
    exit(0);
}

// Build name → artist_id map (case-insensitive)
$artistIdMap = [];
$stmt = $db->query("SELECT id, name FROM artists");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $artistIdMap[mb_strtolower(trim($row['name']), 'UTF-8')] = (int)$row['id'];
}

$insertVariant = $db->prepare(
    "INSERT OR IGNORE INTO artist_variants (artist_id, variant) VALUES (?, ?)"
);

$inserted  = 0;
$skipped   = 0;
$noArtist  = 0;

try {
    $db->beginTransaction();

    foreach ($mapping['artists'] as $entry) {
        if (!empty($entry['skip'])) continue;

        $canonical = trim($entry['canonical'] ?? '');
        if ($canonical === '') continue;

        $artistId = $artistIdMap[mb_strtolower($canonical, 'UTF-8')] ?? null;
        if (!$artistId) {
            $noArtist++;
            echo "  [WARN]  Artist not found in DB: \"$canonical\"\n";
            continue;
        }

        foreach ($entry['variants'] ?? [] as $variant) {
            $variant = trim($variant);
            if ($variant === '') continue;

            // Don't insert if variant == canonical (exact match)
            // Still insert case variants (e.g. "ALICE" when canonical is "Alice")
            $insertVariant->execute([$artistId, $variant]);
            if ($db->lastInsertId()) {
                $inserted++;
            } else {
                $skipped++;
            }
        }
    }

    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "  Variants inserted : $inserted\n";
echo "  Already existed   : $skipped\n";
if ($noArtist > 0) {
    echo "  Artists not found : $noArtist (run migrate-artists-from-mapping.php first)\n";
}

$total = (int)$db->query("SELECT COUNT(*) FROM artist_variants")->fetchColumn();
echo "  Total in DB       : $total\n";

echo "\nMigration completed successfully.\n";
echo "\nArtist variants are now managed in the database.\n";
echo "You can also manage them via Admin > Artists > Variants.\n";
exit(0);
