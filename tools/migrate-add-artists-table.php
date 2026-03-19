<?php
/**
 * Migration: Add Artists Tables
 * Idol Stage Timetable
 *
 * สร้างตาราง artists (self-referential) และ program_artists
 *
 * Schema:
 *   artists.is_group  = 1 หมายถึงกลุ่ม, 0 หมายถึงศิลปินเดี่ยว/สมาชิก
 *   artists.group_id  → artists(id)  (self-referential: สมาชิกชี้ไปกลุ่ม)
 *   program_artists   : many-to-many programs ↔ artists
 *
 * สคริปต์นี้ idempotent (รันซ้ำได้ปลอดภัย)
 *
 * Usage:
 *   cd tools && php migrate-add-artists-table.php
 *
 * Next step:
 *   php generate-artists-mapping.php
 */

require_once __DIR__ . '/../config.php';

echo "=== Migration: Add Artists Tables ===\n\n";

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

// --- 1. ลบ artist_groups ถ้ายังเหลืออยู่จากการออกแบบเดิม ---
$oldGroupsTable = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='artist_groups'")->fetch();
if ($oldGroupsTable) {
    $count = (int)$db->query("SELECT COUNT(*) FROM artist_groups")->fetchColumn();
    if ($count > 0) {
        echo "  [WARN]  artist_groups table has $count rows — skipping drop to avoid data loss.\n";
        echo "          Run manually: DROP TABLE artist_groups; if you want to remove it.\n";
    } else {
        $db->exec("DROP TABLE artist_groups");
        $steps[] = "  [OK]    artist_groups table dropped (was empty, replaced by self-referential design)";
    }
}

// --- 2. artists table ---
// ตรวจว่า artists มีโครงสร้างถูกต้อง (self-referential: group_id → artists + is_group column)
$artistsExists = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='artists'")->fetch();

$needRecreate = false;
if ($artistsExists) {
    $cols = array_column($db->query("PRAGMA table_info(artists)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    $fks  = $db->query("PRAGMA foreign_key_list(artists)")->fetchAll(PDO::FETCH_ASSOC);

    $hasIsGroup   = in_array('is_group', $cols);
    $selfRefOk    = false;
    foreach ($fks as $fk) {
        if ($fk['from'] === 'group_id' && $fk['table'] === 'artists') {
            $selfRefOk = true;
            break;
        }
    }

    if (!$hasIsGroup || !$selfRefOk) {
        $count = (int)$db->query("SELECT COUNT(*) FROM artists")->fetchColumn();
        if ($count > 0) {
            echo "  [ERROR] artists table has $count rows with incorrect structure.\n";
            echo "          Manual migration required.\n";
            exit(1);
        }
        $db->exec("DROP TABLE IF EXISTS artists");
        $artistsExists = false;
        $needRecreate  = true;
        $steps[] = "  [OK]    artists table dropped (was empty, wrong structure — recreating)";
    }
}

if (!$artistsExists) {
    try {
        $db->exec("
            CREATE TABLE artists (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                name       TEXT    NOT NULL UNIQUE,
                is_group   INTEGER NOT NULL DEFAULT 0,
                group_id   INTEGER NULL REFERENCES artists(id),
                created_at TEXT    DEFAULT (datetime('now', 'localtime')),
                updated_at TEXT    DEFAULT (datetime('now', 'localtime'))
            )
        ");
        $steps[] = "  [OK]    artists table created (self-referential, is_group column)";
    } catch (PDOException $e) {
        echo "  [ERROR] artists table: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    $steps[] = "  [SKIP]  artists table (already exists with correct structure)";
}

// --- 3. program_artists junction table ---
$junctionExists = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='program_artists'")->fetch();

try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS program_artists (
            program_id INTEGER NOT NULL,
            artist_id  INTEGER NOT NULL,
            PRIMARY KEY (program_id, artist_id),
            FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
            FOREIGN KEY (artist_id)  REFERENCES artists(id)  ON DELETE CASCADE
        )
    ");
    $steps[] = $junctionExists
        ? "  [SKIP]  program_artists table (already exists)"
        : "  [OK]    program_artists table created";
} catch (PDOException $e) {
    echo "  [ERROR] program_artists table: " . $e->getMessage() . "\n";
    exit(1);
}

// --- 4. Indexes ---
$indexes = [
    'idx_artists_is_group'           => 'CREATE INDEX IF NOT EXISTS idx_artists_is_group           ON artists(is_group)',
    'idx_artists_group_id'           => 'CREATE INDEX IF NOT EXISTS idx_artists_group_id            ON artists(group_id)',
    'idx_program_artists_program_id' => 'CREATE INDEX IF NOT EXISTS idx_program_artists_program_id  ON program_artists(program_id)',
    'idx_program_artists_artist_id'  => 'CREATE INDEX IF NOT EXISTS idx_program_artists_artist_id   ON program_artists(artist_id)',
];

foreach ($indexes as $name => $sql) {
    $exists = $db->query("SELECT name FROM sqlite_master WHERE type='index' AND name=" . $db->quote($name))->fetch();
    try {
        $db->exec($sql);
        $steps[] = $exists
            ? "  [SKIP]  index $name (already exists)"
            : "  [OK]    index $name created";
    } catch (PDOException $e) {
        echo "  [ERROR] index $name: " . $e->getMessage() . "\n";
        exit(1);
    }
}

foreach ($steps as $step) {
    echo $step . "\n";
}

echo "\nMigration completed successfully.\n";
echo "\nNext step:\n";
echo "  php generate-artists-mapping.php\n";
exit(0);
